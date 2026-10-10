<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * StockReservationTest
 *
 * Behaviour tests for the reserve-at-checkout stock model
 * (laravel-ecommerce-engine: pessimistic locking + stock deduction
 * inside the checkout DB transaction):
 *
 *   - checkout IMMEDIATELY reserves (decrements) stock inside Txn A
 *   - settlement webhook no longer touches stock (already reserved)
 *   - every cancellation path restores the reservation:
 *       customer cancel, Midtrans deny/expire/cancel, abandoned
 *       re-checkout replacement, snap-token error
 *   - variant reservations sync the parent product stock aggregate
 */
class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private function setupMocks(array $options = []): void
    {
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 10000, 'etd' => '2'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('verifySignature')->andReturn(true);

        if (($options['snap_throws'] ?? false) === true) {
            $midtransMock->shouldReceive('createSnapToken')
                ->andThrow(new \Exception('Midtrans down'));
        } else {
            $midtransMock->shouldReceive('createSnapToken')->andReturn([
                'token' => 'snap_token', 'redirect_url' => 'https://dummy.url',
            ]);
        }

        if (isset($options['cancel_outcome'])) {
            $midtransMock->shouldReceive('cancelTransaction')->andReturn([
                'outcome'     => $options['cancel_outcome'],
                'status_code' => $options['cancel_outcome'] === 'cancelled' ? 200 : 412,
                'message'     => '',
            ]);
        } else {
            $midtransMock->shouldReceive('cancelTransaction')->andReturn([
                'outcome'     => 'cancelled',
                'status_code' => 200,
                'message'     => '',
            ]);
        }

        $midtransMock->shouldReceive('refundTransaction')->andReturn([
            'outcome' => 'refunded', 'status_code' => 200, 'message' => 'ok',
        ]);

        $this->app->instance(MidtransService::class, $midtransMock);
    }

    /** @return array{0: User, 1: Product} */
    private function setupUserWithCart(int $stock, int $qty): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting-sr-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Anting Silver',
            'slug'        => 'anting-silver-sr-' . uniqid(),
            'price'       => 75000,
            'stock'       => $stock,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $user->getOrCreateCart()->items()->create([
            'product_id' => $product->id,
            'quantity'   => $qty,
        ]);

        return [$user, $product];
    }

    private function postCheckout(User $user): \Illuminate\Testing\TestResponse
    {
        return $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'Jane Doe',
                'recipient_phone' => '081111111111',
                'full_address'    => 'Jl. Reservasi No. 1',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40135',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'gopay',
                'shipping_cost'   => 0,
            ]);
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

    // ── Reservation at checkout ──────────────────────────────────────────────

    public function test_checkout_immediately_reserves_stock(): void
    {
        [$user, $product] = $this->setupUserWithCart(stock: 10, qty: 2);
        $this->setupMocks();

        $this->postCheckout($user)->assertOk();

        $this->assertSame(8, (int) $product->fresh()->stock);
    }

    public function test_settlement_webhook_does_not_change_stock(): void
    {
        [$user, $product] = $this->setupUserWithCart(stock: 10, qty: 2);
        $this->setupMocks();

        $checkout = $this->postCheckout($user);
        $checkout->assertOk();
        $this->assertSame(8, (int) $product->fresh()->stock);

        $orderNumber = $checkout->json('order_id');
        $this->postWebhook($orderNumber, 'settlement')->assertOk();

        // Stock was reserved at checkout — settlement must not decrement again
        $this->assertSame(8, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('orders', ['order_number' => $orderNumber, 'status' => 'paid']);
    }

    public function test_customer_cancel_pending_order_restores_stock(): void
    {
        [$user, $product] = $this->setupUserWithCart(stock: 10, qty: 2);
        $this->setupMocks();

        $checkout = $this->postCheckout($user);
        $checkout->assertOk();
        $orderNumber = $checkout->json('order_id');
        $this->assertSame(8, (int) $product->fresh()->stock);

        $this->actingAs($user)
            ->post(route('orders.cancel', $orderNumber))
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['order_number' => $orderNumber, 'status' => 'dibatalkan']);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_midtrans_deny_webhook_restores_stock(): void
    {
        [$user, $product] = $this->setupUserWithCart(stock: 10, qty: 2);
        $this->setupMocks();

        $checkout = $this->postCheckout($user);
        $checkout->assertOk();
        $orderNumber = $checkout->json('order_id');
        $this->assertSame(8, (int) $product->fresh()->stock);

        $this->postWebhook($orderNumber, 'deny')->assertOk();

        $this->assertDatabaseHas('orders', ['order_number' => $orderNumber, 'status' => 'dibatalkan']);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_midtrans_expire_webhook_restores_stock(): void
    {
        [$user, $product] = $this->setupUserWithCart(stock: 10, qty: 2);
        $this->setupMocks();

        $checkout = $this->postCheckout($user);
        $checkout->assertOk();
        $orderNumber = $checkout->json('order_id');

        $this->postWebhook($orderNumber, 'expire')->assertOk();

        $this->assertDatabaseHas('orders', ['order_number' => $orderNumber, 'status' => 'dibatalkan']);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_abandoned_recheckout_restores_previous_reservation(): void
    {
        [$user, $product] = $this->setupUserWithCart(stock: 10, qty: 2);
        $this->setupMocks(['cancel_outcome' => 'cancelled']);

        $first = $this->postCheckout($user);
        $first->assertOk();
        $oldOrderNumber = $first->json('order_id');
        $this->assertSame(8, (int) $product->fresh()->stock);

        // Drop the cached cart relation — without this the test-process $user
        // still holds the pre-checkout items collection (Laravel testing gotcha,
        // not a production concern: real requests resolve fresh models).
        $user->unsetRelation('cart');

        // Re-fill cart for a second checkout — the abandoned pending order
        // must have its reservation restored before the new one is reserved.
        $user->getOrCreateCart()->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        // Cart eager-loads items via $with, so the instance cached during the
        // refill create() still holds the pre-insert (empty) collection.
        // Drop it again so the next request resolves a fresh snapshot
        // (Laravel testing gotcha; real HTTP requests always resolve fresh models).
        $user->unsetRelation('cart');

        $second = $this->postCheckout($user);
        $second->assertOk();

        $this->assertDatabaseHas('orders', ['order_number' => $oldOrderNumber, 'status' => 'dibatalkan']);
        // restore 2 + reserve 1 => 10 - 1 = 9
        $this->assertSame(9, (int) $product->fresh()->stock);
    }

    public function test_snap_error_restores_stock(): void
    {
        [$user, $product] = $this->setupUserWithCart(stock: 10, qty: 2);
        $this->setupMocks(['snap_throws' => true]);

        $this->postCheckout($user)->assertStatus(500);

        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    // ── Variant reservation syncs parent aggregate ────────────────────────────

    public function test_variant_reservation_syncs_parent_product_stock(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Cincin', 'slug' => 'cincin-sr-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Cincin Silver',
            'slug'        => 'cincin-silver-sr-' . uniqid(),
            'price'       => 90000,
            'stock'       => 20, // aggregate: 8 + 12
            'weight'      => 10,
            'is_active'   => true,
        ]);
        $v1 = ProductVariant::create([
            'product_id'    => $product->id,
            'variant_type'  => 'Ukuran',
            'variant_name'  => 'Size 6',
            'sku'           => 'CNC-S6-' . uniqid(),
            'price_adjust'  => 0,
            'stock'         => 8,
            'is_active'     => true,
        ]);
        ProductVariant::create([
            'product_id'    => $product->id,
            'variant_type'  => 'Ukuran',
            'variant_name'  => 'Size 7',
            'sku'           => 'CNC-S7-' . uniqid(),
            'price_adjust'  => 0,
            'stock'         => 12,
            'is_active'     => true,
        ]);

        $user->getOrCreateCart()->items()->create([
            'product_id' => $product->id,
            'variant_id' => $v1->id,
            'quantity'   => 3,
        ]);

        $this->setupMocks();

        $this->postCheckout($user)->assertOk();

        $this->assertSame(5, (int) $v1->fresh()->stock);
        $this->assertSame(17, (int) $product->fresh()->stock); // 5 + 12
    }

    // ── Settlement of a cancelled (deny-cancel race) does not decrement ───────

    public function test_mark_as_paid_after_cancel_does_not_touch_stock(): void
    {
        [$user, $product] = $this->setupUserWithCart(stock: 10, qty: 2);
        $this->setupMocks();

        $checkout = $this->postCheckout($user);
        $checkout->assertOk();
        $orderNumber = $checkout->json('order_id');

        // Customer cancels first (reservation restored to 10)
        $this->actingAs($user)->post(route('orders.cancel', $orderNumber));
        $this->assertSame(10, (int) $product->fresh()->stock);

        // Late settlement must be auto-refunded, NOT resurrect the order or stock
        $this->postWebhook($orderNumber, 'settlement')->assertOk();

        $this->assertDatabaseHas('orders', ['order_number' => $orderNumber, 'status' => 'dibatalkan']);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }
}
