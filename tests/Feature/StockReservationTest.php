<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\ShippingService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            // Data uji: nominal diambil dari total order — qty checkout bervariasi
            // per test, dan verifikasi jumlah di markOrderPaid menolak selisih.
            'gross_amount'       => (string) Order::where('order_number', $orderNumber)->value('total_amount'),
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

    // ── Parent aggregate counts ACTIVE variants only (Project rule) ──────────

    /** @return array{0: User, 1: Product, 2: ProductVariant} */
    private function setupVariantProductWithInactiveVariant(): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang-sr-' . uniqid(), 'is_active' => true]);
        // products.stock seed sengaja salah (12 = 5+7): aturan proyek = varian aktif saja.
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Tali Silver',
            'slug'        => 'gelang-tali-sr-' . uniqid(),
            'price'       => 40000,
            'stock'       => 12,
            'weight'      => 10,
            'is_active'   => true,
        ]);
        $variantA = ProductVariant::create([
            'product_id'    => $product->id,
            'variant_type'  => 'Warna',
            'variant_name'  => 'Silver',
            'sku'           => 'GLT-S-' . uniqid(),
            'price_adjust'  => 0,
            'stock'         => 5,
            'is_active'     => true,
        ]);
        ProductVariant::create([
            'product_id'    => $product->id,
            'variant_type'  => 'Warna',
            'variant_name'  => 'Gold',
            'sku'           => 'GLT-G-' . uniqid(),
            'price_adjust'  => 0,
            'stock'         => 7,
            'is_active'     => false,
        ]);

        return [$user, $product, $variantA];
    }

    public function test_parent_stock_sync_ignores_inactive_variants_on_reserve(): void
    {
        [$user, $product, $variantA] = $this->setupVariantProductWithInactiveVariant();

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variantA->id,
            'quantity'   => 2,
        ]);

        DB::transaction(fn () => app(StockService::class)->reserveForCart($cart));

        $this->assertSame(3, (int) $variantA->fresh()->stock);
        // Parent = jumlah varian AKTIF saja (5-2=3), BUKAN semua varian (10).
        $this->assertSame(3, (int) $product->fresh()->stock);
    }

    public function test_parent_stock_sync_ignores_inactive_variants_on_restore(): void
    {
        [$user, $product, $variantA] = $this->setupVariantProductWithInactiveVariant();

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variantA->id,
            'quantity'   => 2,
        ]);

        DB::transaction(fn () => app(StockService::class)->reserveForCart($cart));
        $this->assertSame(3, (int) $product->fresh()->stock);

        $address = $user->addresses()->create([
            'label'          => 'Restock',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081111111111',
            'full_address'   => 'Jl. Restock No. 2',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40135',
        ]);
        $order = Order::create([
            'order_number' => 'ORD-SR-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 80000,
            'total_amount' => 80000,
            'status'       => 'pending',
        ]);
        $order->items()->create([
            'product_id'   => $product->id,
            'variant_id'   => $variantA->id,
            'quantity'     => 2,
            'product_name' => $product->name,
            'price'        => 40000,
            'subtotal'     => 80000,
        ]);

        DB::transaction(fn () => app(StockService::class)->restoreForOrder($order));

        $this->assertSame(5, (int) $variantA->fresh()->stock);
        // Parent kembali ke jumlah varian AKTIF saja (5), BUKAN semua varian (12).
        $this->assertSame(5, (int) $product->fresh()->stock);
    }

    public function test_non_variant_product_stock_logic_unchanged(): void
    {
        [$user, $product] = $this->setupUserWithCart(stock: 10, qty: 2);

        $cart = $user->getOrCreateCart();
        DB::transaction(fn () => app(StockService::class)->reserveForCart($cart));

        $this->assertSame(8, (int) $product->fresh()->stock);

        $address = $user->addresses()->create([
            'label'          => 'Restock',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081111111111',
            'full_address'   => 'Jl. Restock No. 3',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40135',
        ]);
        $order = Order::create([
            'order_number' => 'ORD-SR-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 150000,
            'status'       => 'pending',
        ]);
        $order->items()->create([
            'product_id'   => $product->id,
            'quantity'     => 2,
            'product_name' => $product->name,
            'price'        => 75000,
            'subtotal'     => 150000,
        ]);

        DB::transaction(fn () => app(StockService::class)->restoreForOrder($order));

        $this->assertSame(10, (int) $product->fresh()->stock);
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
