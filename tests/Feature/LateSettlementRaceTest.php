<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * LateSettlementRaceTest — fix C2 (settlement hilang senyap karena TOCTOU).
 *
 * Sebelum fix, markAsPaid membaca status order via value() TANPA lock
 * SEBELUM service mengunci baris: race "pending → dibatalkan" di jendela itu
 * membuat markOrderPaid() false dan controller diam (HTTP 200) — dana
 * tertangkap tanpa auto-refund. AutoCancelExpiredOrders berisiko sama
 * (kandidat dipilih 'pending', order bisa dibatalkan sebelum settle).
 *
 * Fix: PaymentSettlementService::settleOrder() mengambil SEMUA keputusan
 * (missing / already paid / cancelled / not pending / amount mismatch / paid)
 * di bawah lockForUpdate dan mengembalikan konstanta RESULT_*;
 * markOrderPaid() tetap bool wrapper dengan kontrak lama.
 *
 * Signature dihitung sungguhan (pola MidtransWebhookSecurityTest::sign) —
 * verifySignature TIDAK di-mock. refundTransaction di-mock lewat partial
 * Mockery 'Class[method]' (constructor asli tetap jalan, jadi server key test
 * terbaca; method non-whitelist berjalan asli).
 */
class LateSettlementRaceTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_KEY = 'SB-Midserver-Test-Key-123';

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createOrder(
        string $orderNumber,
        string $status = 'pending',
        ?string $paymentStatus = null,
        int $total = 100000
    ): array {
        $user     = User::factory()->create();
        $category = Category::create(['name' => 'Cincin', 'slug' => 'cincin-' . uniqid(), 'is_active' => true]);
        $product  = Product::create([
            'category_id' => $category->id,
            'name'        => 'Cincin Race',
            'slug'        => 'cincin-race-' . uniqid(),
            'price'       => intdiv($total, 2),
            'stock'       => 10,
            'weight'      => 5,
            'is_active'   => true,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081200000011',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Kecamatan A',
            'postal_code'    => '68111',
            'full_address'   => 'Jl. Race No. 1',
        ]);

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => $total,
            'total_amount' => $total,
            'status'       => $status,
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => intdiv($total, 2),
            'quantity'     => 2,
            'subtotal'     => $total,
        ]);

        $defaultPaymentStatus = match ($status) {
            'dibatalkan' => 'failed',
            'paid'       => 'success',
            default      => 'pending',
        };

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $orderNumber,
            'amount'            => $total,
            'status'            => $paymentStatus ?? $defaultPaymentStatus,
            'snap_token'        => 'snap-race-' . uniqid(),
            'expired_at'        => now()->addHours(24),
        ]);

        return compact('user', 'product', 'order');
    }

    /** Order pending lewat batas waktu (total 110000) untuk tes auto-cancel. */
    private function makeExpiredPendingOrder(User $user): Order
    {
        $address = $user->addresses()->create([
            'label'          => 'Rumah',
            'recipient_name' => $user->name,
            'phone'          => '08123456789',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40132',
            'full_address'   => 'Jl. Dago No. 10',
            'is_default'     => true,
        ]);

        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung-' . uniqid(), 'is_active' => true]);
        $product  = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Emas',
            'slug'        => 'kalung-emas-' . uniqid(),
            'price'       => 100000,
            'stock'       => 9,
            'weight'      => 50,
            'is_active'   => true,
        ]);

        $order = Order::create([
            'order_number'     => 'ELCRAFT-RACE-AUTOCANCEL-' . uniqid(),
            'user_id'          => $user->id,
            'address_id'       => $address->id,
            'subtotal'         => 100000,
            'shipping_cost'    => 10000,
            'courier'          => 'jne',
            'courier_service'  => 'REG',
            'total_amount'     => 110000,
            'status'           => 'pending',
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => $product->price,
            'quantity'     => 1,
            'subtotal'     => 100000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'payment_method'    => 'snap',
            'amount'            => 110000,
            'status'            => 'pending',
            'snap_token'        => 'snap-race-' . uniqid(),
            'expired_at'        => now()->subMinutes(5),
        ]);

        return $order;
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

    /** Payload webhook dengan signature sah untuk key yang sedang di-set di config. */
    private function signedNotification(array $overrides = []): array
    {
        $notification = array_merge([
            'order_id'           => '',
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'payment_type'       => 'gopay',
            'fraud_status'       => 'accept',
        ], $overrides);

        $notification['signature_key'] = $this->sign($notification, self::SANDBOX_KEY);

        return $notification;
    }

    private function useTestKey(): void
    {
        config()->set('midtrans.is_production', false);
        config()->set('midtrans.server_key_sandbox', self::SANDBOX_KEY);
    }

    /**
     * Partial mock: constructor asli berjalan (server key terbaca),
     * hanya refundTransaction yang di-stub.
     */
    private function partialRefundMock(): MidtransService
    {
        return Mockery::mock(MidtransService::class . '[refundTransaction]');
    }

    // ── (a) Race: batal setelah load, lock setelahnya → auto-refund ─────────

    /**
     * Jendela TOCTOU yang menimbulkan C2: model order pertama di-hydrate
     * ('retrieved'), lalu order dibatalkan lewat DB::table (tanpa event),
     * SEBELUM PaymentSettlementService mengunci baris. Settlement tetap
     * harus auto-refund (refund_key AUTO-{order_number}) — bukan diam senyap.
     */
    public function test_race_cancelled_between_load_and_lock_triggers_auto_refund(): void
    {
        Mail::fake();
        Log::spy();
        $this->useTestKey();

        ['product' => $product, 'order' => $order] = $this->createOrder('ELCRAFT-RACE-CANCEL-001');

        $mock = $this->partialRefundMock();
        $mock->shouldReceive('refundTransaction')
            ->once()
            ->withArgs(fn ($orderId, $amount, $reason, $refundKey) =>
                $orderId === 'ELCRAFT-RACE-CANCEL-001'
                && (int) $amount === 100000
                && $refundKey === 'AUTO-ELCRAFT-RACE-CANCEL-001'
            )
            ->andReturn(['outcome' => 'refunded', 'status_code' => 200, 'message' => '']);
        $this->app->instance(MidtransService::class, $mock);

        // Pembatalan tepat pada hydrate PERTAMA order target (retrieved),
        // yaitu sebelum service lock — DB::table tidak memicu event model.
        $retrievals = 0;
        Order::retrieved(function (Order $loaded) use (&$retrievals, $order) {
            if ($loaded->id !== $order->id) {
                return;
            }
            if (++$retrievals === 1) {
                DB::table('orders')->where('id', $order->id)->update(['status' => 'dibatalkan']);
            }
        });

        $this->postJson(route('midtrans.webhook'), $this->signedNotification([
            'order_id' => 'ELCRAFT-RACE-CANCEL-001',
        ]))->assertOk();

        $this->assertSame(
            'dibatalkan',
            DB::table('orders')->where('id', $order->id)->value('status'),
            'Order tidak boleh dibangkitkan oleh settlement telat'
        );
        $this->assertSame(
            'refunded',
            Payment::where('order_id', $order->id)->value('status'),
            'Race batal-vs-bayar: dana tertangkap harus auto-refund, bukan hilang senyap'
        );
        $this->assertSame(10, (int) DB::table('products')->where('id', $product->id)->value('stock'));
        Mail::assertNothingSent();

        Log::shouldHaveReceived('critical')
            ->withArgs(fn ($message, $context) => is_array($context)
                && ($context['order_number'] ?? null) === 'ELCRAFT-RACE-CANCEL-001')
            ->atLeast()
            ->once();
    }

    // ── (b) Late settlement sekuensial (regresi perilaku lama) ──────────────

    public function test_sequential_late_settlement_for_cancelled_order_auto_refunds(): void
    {
        Mail::fake();
        $this->useTestKey();

        ['product' => $product, 'order' => $order] = $this->createOrder('ELCRAFT-LATE-SEQ-002', 'dibatalkan');

        $mock = $this->partialRefundMock();
        $mock->shouldReceive('refundTransaction')
            ->once()
            ->withArgs(fn ($orderId, $amount, $reason, $refundKey) =>
                $orderId === 'ELCRAFT-LATE-SEQ-002'
                && (int) $amount === 100000
                && $refundKey === 'AUTO-ELCRAFT-LATE-SEQ-002'
            )
            ->andReturn(['outcome' => 'refunded', 'status_code' => 200, 'message' => '']);
        $this->app->instance(MidtransService::class, $mock);

        $this->postJson(route('midtrans.webhook'), $this->signedNotification([
            'order_id' => 'ELCRAFT-LATE-SEQ-002',
        ]))->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'dibatalkan']);
        $this->assertSame('refunded', Payment::where('order_id', $order->id)->value('status'));
        $this->assertSame(10, (int) DB::table('products')->where('id', $product->id)->value('stock'));
        Mail::assertNothingSent();
    }

    // ── (c) Order sudah paid → 200 diam, tanpa email/refund kedua ───────────

    public function test_already_paid_order_second_delivery_returns_200_quietly(): void
    {
        Mail::fake();
        Log::spy();
        $this->useTestKey();

        ['order' => $order] = $this->createOrder('ELCRAFT-PAID-DUP-003', 'paid');

        $mock = $this->partialRefundMock();
        $mock->shouldNotReceive('refundTransaction');
        $this->app->instance(MidtransService::class, $mock);

        $this->postJson(route('midtrans.webhook'), $this->signedNotification([
            'order_id' => 'ELCRAFT-PAID-DUP-003',
        ]))->assertOk();

        $this->assertSame('paid', DB::table('orders')->where('id', $order->id)->value('status'));
        $this->assertSame('success', Payment::where('order_id', $order->id)->value('status'));
        Mail::assertNothingSent();
        Log::shouldNotHaveReceived('critical');
    }

    // ── (d) settleOrder mengembalikan konstanta RESULT_* yang tepat ─────────

    public function test_settle_order_returns_expected_result_constants(): void
    {
        $service = app(PaymentSettlementService::class);

        ['order' => $pending]      = $this->createOrder('ELCRAFT-CONST-PENDING');
        ['order' => $mismatch]     = $this->createOrder('ELCRAFT-CONST-MISMATCH');
        ['order' => $paid]         = $this->createOrder('ELCRAFT-CONST-PAID', 'paid');
        ['order' => $cancelled]    = $this->createOrder('ELCRAFT-CONST-BATAL', 'dibatalkan');
        ['order' => $processing]   = $this->createOrder('ELCRAFT-CONST-DIPROSES', 'diproses');

        $this->assertSame(
            PaymentSettlementService::RESULT_PAID,
            $service->settleOrder($pending, ['gross_amount' => '100000.00'])
        );
        $this->assertSame(
            PaymentSettlementService::RESULT_AMOUNT_MISMATCH,
            $service->settleOrder($mismatch, ['gross_amount' => '99999.00'])
        );
        $this->assertSame(
            PaymentSettlementService::RESULT_ALREADY_PAID,
            $service->settleOrder($paid, ['gross_amount' => '100000.00'])
        );
        $this->assertSame(
            PaymentSettlementService::RESULT_ORDER_CANCELLED,
            $service->settleOrder($cancelled, ['gross_amount' => '100000.00'])
        );
        $this->assertSame(
            PaymentSettlementService::RESULT_NOT_PENDING,
            $service->settleOrder($processing, ['gross_amount' => '100000.00'])
        );
        $this->assertSame(
            PaymentSettlementService::RESULT_ORDER_MISSING,
            $service->settleOrder(Order::make(['order_number' => 'ELCRAFT-CONST-GHOST']))
        );
    }

    // ── (e) markOrderPaid tetap bool wrapper dengan kontrak lama ────────────

    public function test_mark_order_paid_wrapper_keeps_previous_boolean_contract(): void
    {
        $service = app(PaymentSettlementService::class);

        ['order' => $pending]    = $this->createOrder('ELCRAFT-WRAP-PENDING');
        ['order' => $paid]       = $this->createOrder('ELCRAFT-WRAP-PAID', 'paid');
        ['order' => $cancelled]  = $this->createOrder('ELCRAFT-WRAP-BATAL', 'dibatalkan');
        ['order' => $processing] = $this->createOrder('ELCRAFT-WRAP-DIPROSES', 'diproses');
        ['order' => $mismatch]   = $this->createOrder('ELCRAFT-WRAP-MISMATCH');

        $this->assertTrue($service->markOrderPaid($pending, ['gross_amount' => '100000.00']));
        $this->assertFalse($service->markOrderPaid($paid));
        $this->assertFalse($service->markOrderPaid($cancelled));
        $this->assertFalse($service->markOrderPaid($processing));
        $this->assertFalse($service->markOrderPaid($mismatch, ['gross_amount' => '99999.00']));
        $this->assertFalse($service->markOrderPaid(Order::make(['order_number' => 'ELCRAFT-WRAP-GHOST'])));
    }

    // ── (f) AutoCancel: batal di antara seleksi kandidat & settlement ───────

    /**
     * Order dibatalkan setelah terpilih sebagai kandidat dan lolos re-verify,
     * tepat saat Get Status API dipanggil — settleOrder di bawah lock harus
     * mengembalikan ORDER_CANCELLED yang dicatat kritis (order_number) TANPA
     * exception dan TANPA auto-refund dari command.
     */
    public function test_auto_cancel_settlement_found_for_cancelled_order_logs_critical(): void
    {
        Mail::fake();
        Log::spy();

        $user  = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);

        $mock = $this->partialRefundMock();
        $mock->shouldNotReceive('refundTransaction');
        $this->app->instance(MidtransService::class, $mock);

        Http::fake([
            '*/v2/*/status' => function () use ($order) {
                // Race: batal SETELAH re-verify 'pending', SEBELUM lock settlement.
                DB::table('orders')->where('id', $order->id)->update(['status' => 'dibatalkan']);

                return Http::response([
                    'transaction_status' => 'settlement',
                    'status_code'        => '200',
                    'payment_type'       => 'qris',
                    'gross_amount'       => '110000.00',
                ], 200);
            },
        ]);

        $this->artisan('orders:auto-cancel-expired')->assertExitCode(0);

        $this->assertSame('dibatalkan', DB::table('orders')->where('id', $order->id)->value('status'));
        $this->assertSame(
            'pending',
            Payment::where('order_id', $order->id)->value('status'),
            'Auto-cancel tidak boleh auto-refund / menyentuh payment'
        );
        Mail::assertNothingSent();

        Log::shouldHaveReceived('critical')
            ->withArgs(fn ($message, $context) => is_array($context)
                && ($context['order_number'] ?? null) === $order->order_number
                && str_contains((string) $message, 'settlement found for cancelled order, needs manual refund'))
            ->atLeast()
            ->once();
    }
}
