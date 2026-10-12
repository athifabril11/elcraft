<?php

namespace Tests\Feature;

use App\Mail\OrderCancelledMail;
use App\Mail\PaymentSuccessMail;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Category;
use App\Models\Product;
use App\Models\Address;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Feature tests for the "orders:auto-cancel-expired" scheduled command.
 *
 * Pending orders whose payment deadline (payment.expired_at, fallback
 * created_at+24h) has passed are cancelled automatically: Midtrans
 * transaction cancelled, payment marked failed, stock reservation
 * restored, and the customer notified via OrderCancelledMail.
 */
class AutoCancelExpiredOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpiredPendingOrder(User $user, array $paymentOverrides = [], array $orderOverrides = []): Order
    {
        $address = Address::create([
            'user_id'        => $user->id,
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
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Emas',
            'slug'        => 'kalung-emas-' . uniqid(),
            'price'       => 100000,
            'stock'       => 9,
            'weight'      => 50,
            'is_active'   => true,
        ]);

        $order = Order::create(array_merge([
            'order_number'     => 'ELCRAFT-EXPIRETEST-' . uniqid(),
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
        ], $orderOverrides));

        $order->items()->create([
            'product_id'      => $product->id,
            'product_name'    => $product->name,
            'price'           => $product->price,
            'discount_amount' => 0,
            'quantity'        => 1,
            'subtotal'        => 100000,
        ]);

        Payment::create(array_merge([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'payment_method'    => 'snap',
            'amount'            => 110000,
            'status'            => 'pending',
            'snap_token'        => 'snap-token-test-' . uniqid(),
            'expired_at'        => now()->subMinutes(5),
        ], $paymentOverrides));

        return $order;
    }

    public function test_expired_pending_order_is_cancelled_with_stock_restore_and_email(): void
    {
        Mail::fake();
        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'pending',
                'status_code'        => '200',
            ], 200),
            '*/v2/*/cancel' => Http::response(
                ['status_code' => '200', 'status_message' => 'Success, transaction is canceled'],
                200
            ),
        ]);

        $user = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);
        $product = $order->items->first()->product;

        $this->artisan('orders:auto-cancel-expired')
            ->expectsOutputToContain('Auto-cancelled 1')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals('dibatalkan', $order->status);
        $this->assertEquals('failed', $order->payment->status);
        $this->assertEquals(10, $product->fresh()->stock);

        Mail::assertSent(OrderCancelledMail::class, function (OrderCancelledMail $mail) use ($order) {
            return $mail->hasTo($order->user->email)
                && $mail->envelope()->subject === "Pembayaran Gagal untuk Pesanan #{$order->order_number} - el Craft";
        });

        Http::assertSent(fn ($request) => str_contains($request->url(), "/v2/{$order->order_number}/cancel"));
    }

    public function test_non_expired_pending_order_is_not_cancelled(): void
    {
        Mail::fake();
        Http::fake();

        $user = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user, ['expired_at' => now()->addHours(12)]);

        $this->artisan('orders:auto-cancel-expired')
            ->expectsOutputToContain('Auto-cancelled 0')
            ->assertExitCode(0);

        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals('pending', $order->fresh()->payment->status);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_non_pending_order_with_expired_payment_is_not_affected(): void
    {
        Mail::fake();
        Http::fake();

        $user = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user, [], ['status' => 'paid']);

        $this->artisan('orders:auto-cancel-expired')->assertExitCode(0);

        $this->assertEquals('paid', $order->fresh()->status);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_midtrans_cancel_failure_keeps_order_pending_for_next_run(): void
    {
        Mail::fake();
        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'pending',
                'status_code'        => '200',
            ], 200),
            '*/v2/*/cancel' => Http::response(
                ['status_code' => '412', 'status_message' => 'Transaction is finished, cannot be modify'],
                200
            ),
        ]);

        $user = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);
        $product = $order->items->first()->product;

        $this->artisan('orders:auto-cancel-expired')
            ->expectsOutputToContain('Auto-cancelled 0')
            ->assertExitCode(0);

        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals('pending', $order->fresh()->payment->status);
        $this->assertEquals(9, $product->fresh()->stock);
        Mail::assertNothingSent();
    }

    public function test_pending_order_without_payment_past_24h_is_cancelled(): void
    {
        Mail::fake();
        Http::fake([
            '*/v2/*/status' => Http::response(
                ['status_code' => '404', 'status_message' => 'Transaction doesn\'t exist'],
                404
            ),
        ]);

        $user = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);
        $order->payment->delete();
        $order->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->artisan('orders:auto-cancel-expired')
            ->expectsOutputToContain('Auto-cancelled 1')
            ->assertExitCode(0);

        $this->assertEquals('dibatalkan', $order->fresh()->status);

        // Body 404 = transaksi tidak pernah ada — batalkan lokal TANPA cancel API
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancel'));
    }

    public function test_recent_pending_order_without_payment_is_not_cancelled(): void
    {
        Mail::fake();
        Http::fake();

        $user = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);
        $order->payment->delete();

        $this->artisan('orders:auto-cancel-expired')
            ->expectsOutputToContain('Auto-cancelled 0')
            ->assertExitCode(0);

        $this->assertEquals('pending', $order->fresh()->status);
        Http::assertNothingSent();
    }

    /** Fake Midtrans: Get Status melaporkan settlement (cancel tidak boleh dipanggil). */
    private function fakeLateSettlement(): void
    {
        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'settlement',
                'status_code'        => '200',
                'payment_type'       => 'qris',
                'gross_amount'       => '110000.00',
            ], 200),
        ]);
    }

    public function test_late_settlement_via_get_status_records_voucher_usage(): void
    {
        Mail::fake();
        $this->fakeLateSettlement();

        $user = User::factory()->create();
        $voucher = Voucher::create([
            'code'           => 'LATESETTLE',
            'discount_type'  => 'nominal',
            'discount_value' => 10000,
            'min_purchase'   => 0,
            'quota'          => 10,
            'used_count'     => 0,
            'start_date'     => now()->subDay()->toDateString(),
            'end_date'       => now()->addDay()->toDateString(),
            'is_active'      => true,
        ]);
        $order = $this->makeExpiredPendingOrder($user, [], [
            'voucher_id'       => $voucher->id,
            'voucher_discount' => 10000,
            'total_amount'     => 100000,
        ]);
        $product = $order->items->first()->product;

        $this->artisan('orders:auto-cancel-expired')->assertExitCode(0);

        $order->refresh();
        $this->assertEquals('paid', $order->status, 'Order telat bayar harus jadi paid, bukan dibatalkan');
        $this->assertEquals('success', $order->payment->status);
        $this->assertEquals('qris', $order->payment->payment_type);

        // Pemakaian voucher WAJIB tercatat sama seperti jalur webhook
        $this->assertEquals(1, $voucher->fresh()->used_count, 'used_count harus naik saat late settlement');
        $this->assertDatabaseHas('voucher_usages', [
            'voucher_id' => $voucher->id,
            'user_id'    => $user->id,
            'order_id'   => $order->id,
        ]);

        // Stok tidak dipulihkan — sudah di-reserve saat checkout, order paid
        $this->assertEquals(9, $product->fresh()->stock);

        // Late settlement juga membuat shipment record (sama seperti jalur webhook)
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id]);

        // Pembayaran sudah diketahui lunas — cancel API TIDAK BOLEH dipanggil
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancel'));
    }

    public function test_late_settlement_with_exhausted_quota_does_not_over_redeem(): void
    {
        Mail::fake();
        $this->fakeLateSettlement();

        $user = User::factory()->create();
        $voucher = Voucher::create([
            'code'           => 'HABIS1',
            'discount_type'  => 'nominal',
            'discount_value' => 10000,
            'min_purchase'   => 0,
            'quota'          => 1,
            'used_count'     => 1,
            'start_date'     => now()->subDay()->toDateString(),
            'end_date'       => now()->addDay()->toDateString(),
            'is_active'      => true,
        ]);
        $order = $this->makeExpiredPendingOrder($user, [], [
            'voucher_id'       => $voucher->id,
            'voucher_discount' => 10000,
            'total_amount'     => 100000,
        ]);

        $this->artisan('orders:auto-cancel-expired')->assertExitCode(0);

        $order->refresh();
        $this->assertEquals('paid', $order->status, 'Dana sudah tertangkap — order tetap paid');
        $this->assertEquals(1, $voucher->fresh()->used_count, 'used_count tidak boleh melampaui kuota');
        $this->assertDatabaseMissing('voucher_usages', ['order_id' => $order->id]);
        $this->assertStringContainsString('PERINGATAN SISTEM', (string) $order->notes);
    }

    public function test_late_settlement_sends_payment_success_email(): void
    {
        Mail::fake();
        $this->fakeLateSettlement();

        $user = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);

        $this->artisan('orders:auto-cancel-expired')->assertExitCode(0);

        $this->assertEquals('paid', $order->fresh()->status);
        Mail::assertSent(PaymentSuccessMail::class, function (PaymentSuccessMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->order->status === 'paid';
        });
    }

    public function test_mark_order_paid_with_missing_order_returns_false(): void
    {
        $service = new \App\Services\PaymentSettlementService();
        $ghost   = Order::make(['id' => 999999, 'order_number' => 'ELCRAFT-GHOST']);

        $this->assertFalse(
            $service->markOrderPaid($ghost),
            'Order yang tidak ada di DB harus mengembalikan false, bukan melempar exception'
        );
    }

    public function test_capture_with_fraud_accept_marks_paid_without_cancel(): void
    {
        // (a) Kartu kredit berstatus 'capture' + fraud 'accept' — pembayaran sah.
        //     Cancel API akan MEMBATALKAN pembayaran itu; perintah wajib
        //     menanyakan status DULU dan menandai lunas tanpa pernah cancel.
        Mail::fake();
        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'capture',
                'fraud_status'       => 'accept',
                'status_code'        => '200',
                'payment_type'       => 'credit_card',
                'gross_amount'       => '110000.00',
            ], 200),
        ]);

        $user  = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);
        $product = $order->items->first()->product;

        $this->artisan('orders:auto-cancel-expired')
            ->expectsOutputToContain('1 late-settlement')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals('paid', $order->status);
        $this->assertEquals('success', $order->payment->status);
        $this->assertEquals('credit_card', $order->payment->payment_type);
        $this->assertEquals(9, $product->fresh()->stock, 'Stok tidak boleh dipulihkan untuk pembayaran sah');

        Mail::assertSent(PaymentSuccessMail::class, function (PaymentSuccessMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->order->status === 'paid';
        });

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancel'));
    }

    public function test_expire_status_cancels_locally_without_cancel_api(): void
    {
        // (e) Status 'expire' dari Get Status — transaksi sudah kedaluwarsa di
        //     Midtrans, batalkan lokal TANPA memanggil cancel API.
        Mail::fake();
        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'expire',
                'status_code'        => '200',
            ], 200),
        ]);

        $user  = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);
        $product = $order->items->first()->product;

        $this->artisan('orders:auto-cancel-expired')
            ->expectsOutputToContain('Auto-cancelled 1')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals('dibatalkan', $order->status);
        $this->assertEquals('failed', $order->payment->status);
        $this->assertEquals(10, $product->fresh()->stock, 'Stok harus dipulihkan');

        Mail::assertSent(OrderCancelledMail::class, function (OrderCancelledMail $mail) use ($order) {
            return $mail->hasTo($order->user->email);
        });

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancel'));
    }

    public function test_status_api_failure_keeps_order_pending_without_cancel(): void
    {
        // (g) Get Status API gagal (500) — status tidak diketahui; JANGAN pernah
        //     membatalkan lokal pada keadaan yang tidak pasti.
        Mail::fake();
        Http::fake([
            '*/v2/*/status' => Http::response([], 500),
        ]);

        $user  = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);
        $product = $order->items->first()->product;

        $this->artisan('orders:auto-cancel-expired')
            ->expectsOutputToContain('1 skipped')
            ->assertExitCode(0);

        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals(9, $product->fresh()->stock);
        Mail::assertNothingSent();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancel'));
    }

    public function test_capture_with_fraud_challenge_is_skipped(): void
    {
        // (h) 'capture' dengan fraud_status selain 'accept' (mis. 'challenge')
        //     — jangan batalkan, jangan tandai lunas; tunggu keputusan Midtrans.
        Mail::fake();
        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'capture',
                'fraud_status'       => 'challenge',
                'status_code'        => '200',
                'payment_type'       => 'credit_card',
            ], 200),
        ]);

        $user  = User::factory()->create();
        $order = $this->makeExpiredPendingOrder($user);

        $this->artisan('orders:auto-cancel-expired')
            ->expectsOutputToContain('1 skipped')
            ->assertExitCode(0);

        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals('pending', $order->fresh()->payment->status);
        Mail::assertNothingSent();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancel'));
    }
}
