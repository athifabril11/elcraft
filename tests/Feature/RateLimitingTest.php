<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

/**
 * RateLimitingTest
 *
 * Memverifikasi rate limiter bernama (AppServiceProvider) pada endpoint
 * publik yang rawan disalahgunakan:
 *   - throttle:voucher → POST /checkout/voucher/apply   (10/menit/user)
 *   - throttle:shipping → prefix /shipping              (30/menit/user)
 *   - throttle:cart     → mutating cart routes          (60/menit/user)
 *   - POST /midtrans/webhook TIDAK boleh di-throttle
 *   - schedule orders:auto-cancel-expired non-overlapping (30 menit)
 *
 * Semua request eksternal (RajaOngkir/Komerce) di-mock dengan Http::fake.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache store untuk test adalah `array`, dikonfigurasi di phpunit.xml.
        // Flush agar bucket rate limiter bersih dan tiap test berdiri sendiri
        // tanpa tergantung urutan.
        Cache::flush();
    }

    private function makeVoucher(array $overrides = []): Voucher
    {
        return Voucher::create(array_merge([
            'code'           => 'HEMAT50',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'min_purchase'   => 0,
            'max_discount'   => null,
            'quota'          => 100,
            'used_count'     => 0,
            'start_date'     => now()->subDay(),
            'end_date'       => now()->addDay(),
            'is_active'      => true,
        ], $overrides));
    }

    private function fakeKomerceCost(): void
    {
        Http::fake([
            '*/domestic-cost*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [
                    [
                        'code'        => 'jne',
                        'name'        => 'Jalur Nugraha Ekakurir (JNE)',
                        'service'     => 'OKE',
                        'description' => 'Ongkos Kirim Ekonomis',
                        'cost'        => 15000,
                        'etd'         => '3-4',
                    ],
                ],
            ], 200),
        ]);
    }

    private function postVoucherApply(User $user)
    {
        return $this->actingAs($user)->postJson(route('voucher.apply'), [
            'code'     => 'HEMAT50',
            'subtotal' => 100000,
        ]);
    }

    // ── throttle:voucher ────────────────────────────────────────────────────

    public function test_voucher_apply_throttles_after_10_requests_per_user(): void
    {
        $user = User::factory()->create();
        $this->makeVoucher();

        for ($i = 1; $i <= 10; $i++) {
            $response = $this->postVoucherApply($user);
            $this->assertNotSame(429, $response->status(), "Request #{$i} unexpectedly throttled.");
        }

        $this->postVoucherApply($user)->assertStatus(429);
    }

    public function test_voucher_rate_limit_is_isolated_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->makeVoucher();

        // Habiskan kuota milik A (11 request → 11th kena 429).
        for ($i = 1; $i <= 11; $i++) {
            $response = $this->postVoucherApply($userA);
        }
        $this->assertSame(429, $response->status());

        // B adalah user berbeda — bucket terpisah, tidak boleh ikut kena 429.
        $this->postVoucherApply($userB)->assertOk();
    }

    // ── throttle:shipping ───────────────────────────────────────────────────

    public function test_shipping_cost_throttles_after_30_requests(): void
    {
        $this->fakeKomerceCost();

        $payload = ['city_id' => 23, 'weight' => 1000, 'courier' => 'jne'];

        for ($i = 1; $i <= 30; $i++) {
            $response = $this->postJson('/shipping/cost', $payload);
            $this->assertNotSame(429, $response->status(), "Request #{$i} unexpectedly throttled.");
        }

        $this->postJson('/shipping/cost', $payload)->assertStatus(429);
    }

    public function test_shipping_and_voucher_rate_limit_buckets_are_independent(): void
    {
        $user = User::factory()->create();
        $this->makeVoucher();
        $this->fakeKomerceCost();

        // Habiskan bucket voucher milik user ini.
        for ($i = 1; $i <= 11; $i++) {
            $this->postVoucherApply($user);
        }
        $this->postVoucherApply($user)->assertStatus(429);

        // Bucket shipping (nama limiter berbeda) tidak ikut terpengaruh.
        $this->actingAs($user)
            ->postJson('/shipping/cost', ['city_id' => 23, 'weight' => 1000, 'courier' => 'jne'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // ── throttle:cart ───────────────────────────────────────────────────────

    public function test_cart_mutations_throttle_after_60_requests_per_user(): void
    {
        $user = User::factory()->create();

        $category = Category::create([
            'name'      => 'Aksesoris',
            'slug'      => 'aksesoris',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Cincin Titanium',
            'slug'        => 'cincin-titanium',
            'price'       => 50000,
            'stock'       => 30,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $item = $user->getOrCreateCart()->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $payload = ['item_id' => $item->id, 'quantity' => 2];

        for ($i = 1; $i <= 60; $i++) {
            $response = $this->actingAs($user)->postJson(route('cart.update'), $payload);
            $this->assertNotSame(429, $response->status(), "Request #{$i} unexpectedly throttled.");
        }

        $this->actingAs($user)->postJson(route('cart.update'), $payload)->assertStatus(429);
    }

    // ── webhook Midtrans tidak di-throttle ──────────────────────────────────

    public function test_midtrans_webhook_is_not_rate_limited(): void
    {
        $middleware = Route::getRoutes()->getByName('midtrans.webhook')->gatherMiddleware();

        foreach ($middleware as $entry) {
            $this->assertFalse(
                str_starts_with((string) $entry, 'throttle'),
                "Webhook unexpectedly has throttle middleware: {$entry}"
            );
        }

        // Payload dengan signature salah → tiap request berakhir 403 (aman,
        // tanpa panggilan API eksternal), tapi tidak boleh pernah 429.
        $payload = [
            'order_id'           => 'ORD-NONEXISTENT',
            'status_code'        => '200',
            'gross_amount'       => '10000',
            'transaction_status' => 'settlement',
            'payment_type'       => 'qris',
            'signature_key'      => 'invalid-signature',
        ];

        for ($i = 1; $i <= 105; $i++) {
            $response = $this->postJson(route('midtrans.webhook'), $payload);
            $this->assertNotSame(429, $response->status(), "Request #{$i} unexpectedly throttled.");
        }
    }

    // ── schedule: orders:auto-cancel-expired non-overlapping ────────────────

    public function test_auto_cancel_schedule_is_non_overlapping(): void
    {
        // Callback withSchedule() di bootstrap/app.php baru dijalankan ketika
        // aplikasi console (Artisan) diboot — paksa dulu sebelum membaca event.
        $this->app->make(ConsoleKernel::class)
            ->call('list', [], new NullOutput());

        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'orders:auto-cancel-expired'));

        $this->assertNotEmpty($events);

        // Assert properties on every matching event — in the PHPUnit process the
        // static console bootstrappers can register the withSchedule callback
        // more than once (verified: production `schedule:list` shows exactly one).
        foreach ($events as $event) {
            $this->assertTrue($event->withoutOverlapping);
            $this->assertSame(30, $event->expiresAt);
        }
    }
}
