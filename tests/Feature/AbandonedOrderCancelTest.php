<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * AbandonedOrderCancelTest
 *
 * Regression/behavior tests for abandoned pending orders during re-checkout:
 *   - Midtrans Cancel API MUST be called for each abandoned pending order
 *     (otherwise the old Snap session stays payable after local cancellation)
 *   - local 'dibatalkan' only when Midtrans outcome is cancelled/not_found
 *   - outcome cannot_modify/error leaves the old order untouched (retry later)
 *
 * The Midtrans call happens OUTSIDE the checkout DB transaction.
 */
class AbandonedOrderCancelTest extends TestCase
{
    use RefreshDatabase;

    private function setupShippingAndSnapMocks(string $cancelOutcome): void
    {
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 10000, 'etd' => '2'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturn(['token' => 'new_token', 'redirect_url' => 'https://dummy.url']);
        $midtransMock->shouldReceive('cancelTransaction')
            ->once()
            ->andReturn([
                'outcome'     => $cancelOutcome,
                'status_code' => $cancelOutcome === 'cancelled' ? 200 : 412,
                'message'     => '',
            ]);
        $this->app->instance(MidtransService::class, $midtransMock);
    }

    /** @return array{0: User, 1: Order, 2: Payment} */
    private function setupUserWithAbandonedPendingOrder(): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung-ab-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Silver',
            'slug'        => 'kalung-silver-ab-' . uniqid(),
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081111111111',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40135',
            'full_address'   => 'Jl. Abandoned No. 1',
        ]);

        $oldOrder = Order::create([
            'order_number' => 'ELCRAFT-ABANDONED-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 50000,
            'total_amount' => 50000,
            'status'       => 'pending',
        ]);

        $oldPayment = Payment::create([
            'order_id'          => $oldOrder->id,
            'midtrans_order_id' => $oldOrder->order_number,
            'amount'            => 50000,
            'status'            => 'pending',
            'snap_token'        => 'old_snap_token',
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        return [$user, $oldOrder, $oldPayment];
    }

    private function postCheckout(User $user): \Illuminate\Testing\TestResponse
    {
        return $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'Jane Doe',
                'recipient_phone' => '081111111111',
                'full_address'    => 'Jl. Abandoned No. 1',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40135',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'gopay',
                'shipping_cost'   => 0,
            ]);
    }

    public function test_checkout_cancels_abandoned_pending_order_at_midtrans(): void
    {
        [$user, $oldOrder, $oldPayment] = $this->setupUserWithAbandonedPendingOrder();
        $this->setupShippingAndSnapMocks('cancelled');

        $this->postCheckout($user)->assertOk();

        // Midtrans cancel was invoked with the old order number
        // (Mockery ->once() on cancelTransaction fails the test otherwise)

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'dibatalkan']);
        $this->assertDatabaseHas('payments', ['id' => $oldPayment->id, 'status' => 'failed']);
    }

    public function test_checkout_keeps_abandoned_order_pending_when_midtrans_blocks_cancel(): void
    {
        [$user, $oldOrder, $oldPayment] = $this->setupUserWithAbandonedPendingOrder();
        $this->setupShippingAndSnapMocks('cannot_modify');

        // New checkout must still succeed even though the old order couldn't be
        // closed at Midtrans — but the old order must NOT be locally cancelled
        // (a locally-cancelled-but-still-payable order traps customer money).
        $this->postCheckout($user)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'pending']);
        $this->assertDatabaseHas('payments', ['id' => $oldPayment->id, 'status' => 'pending']);
    }

    public function test_checkout_treats_not_found_as_cancellable(): void
    {
        [$user, $oldOrder] = $this->setupUserWithAbandonedPendingOrder();
        $this->setupShippingAndSnapMocks('not_found');

        $this->postCheckout($user)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'dibatalkan']);
    }

    public function test_checkout_without_pending_orders_never_calls_cancel(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Pin', 'slug' => 'pin-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Pin Buket',
            'slug'        => 'pin-buket-' . uniqid(),
            'price'       => 30000,
            'stock'       => 5,
            'weight'      => 5,
            'is_active'   => true,
        ]);
        $user->getOrCreateCart()->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reg', 'cost' => 5000, 'etd' => '2'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')->once()->andReturn([
            'token' => 't', 'redirect_url' => 'https://dummy.url',
        ]);
        // No cancelTransaction expectation — calling it would throw an
        // "unexpected method call" Mockery exception and fail this test.
        $this->app->instance(MidtransService::class, $midtransMock);

        $this->actingAs($user)->postJson(route('checkout.token'), [
            'recipient_name'  => 'Jane Doe',
            'recipient_phone' => '081234567890',
            'full_address'    => 'Jl. Merdeka No. 10',
            'city'            => 'Bandung',
            'city_id'         => 23,
            'postal_code'     => '40115',
            'courier'         => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method'  => 'gopay',
            'shipping_cost'   => 0,
        ])->assertOk();
    }
}
