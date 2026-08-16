<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Address;
use App\Services\MidtransService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class CourierPersistenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that checkout persists courier and courier_service to the orders table,
     * and when webhook settlement marks the order as paid, the created Shipment
     * record uses the order's selected courier and service (NOT 'Manual').
     */
    public function test_checkout_persists_courier_and_webhook_creates_shipment_with_selected_courier(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kalung Emas',
            'slug' => 'kalung-emas',
            'price' => 100000,
            'stock' => 10,
            'weight' => 10,
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        // Mock ShippingService
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 15000, 'etd' => '2-3'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        // Mock MidtransService
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturn(['token' => 'snap_token_jne_test', 'redirect_url' => 'https://dummy.url']);
        $midtransMock->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        $this->app->instance(MidtransService::class, $midtransMock);

        // 1. Trigger Checkout
        $response = $this->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'Jane Doe',
                'recipient_phone' => '081299998888',
                'full_address'    => 'Jl. Asia Afrika No. 1',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40111',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'gopay',
                'shipping_cost'   => 15000,
            ]);

        $response->assertOk();
        $orderId = $response->json('order_id');

        // Assert order table has saved courier and courier_service
        $this->assertDatabaseHas('orders', [
            'order_number'    => $orderId,
            'courier'         => 'jne',
            'courier_service' => 'jne-REG',
        ]);

        // 2. Simulate Midtrans Webhook settlement notification
        $webhookData = [
            'order_id'           => $orderId,
            'status_code'        => '200',
            'transaction_status' => 'settlement',
            'gross_amount'       => '115000.00',
            'payment_type'       => 'bank_transfer',
            'signature_key'      => 'dummy_signature',
        ];

        $webhookResponse = $this->postJson(route('midtrans.webhook'), $webhookData);
        $webhookResponse->assertOk();

        // 3. Assert Shipment record was created using stored order courier details, NOT 'Manual'
        $order = Order::where('order_number', $orderId)->first();
        $this->assertNotNull($order->shipment);
        $this->assertEquals('jne', $order->shipment->courier);
        $this->assertEquals('jne-REG', $order->shipment->service);
    }

    /**
     * Test backward compatibility: legacy order with NULL courier and courier_service
     * falls back to 'Manual' and 'Reguler' when webhook settlement creates shipment.
     */
    public function test_legacy_order_with_null_courier_falls_back_to_manual_shipment(): void
    {
        $user = User::factory()->create();
        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Kecamatan',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Test No. 1',
        ]);

        // Create legacy order without courier or courier_service
        $order = Order::create([
            'order_number'    => 'ELCRAFT-LEGACY-' . uniqid(),
            'user_id'         => $user->id,
            'address_id'      => $address->id,
            'subtotal'        => 50000,
            'shipping_cost'   => 10000,
            'total_amount'    => 60000,
            'status'          => 'pending',
            'courier'         => null,
            'courier_service' => null,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 60000,
            'status'            => 'pending',
            'snap_token'        => 'legacy_token',
        ]);

        // Mock Webhook signature check
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        $this->app->instance(MidtransService::class, $midtransMock);

        $webhookData = [
            'order_id'           => $order->order_number,
            'status_code'        => '200',
            'transaction_status' => 'settlement',
            'gross_amount'       => '60000.00',
            'payment_type'       => 'gopay',
            'signature_key'      => 'dummy_signature',
        ];

        $webhookResponse = $this->postJson(route('midtrans.webhook'), $webhookData);
        $webhookResponse->assertOk();

        // Shipment created with fallback values
        $order->refresh();
        $this->assertNotNull($order->shipment);
        $this->assertEquals('Manual', $order->shipment->courier);
        $this->assertEquals('Reguler', $order->shipment->service);
    }

}
