<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger as IlluminateLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

/**
 * MidtransAmountVerificationTest
 *
 * Verifikasi nominal: gross_amount notifikasi Midtrans WAJIB sama dengan
 * total_amount order sebelum markOrderPaid mengubah apa pun (TASK A), dan
 * signature_key tidak boleh bocor ke log maupun payments.response_data
 * (TASK B).
 *
 * Signature dihitung sungguhan (pola MidtransWebhookSecurityTest::sign) —
 * verifySignature TIDAK di-mock.
 */
class MidtransAmountVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_KEY = 'SB-Midserver-Test-Key-123';

    /** Substring khas marker idempoten di orders.notes. */
    private const MARKER_TEXT = 'Nominal pembayaran dari Midtrans';

    private function createPendingOrderWithPayment(string $orderNumber, int $total): array
    {
        $user     = User::factory()->create();
        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang-amt-' . uniqid(), 'is_active' => true]);
        $product  = Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Aman',
            'slug'        => 'gelang-aman-' . uniqid(),
            'price'       => intdiv($total, 2),
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
            'full_address'   => 'Jl. Verifikasi Nominal No. 1',
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
            'price'        => intdiv($total, 2),
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

    /** (f) helper: order pending lewat batas waktu (total 110000). */
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
            'order_number'     => 'ELCRAFT-AMT-AUTOCANCEL-' . uniqid(),
            'user_id'          => $user->id,
            'address_id'       => $address->id,
            'subtotal'         => 100000,
            'discount_amount'  => 0,
            'voucher_discount' => 0,
            'shipping_cost'    => 10000,
            'courier'          => 'jne',
            'courier_service'  => 'REG',
            'total_amount'     => 110000,
            'status'           => 'pending',
        ]);

        $order->items()->create([
            'product_id'      => $product->id,
            'product_name'    => $product->name,
            'price'           => $product->price,
            'discount_amount' => 0,
            'quantity'        => 1,
            'subtotal'        => 100000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'payment_method'    => 'snap',
            'amount'            => 110000,
            'status'            => 'pending',
            'snap_token'        => 'snap-token-amt-' . uniqid(),
            'expired_at'        => now()->subMinutes(5),
        ]);

        return $order;
    }

    // ── (a) Settlement dengan nominal berbeda → tolak, tandai, log kritis ─────

    public function test_settlement_with_mismatching_gross_amount_keeps_order_pending_and_flags_notes(): void
    {
        Mail::fake();
        Log::spy();
        $this->useTestKey();

        ['product' => $product, 'order' => $order] = $this->createPendingOrderWithPayment('ELCRAFT-AMT-MISMATCH', 100000);

        $notification = $this->signedNotification([
            'order_id'     => 'ELCRAFT-AMT-MISMATCH',
            'gross_amount' => '99999.00',
        ]);

        $this->postJson(route('midtrans.webhook'), $notification)->assertOk();

        $order->refresh();
        $this->assertSame('pending', $order->status, 'Nominal beda: order TIDAK boleh jadi paid');
        $this->assertSame('pending', Payment::where('order_id', $order->id)->value('status'), 'Payment tidak boleh diubah');
        $this->assertSame(10, $product->refresh()->stock, 'Stok tidak boleh tersentuh');
        $this->assertStringContainsString(
            self::MARKER_TEXT,
            (string) $order->notes,
            'orders.notes harus menerima marker PERINGATAN SISTEM'
        );
        Mail::assertNothingSent();

        Log::shouldHaveReceived('critical')
            ->withArgs(function ($message, $context) {
                return is_array($context)
                    && isset($context['order_number'], $context['expected_amount'], $context['received_amount'])
                    && !array_key_exists('signature_key', $context);
            })
            ->atLeast()
            ->once();
    }

    // ── (b) Nominal cocok → paid (regression: "110000.00", "110000", "110000.0")

    public function test_matching_gross_amount_marks_order_paid(): void
    {
        Mail::fake();
        $this->useTestKey();

        foreach (['110000.00', '110000', '110000.0'] as $i => $gross) {
            $orderNumber = 'ELCRAFT-AMT-MATCH-' . $i;

            ['order' => $order] = $this->createPendingOrderWithPayment($orderNumber, 110000);

            $notification = $this->signedNotification([
                'order_id'     => $orderNumber,
                'gross_amount' => $gross,
            ]);

            $this->postJson(route('midtrans.webhook'), $notification)->assertOk();

            $this->assertSame('paid', $order->refresh()->status, "gross_amount '{$gross}' harus diterima");
            $this->assertSame('success', Payment::where('order_id', $order->id)->value('status'));
        }
    }

    // ── (c) Pengiriman ulang notification yang sama tidak menggandakan marker ─

    public function test_repeated_mismatching_settlement_does_not_duplicate_marker_in_notes(): void
    {
        Mail::fake();
        Log::spy();
        $this->useTestKey();

        ['order' => $order] = $this->createPendingOrderWithPayment('ELCRAFT-AMT-DUP', 100000);

        $notification = $this->signedNotification([
            'order_id'     => 'ELCRAFT-AMT-DUP',
            'gross_amount' => '99999.00',
        ]);

        $this->postJson(route('midtrans.webhook'), $notification)->assertOk();
        $this->postJson(route('midtrans.webhook'), $notification)->assertOk();

        $notes = (string) $order->refresh()->notes;

        $this->assertSame(
            1,
            substr_count($notes, self::MARKER_TEXT),
            'Marker harus idempoten: pengiriman ulang tidak boleh menduplikasi catatan'
        );
    }

    // ── (d) Log pending & status tak dikenal tidak pernah memuat signature_key ─

    public function test_pending_and_unknown_status_logs_never_contain_signature_key(): void
    {
        $this->useTestKey();

        $testHandler = new TestHandler();
        Log::swap(new IlluminateLogger(new MonologLogger('testing', [$testHandler])));

        ['order' => $pendingOrder] = $this->createPendingOrderWithPayment('ELCRAFT-AMT-LOGPENDING', 100000);
        $this->postJson(route('midtrans.webhook'), $this->signedNotification([
            'order_id'           => 'ELCRAFT-AMT-LOGPENDING',
            'transaction_status' => 'pending',
            'fraud_status'       => null,
        ]))->assertOk();

        ['order' => $unknownOrder] = $this->createPendingOrderWithPayment('ELCRAFT-AMT-LOGUNKNOWN', 100000);
        $this->postJson(route('midtrans.webhook'), $this->signedNotification([
            'order_id'           => 'ELCRAFT-AMT-LOGUNKNOWN',
            'transaction_status' => 'status_ngawur',
        ]))->assertOk();

        $this->assertSame('pending', $pendingOrder->refresh()->status);
        $this->assertSame('pending', $unknownOrder->refresh()->status);

        $records = $testHandler->getRecords();
        $this->assertNotEmpty($records, 'Kedua cabang harus menghasilkan minimal satu log');

        $messages = array_map(fn ($record) => (string) $record['message'], $records);
        $this->assertTrue(
            collect($messages)->contains(fn ($m) => str_contains($m, 'menunggu pembayaran')),
            'Cabang pending harus tetap menghasilkan log'
        );
        $this->assertTrue(
            collect($messages)->contains(fn ($m) => str_contains($m, 'status tidak dikenal')),
            'Cabang status tak dikenal harus tetap menghasilkan log'
        );

        foreach ($records as $record) {
            $this->assertArrayNotHasKey(
                'signature_key',
                $record['context'],
                "Log context tidak boleh memuat signature_key: {$record['message']}"
            );
        }
    }

    // ── (e) payments.response_data tersimpan tanpa signature_key ──────────────

    public function test_response_data_persisted_without_signature_key_for_settlement_and_pending(): void
    {
        Mail::fake();
        $this->useTestKey();

        ['order' => $settleOrder] = $this->createPendingOrderWithPayment('ELCRAFT-AMT-RDSETTLE', 100000);
        $this->postJson(route('midtrans.webhook'), $this->signedNotification([
            'order_id'     => 'ELCRAFT-AMT-RDSETTLE',
            'gross_amount' => '100000.00',
        ]))->assertOk();

        // Catatan: controller menyimpan json_encode(...) ke kolom cast 'array'
        // → ter-encode dua kali → nilai terbaca berupa string JSON. Decode manual.
        $settleRaw = Payment::where('order_id', $settleOrder->id)->first()->response_data;
        $settleData = is_string($settleRaw) ? json_decode($settleRaw, true) : $settleRaw;
        $this->assertIsArray($settleData, 'response_data tetap harus berisi isi notifikasi sebagai array');
        $this->assertArrayHasKey('order_id', $settleData, 'response_data tetap harus berisi isi notifikasi');
        $this->assertArrayNotHasKey('signature_key', $settleData, 'signature_key tidak boleh ikut tersimpan (settlement)');

        ['order' => $pendingOrder] = $this->createPendingOrderWithPayment('ELCRAFT-AMT-RDPENDING', 100000);
        $this->postJson(route('midtrans.webhook'), $this->signedNotification([
            'order_id'           => 'ELCRAFT-AMT-RDPENDING',
            'transaction_status' => 'pending',
            'fraud_status'       => null,
        ]))->assertOk();

        $pendingRaw  = Payment::where('order_id', $pendingOrder->id)->first()->response_data;
        $pendingData = is_string($pendingRaw) ? json_decode($pendingRaw, true) : $pendingRaw;
        $this->assertIsArray($pendingData, 'response_data pending tetap harus berisi array');
        $this->assertArrayHasKey('transaction_status', $pendingData);
        $this->assertArrayNotHasKey('signature_key', $pendingData, 'signature_key tidak boleh ikut tersimpan (pending)');
    }

    // ── (f) Auto-cancel: Get Status settlement dengan nominal beda ────────────

    public function test_auto_cancel_get_status_with_mismatching_gross_amount_keeps_order_pending(): void
    {
        Mail::fake();
        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'settlement',
                'status_code'        => '200',
                'payment_type'       => 'qris',
                'gross_amount'       => '99999.00',
            ], 200),
        ]);

        $user  = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);

        $this->artisan('orders:auto-cancel-expired')->assertExitCode(0);

        $order->refresh();
        $this->assertSame('pending', $order->status, 'Nominal beda: auto-cancel tidak boleh menandai paid');
        $this->assertSame('pending', $order->payment->status);
        $this->assertStringContainsString(self::MARKER_TEXT, (string) $order->notes);
        Mail::assertNothingSent();
    }

    public function test_auto_cancel_get_status_with_matching_gross_amount_marks_paid(): void
    {
        Mail::fake();
        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'settlement',
                'status_code'        => '200',
                'payment_type'       => 'qris',
                'gross_amount'       => '110000.00',
            ], 200),
        ]);

        $user  = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);

        $this->artisan('orders:auto-cancel-expired')->assertExitCode(0);

        $order->refresh();
        $this->assertSame('paid', $order->status, 'Nominal cocok: perilaku lama tetap paid');
        $this->assertSame('success', $order->payment->status);
    }
}
