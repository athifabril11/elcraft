<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * MidtransWebhookSecurityTest
 *
 * Webhook Midtrans harus FAIL-CLOSED:
 *   a) Server key kosong       → signature tidak bisa diverifikasi dengan aman,
 *                                semua notifikasi DITOLAK (403), tanpa side effect.
 *   b) Field wajib hilang      → 403 (bukan 500 yang memicu retry Midtrans).
 *   c) Signature valid + key   → tetap diproses (regression guard).
 *
 * Jangan mock verifySignature di test ini — yang diuji adalah logika
 * verifikasi signature yang asli di MidtransService.
 */
class MidtransWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_KEY = 'SB-Midserver-Test-Key-123';

    private function createPendingOrderWithPayment(string $orderNumber, int $total = 100000): array
    {
        $user     = User::factory()->create();
        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang-sec', 'is_active' => true]);
        $product  = Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Kupu',
            'slug'        => 'gelang-kupu-sec',
            'price'       => (int) ($total / 2),
            'stock'       => 10,
            'weight'      => 5,
            'is_active'   => true,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081200000009',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Kecamatan A',
            'postal_code'    => '68111',
            'full_address'   => 'Jl. Test Keamanan Webhook No. 1',
        ]);

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => $total,
            'total_amount' => $total,
            'status'       => 'pending',
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => (int) ($total / 2),
            'quantity'     => 2,
            'subtotal'     => $total,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => $total,
            'status'            => 'pending',
        ]);

        return compact('user', 'product', 'order');
    }

    private function sign(array $notification, string $serverKey): string
    {
        return hash('sha512',
            $notification['order_id'] .
            $notification['status_code'] .
            $notification['gross_amount'] .
            $serverKey
        );
    }

    // ── (a) Server key kosong → fail closed ──────────────────────────────────

    public function test_empty_server_key_rejects_settlement_with_403_and_no_side_effects(): void
    {
        Mail::fake();

        config()->set('midtrans.is_production', false);
        config()->set('midtrans.server_key_sandbox', '');

        ['product' => $product, 'order' => $order] = $this->createPendingOrderWithPayment('ELCRAFT-SEC-EMPTYKEY');

        $notification = [
            'order_id'           => 'ELCRAFT-SEC-EMPTYKEY',
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'payment_type'       => 'gopay',
            'fraud_status'       => 'accept',
        ];
        // Penyerang menghitung signature dengan key yang sama-sama kosong.
        $notification['signature_key'] = $this->sign($notification, '');

        $response = $this->postJson(route('midtrans.webhook'), $notification);

        $response->assertStatus(403);

        $this->assertSame('pending', $order->refresh()->status, 'Order tidak boleh berubah saat key kosong');
        $this->assertSame('pending', Payment::where('order_id', $order->id)->value('status'));
        $this->assertSame(10, $product->refresh()->stock, 'Stok tidak boleh tersentuh');
        Mail::assertNothingSent();
    }

    // ── (b) Field wajib hilang → 403, bukan 500 ──────────────────────────────

    public function test_missing_gross_amount_returns_403_not_500(): void
    {
        config()->set('midtrans.is_production', false);
        config()->set('midtrans.server_key_sandbox', self::SANDBOX_KEY);

        ['order' => $order] = $this->createPendingOrderWithPayment('ELCRAFT-SEC-NOGROSS');

        $this->postJson(route('midtrans.webhook'), [
            'order_id'           => 'ELCRAFT-SEC-NOGROSS',
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'payment_type'       => 'gopay',
            'signature_key'      => 'whatever',
        ])->assertStatus(403);

        $this->assertSame('pending', $order->refresh()->status);
    }

    public function test_missing_status_code_returns_403_not_500(): void
    {
        config()->set('midtrans.is_production', false);
        config()->set('midtrans.server_key_sandbox', self::SANDBOX_KEY);

        ['order' => $order] = $this->createPendingOrderWithPayment('ELCRAFT-SEC-NOCODE');

        $this->postJson(route('midtrans.webhook'), [
            'order_id'           => 'ELCRAFT-SEC-NOCODE',
            'transaction_status' => 'settlement',
            'gross_amount'       => '100000.00',
            'payment_type'       => 'gopay',
            'signature_key'      => 'whatever',
        ])->assertStatus(403);

        $this->assertSame('pending', $order->refresh()->status);
    }

    public function test_missing_signature_key_returns_403_not_500(): void
    {
        config()->set('midtrans.is_production', false);
        config()->set('midtrans.server_key_sandbox', self::SANDBOX_KEY);

        ['order' => $order] = $this->createPendingOrderWithPayment('ELCRAFT-SEC-NOSIG');

        $this->postJson(route('midtrans.webhook'), [
            'order_id'           => 'ELCRAFT-SEC-NOSIG',
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'payment_type'       => 'gopay',
        ])->assertStatus(403);

        $this->assertSame('pending', $order->refresh()->status);
    }

    // ── (d) signature_key / order_id berbentuk array → 403, bukan 500 ────────

    public function test_array_signature_key_returns_403_not_500(): void
    {
        config()->set('midtrans.is_production', false);
        config()->set('midtrans.server_key_sandbox', self::SANDBOX_KEY);

        ['order' => $order] = $this->createPendingOrderWithPayment('ELCRAFT-SEC-ARRSIG');

        $this->postJson(route('midtrans.webhook'), [
            'order_id'           => 'ELCRAFT-SEC-ARRSIG',
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'payment_type'       => 'gopay',
            'signature_key'      => ['x'],
        ])->assertStatus(403);

        $this->assertSame('pending', $order->refresh()->status);
    }

    public function test_array_order_id_returns_403_not_500(): void
    {
        config()->set('midtrans.is_production', false);
        config()->set('midtrans.server_key_sandbox', self::SANDBOX_KEY);

        ['order' => $order] = $this->createPendingOrderWithPayment('ELCRAFT-SEC-ARROID');

        $this->postJson(route('midtrans.webhook'), [
            'order_id'           => ['x'],
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'payment_type'       => 'gopay',
            'signature_key'      => 'whatever',
        ])->assertStatus(403);

        $this->assertSame('pending', $order->refresh()->status);
    }

    // ── (c) Signature valid + key valid → tetap diproses (regression) ────────

    public function test_valid_signature_with_non_empty_key_still_returns_200(): void
    {
        Mail::fake();

        config()->set('midtrans.is_production', false);
        config()->set('midtrans.server_key_sandbox', self::SANDBOX_KEY);

        ['order' => $order] = $this->createPendingOrderWithPayment('ELCRAFT-SEC-VALID');

        $notification = [
            'order_id'           => 'ELCRAFT-SEC-VALID',
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'payment_type'       => 'gopay',
            'fraud_status'       => 'accept',
        ];
        $notification['signature_key'] = $this->sign($notification, self::SANDBOX_KEY);

        $this->postJson(route('midtrans.webhook'), $notification)->assertOk();

        $this->assertSame('paid', $order->refresh()->status);
        $this->assertSame('success', Payment::where('order_id', $order->id)->value('status'));
    }
}
