<?php

namespace Tests\Feature;

use App\Mail\OrderCancelledMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class OrderCancelledEmailTest extends TestCase
{
    use RefreshDatabase;

    private function mockSignatureValid(): void
    {
        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);
    }

    private function postWebhook(string $orderNumber, string $transactionStatus): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $orderNumber,
            'transaction_status' => $transactionStatus,
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'signature_key'      => 'dummy_signature',
            'payment_type'       => 'gopay',
            'fraud_status'       => 'accept',
        ]);
    }

    private function makeOrder(string $orderStatus, string $paymentStatus): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung-oce-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Perak',
            'slug'        => 'kalung-oce-' . uniqid(),
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 5,
            'is_active'   => true,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000010',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40135',
            'full_address'   => 'Jl. Order Cancelled No. 1',
        ]);

        $order = Order::create([
            'order_number' => 'ELCRAFT-OCE-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => $orderStatus,
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 50000,
            'quantity'     => 2,
            'subtotal'     => 100000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 100000,
            'status'            => $paymentStatus,
        ]);

        return [$user, $order];
    }

    // ── Customer cancel ──────────────────────────────────────────────────────

    public function test_customer_cancel_sends_cancelled_email(): void
    {
        Mail::fake();
        [$user, $order] = $this->makeOrder('pending', 'pending');

        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('cancelTransaction')
            ->once()
            ->andReturn(['outcome' => 'cancelled', 'status_code' => 200, 'message' => 'ok']);
        $this->app->instance(MidtransService::class, $mock);

        $this->actingAs($user)
            ->post(route('orders.cancel', $order->order_number))
            ->assertSessionHas('success');

        Mail::assertSent(OrderCancelledMail::class, function (OrderCancelledMail $mail) use ($user, $order) {
            return $mail->hasTo($user->email)
                && $mail->event === 'customer_cancel'
                && str_contains($mail->envelope()->subject, $order->order_number);
        });

        $this->assertSame('dibatalkan', $order->fresh()->status);
    }

    // ── Webhook deny / expire / cancel ───────────────────────────────────────

    public function test_webhook_deny_sends_payment_failed_email(): void
    {
        Mail::fake();
        $this->mockSignatureValid();
        [$user, $order] = $this->makeOrder('pending', 'pending');

        $this->postWebhook($order->order_number, 'deny')->assertOk();

        Mail::assertSent(OrderCancelledMail::class, function (OrderCancelledMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->event === 'payment_failed';
        });

        $this->assertSame('dibatalkan', $order->fresh()->status);
    }

    public function test_webhook_expire_sends_payment_failed_email(): void
    {
        Mail::fake();
        $this->mockSignatureValid();
        [$user, $order] = $this->makeOrder('pending', 'pending');

        $this->postWebhook($order->order_number, 'expire')->assertOk();

        Mail::assertSent(OrderCancelledMail::class, fn (OrderCancelledMail $mail) => $mail->event === 'payment_failed' && $mail->hasTo($user->email));
    }

    public function test_webhook_merchant_cancel_on_paid_order_sends_email(): void
    {
        Mail::fake();
        $this->mockSignatureValid();
        [$user, $order] = $this->makeOrder('paid', 'success');

        $this->postWebhook($order->order_number, 'cancel')->assertOk();

        Mail::assertSent(OrderCancelledMail::class, fn (OrderCancelledMail $mail) => $mail->event === 'payment_failed' && $mail->hasTo($user->email));

        $this->assertSame('dibatalkan', $order->fresh()->status);
    }

    public function test_no_email_when_cancellation_is_rejected_by_guard(): void
    {
        Mail::fake();
        $this->mockSignatureValid();
        [, $order] = $this->makeOrder('diproses', 'success');

        $this->postWebhook($order->order_number, 'cancel')->assertOk();

        Mail::assertNothingSent();
        $this->assertSame('diproses', $order->fresh()->status);
    }

    // ── Resilience & view ────────────────────────────────────────────────────

    public function test_email_failure_does_not_break_customer_cancel_redirect(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        [$user, $order] = $this->makeOrder('pending', 'pending');

        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('cancelTransaction')
            ->once()
            ->andReturn(['outcome' => 'cancelled', 'status_code' => 200, 'message' => 'ok']);
        $this->app->instance(MidtransService::class, $mock);

        $this->actingAs($user)
            ->post(route('orders.cancel', $order->order_number))
            ->assertSessionHas('success');

        $this->assertSame('dibatalkan', $order->fresh()->status);
    }

    public function test_email_view_renders_order_details(): void
    {
        [, $order] = $this->makeOrder('pending', 'pending');

        $html = (new OrderCancelledMail($order, 'payment_failed'))->render();

        $this->assertStringContainsString($order->order_number, $html);
    }
}
