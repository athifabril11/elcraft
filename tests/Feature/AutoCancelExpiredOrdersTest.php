<?php

namespace Tests\Feature;

use App\Mail\OrderCancelledMail;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Category;
use App\Models\Product;
use App\Models\Address;
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
            '*/v2/*/cancel' => Http::response(
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
}
