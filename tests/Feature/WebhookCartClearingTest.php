<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WebhookCartClearingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to post a settlement webhook payload with valid mock signature.
     */
    private function postSettlementWebhook(string $orderNumber, string $paymentType = 'gopay'): \Illuminate\Testing\TestResponse
    {
        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);

        return $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $orderNumber,
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'signature_key'      => 'dummy_signature',
            'payment_type'       => $paymentType,
            'fraud_status'       => 'accept',
        ]);
    }

    /**
     * Helper to create a basic test setup.
     */
    private function createOrderSetup(User $user, string $orderNumber = 'ELCRAFT-CART-001'): Order
    {
        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081200000001',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Kecamatan A',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Test Cart No. 1',
        ]);

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => 'pending',
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $orderNumber,
            'amount'            => 100000,
            'status'            => 'pending',
            'snap_token'        => 'dummy_token',
        ]);

        return $order;
    }

    /**
     * 1a. User has an order pending (cart cleared at token creation),
     * customer adds a NEW item to cart, settlement webhook arrives -> new cart item STILL EXISTS.
     */
    public function test_settlement_preserves_cart_items_added_after_checkout(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting', 'is_active' => true]);
        $newProduct = Product::create([
            'category_id' => $category->id,
            'name'        => 'Anting Mutiara Baru',
            'slug'        => 'anting-mutiara-baru',
            'price'       => 80000,
            'stock'       => 10,
            'weight'      => 5,
            'is_active'   => true,
        ]);

        // Customer completed checkout previously: pending order exists, cart was emptied at Snap token creation
        $order = $this->createOrderSetup($user, 'ELCRAFT-CART-POST-CHECKOUT');

        // Customer adds a NEW item to cart while payment is pending
        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $newProduct->id,
            'quantity'   => 1,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => $newProduct->id,
        ]);

        // Webhook arrives for the earlier pending order
        $response = $this->postSettlementWebhook($order->order_number);

        $response->assertStatus(200);
        $this->assertEquals('paid', $order->fresh()->status);

        // Invariant: settlement MUST NOT touch the cart — newly added items remain intact
        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => $newProduct->id,
        ]);
        $this->assertEquals(1, CartItem::where('cart_id', $cart->id)->count());
        $this->assertCount(1, $cart->fresh()->items);
    }

    /**
     * 1b. When markAsPaid() successfully processes a webhook for an order,
     * the customer's cart items are preserved (settlement does not touch cart).
     */
    public function test_webhook_mark_as_paid_does_not_clear_customer_cart_items(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Cincin', 'slug' => 'cincin', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Cincin Perak',
            'slug'        => 'cincin-perak',
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 5,
            'is_active'   => true,
        ]);

        // Berikan item ke keranjang belanja user
        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 2,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
        ]);
        $this->assertEquals(1, CartItem::where('cart_id', $cart->id)->count());

        $order = $this->createOrderSetup($user, 'ELCRAFT-CART-001');

        $response = $this->postSettlementWebhook($order->order_number);

        $response->assertStatus(200);
        $this->assertEquals('paid', $order->fresh()->status);

        // Pastikan isi keranjang pelanggan TIDAK dikosongkan oleh webhook
        $this->assertEquals(1, CartItem::where('cart_id', $cart->id)->count());
        $this->assertCount(1, $cart->fresh()->items);
        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
        ]);
    }

    /**
     * 2a. If the customer's cart does not exist (null), markAsPaid() handles it gracefully without error.
     */
    public function test_webhook_mark_as_paid_handles_null_cart_gracefully(): void
    {
        $user = User::factory()->create();
        // User tanpa cart record
        $this->assertNull($user->cart);

        $order = $this->createOrderSetup($user, 'ELCRAFT-CART-002');

        $response = $this->postSettlementWebhook($order->order_number);

        $response->assertStatus(200);
        $this->assertEquals('paid', $order->fresh()->status);
    }

    /**
     * 2b. If the customer's cart is already empty at the time of markAsPaid(), no error occurs.
     */
    public function test_webhook_mark_as_paid_handles_empty_cart_gracefully(): void
    {
        $user = User::factory()->create();
        $cart = $user->getOrCreateCart();
        $this->assertEquals(0, $cart->items()->count());

        $order = $this->createOrderSetup($user, 'ELCRAFT-CART-003');

        $response = $this->postSettlementWebhook($order->order_number);

        $response->assertStatus(200);
        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertEquals(0, $cart->fresh()->items()->count());
    }

    /**
     * 3. Duplicate webhook notifications remain idempotent and preserve customer cart.
     */
    public function test_duplicate_webhook_settlement_remains_idempotent_and_preserves_cart(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Emas',
            'slug'        => 'kalung-emas',
            'price'       => 100000,
            'stock'       => 10,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $order = $this->createOrderSetup($user, 'ELCRAFT-CART-004');

        // Panggilan webhook pertama
        $resp1 = $this->postSettlementWebhook($order->order_number);
        $resp1->assertStatus(200);
        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertEquals(1, CartItem::where('cart_id', $cart->id)->count());

        // Panggilan webhook kedua (duplikat)
        $resp2 = $this->postSettlementWebhook($order->order_number);
        $resp2->assertStatus(200);
        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertEquals(1, CartItem::where('cart_id', $cart->id)->count());
    }

    /**
     * 4. Verify the client-side finish() method renders checkout.finish view and preserves cart.
     */
    public function test_checkout_finish_client_side_redirect_preserves_cart(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Mutiara',
            'slug'        => 'gelang-mutiara',
            'price'       => 75000,
            'stock'       => 5,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $this->assertEquals(1, CartItem::where('cart_id', $cart->id)->count());

        $response = $this->actingAs($user)->get(route('checkout.finish', [
            'order_id'           => 'ELCRAFT-CART-005',
            'transaction_status' => 'settlement',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('checkout.finish');

        // Pastikan keranjang TIDAK dihapus ketika finish() dipanggil
        $this->assertEquals(1, CartItem::where('cart_id', $cart->id)->count());
        $this->assertCount(1, $cart->fresh()->items);
    }
}
