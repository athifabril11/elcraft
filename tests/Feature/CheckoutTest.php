<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_active_cart(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name' => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address' => 'Jl. Merdeka No. 10',
                'city' => 'Bandung',
                'postal_code' => '40115',
            ]);

        $response->assertStatus(422);
    }

    public function test_checkout_can_create_snap_token(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Anting Emas',
            'slug' => 'anting-emas',
            'price' => 50000,
            'stock' => 10,
            'weight' => 5,
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Mock Midtrans Service
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturn(['token' => 'dummy_snap_token', 'redirect_url' => 'https://dummy.url']);
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name' => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address' => 'Jl. Merdeka No. 10',
                'city' => 'Bandung',
                'postal_code' => '40115',
                'notes' => 'Tolong dibungkus kado',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['snap_token', 'order_id']);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total_amount' => 100000,
            'status' => 'pending',
            'notes' => 'Tolong dibungkus kado',
        ]);

        $this->assertDatabaseHas('payments', [
            'amount' => 100000,
            'status' => 'pending',
            'snap_token' => 'dummy_snap_token',
        ]);
    }

    public function test_checkout_validates_stock(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Anting Emas',
            'slug' => 'anting-emas',
            'price' => 50000,
            'stock' => 1,
            'weight' => 5,
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name' => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address' => 'Jl. Merdeka No. 10',
                'city' => 'Bandung',
                'postal_code' => '40115',
            ]);

        $response->assertStatus(422);
    }

    public function test_webhook_payment_success(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Anting Emas',
            'slug' => 'anting-emas',
            'price' => 50000,
            'stock' => 10,
            'weight' => 5,
            'is_active' => true,
        ]);

        $address = $user->addresses()->create([
            'label' => 'Utama',
            'recipient_name' => 'John Doe',
            'phone' => '081234567890',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'district' => 'Kecamatan',
            'postal_code' => '40115',
            'full_address' => 'Jl. Merdeka No. 10',
        ]);

        $order = Order::create([
            'order_number' => 'ELCRAFT-TEST1234',
            'user_id' => $user->id,
            'address_id' => $address->id,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 50000,
            'quantity' => 2,
            'subtotal' => 100000,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount' => 100000,
            'status' => 'pending',
        ]);

        // Mock Webhook signature check
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this->postJson(route('midtrans.webhook'), [
            'order_id' => 'ELCRAFT-TEST1234',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => 'dummy_signature',
            'payment_type' => 'gopay',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'success',
            'payment_method' => 'gopay',
        ]);

        $product->refresh();
        $this->assertEquals(8, $product->stock); // Initial 10 - 2
    }
}
