<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * AutoCompleteDeliveredOrdersTest
 *
 * Tests the `orders:auto-complete` Artisan command.
 *
 * Covers:
 *  - Orders at 'dikirim' older than threshold → advanced to 'selesai'
 *  - Orders at 'dikirim' within threshold → NOT touched
 *  - Orders at other statuses → NEVER touched regardless of age
 *  - Custom threshold value is respected
 *  - Command output reports the correct count
 */
class AutoCompleteDeliveredOrdersTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper ───────────────────────────────────────────────────────────────

    /**
     * Create a minimal order, then manually set its updated_at so we can
     * simulate different ages without having to wait real time.
     */
    private function makeOrder(string $status, int $daysOld): Order
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Test No. 1',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-AUTOCOMPLETE-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => $status,
        ]);

        // Force updated_at via DB::table — bypasses all Eloquent timestamp
        // handling so the exact past date is always written to the database.
        DB::table('orders')
            ->where('id', $order->id)
            ->update(['updated_at' => now()->subDays($daysOld)->toDateTimeString()]);

        return $order->fresh();
    }

    private function setThreshold(int $days): void
    {
        ShopSetting::set('order_auto_complete_days', (string) $days);
    }

    // ── Core behaviour ───────────────────────────────────────────────────────

    public function test_dikirim_order_older_than_threshold_is_auto_completed(): void
    {
        $this->setThreshold(7);
        $order = $this->makeOrder('dikirim', 8); // 8 days old > 7-day threshold

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('selesai', $order->fresh()->status);
    }

    public function test_dikirim_order_exactly_on_threshold_boundary_is_auto_completed(): void
    {
        $this->setThreshold(7);
        $order = $this->makeOrder('dikirim', 7); // exactly 7 days → should be completed (<=)

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('selesai', $order->fresh()->status);
    }

    public function test_dikirim_order_within_threshold_is_not_touched(): void
    {
        $this->setThreshold(7);
        $order = $this->makeOrder('dikirim', 3); // 3 days old < 7-day threshold

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('dikirim', $order->fresh()->status);
    }

    // ── Other statuses are never touched ─────────────────────────────────────

    public function test_paid_order_is_never_touched(): void
    {
        $this->setThreshold(1);
        $order = $this->makeOrder('paid', 30);

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('paid', $order->fresh()->status);
    }

    public function test_diproses_order_is_never_touched(): void
    {
        $this->setThreshold(1);
        $order = $this->makeOrder('diproses', 30);

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('diproses', $order->fresh()->status);
    }

    public function test_pending_order_is_never_touched(): void
    {
        $this->setThreshold(1);
        $order = $this->makeOrder('pending', 30);

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_selesai_order_is_never_touched(): void
    {
        $this->setThreshold(1);
        $order = $this->makeOrder('selesai', 30);

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('selesai', $order->fresh()->status);
    }

    public function test_dibatalkan_order_is_never_touched(): void
    {
        $this->setThreshold(1);
        $order = $this->makeOrder('dibatalkan', 30);

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('dibatalkan', $order->fresh()->status);
    }

    // ── Custom threshold ──────────────────────────────────────────────────────

    public function test_custom_threshold_of_3_days_completes_4_day_old_order(): void
    {
        $this->setThreshold(3);
        $order = $this->makeOrder('dikirim', 4); // 4 days > 3-day threshold

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('selesai', $order->fresh()->status);
    }

    public function test_custom_threshold_of_3_days_does_not_complete_2_day_old_order(): void
    {
        $this->setThreshold(3);
        $order = $this->makeOrder('dikirim', 2); // 2 days < 3-day threshold

        $this->artisan('orders:auto-complete')->assertSuccessful();

        $this->assertEquals('dikirim', $order->fresh()->status);
    }

    // ── Command output count ──────────────────────────────────────────────────

    public function test_command_reports_zero_when_no_orders_qualify(): void
    {
        $this->setThreshold(7);
        $this->makeOrder('dikirim', 3); // within threshold — not completed

        $this->artisan('orders:auto-complete')
             ->expectsOutput('Auto-completed 0 order(s).')
             ->assertSuccessful();
    }

    public function test_command_reports_correct_count_of_completed_orders(): void
    {
        $this->setThreshold(7);

        $this->makeOrder('dikirim', 10); // old — qualifies
        $this->makeOrder('dikirim', 8);  // old — qualifies
        $this->makeOrder('dikirim', 2);  // recent — does NOT qualify

        $this->artisan('orders:auto-complete')
             ->expectsOutput('Auto-completed 2 order(s).')
             ->assertSuccessful();
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    public function test_completed_order_is_logged(): void
    {
        Log::spy();

        $this->setThreshold(7);
        $this->makeOrder('dikirim', 8);

        $this->artisan('orders:auto-complete')->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'auto-complete')
                    && isset($context['order_number']);
            });
    }
}
