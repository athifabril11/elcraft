<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * WebhookIdempotencyTest
 *
 * Proves that MidtransWebhookController::markAsPaid() is idempotent:
 * sending the same settlement webhook twice produces the same end-state as once.
 *
 * Why this works without real concurrency:
 *   In production, lockForUpdate() blocks the second transaction until the first
 *   commits, then re-reads status='paid' and returns early. Here, the second
 *   sequential call hits the same guard (status already 'paid') — same code path.
 */
class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /** Post a settlement webhook payload; mock always passes signature check. */
    private function postSettlementWebhook(string $orderNumber, string $paymentType = 'gopay'): \Illuminate\Testing\TestResponse
    {
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

    /** Bind a MidtransService mock that always returns true for verifySignature. */
    private function mockSignatureValid(): void
    {
        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);
    }

    // ── Test 1: product-level stock (no variant) ──────────────────────────────

    /**
     * Sending the same settlement webhook twice must not double-process the
     * order for a product without variants.
     *
     * Initial stock = 10 (reservation made at checkout — settlement never
     * touches stock under the reserve-at-checkout model).
     * After both webhooks: stock still = 10, order = 'paid' once.
     */
    public function test_duplicate_webhook_settlement_does_not_double_decrement_product_stock(): void
    {
        $this->mockSignatureValid();

        $user     = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting-idem', 'is_active' => true]);
        $product  = Product::create([
            'category_id' => $category->id,
            'name'        => 'Anting Perak',
            'slug'        => 'anting-perak-idem',
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 5,
            'is_active'   => true,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081200000001',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Kecamatan A',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Test Idempotency No. 1',
        ]);

        $order = Order::create([
            'order_number' => 'ELCRAFT-IDEM-001',
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => 'pending',
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
            'status'            => 'pending',
        ]);

        // Act: same webhook twice
        $this->postSettlementWebhook('ELCRAFT-IDEM-001')->assertOk();
        $this->postSettlementWebhook('ELCRAFT-IDEM-001')->assertOk();

        // Reservation model: settlement never touches stock
        $product->refresh();
        $this->assertSame(10, $product->stock,
            'Stock must remain untouched by settlement (reserved at checkout)');

        // Order paid
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);

        // Exactly ONE shipment
        $shipmentCount = Shipment::where('order_id', $order->id)->count();
        $this->assertSame(1, $shipmentCount,
            "Exactly one Shipment should exist; found {$shipmentCount}");

        // Payment success
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
    }

    // ── Test 2: variant-level stock ───────────────────────────────────────────

    /**
     * Sending the same settlement webhook twice must not double-process the
     * order for a product WITH a variant.
     *
     * Variant stock = 10 (reserved at checkout). After both webhooks:
     * variant stock still = 10, exactly one shipment/usage recorded.
     */
    public function test_duplicate_webhook_settlement_does_not_double_decrement_variant_stock(): void
    {
        $this->mockSignatureValid();

        $user     = User::factory()->create();
        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang-idem', 'is_active' => true]);
        $product  = Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Emas Varian',
            'slug'        => 'gelang-emas-varian-idem',
            'price'       => 80000,
            'stock'       => 0,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $variant = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_name' => 'Ukuran S',
            'variant_type' => 'Ukuran',
            'stock'        => 10,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'John Smith',
            'phone'          => '081200000002',
            'province'       => 'DKI Jakarta',
            'city'           => 'Jakarta Pusat',
            'district'       => 'Gambir',
            'postal_code'    => '10110',
            'full_address'   => 'Jl. Variant No. 2',
        ]);

        $order = Order::create([
            'order_number' => 'ELCRAFT-IDEM-002',
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 240000,
            'total_amount' => 240000,
            'status'       => 'pending',
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'variant_id'   => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->variant_name,
            'price'        => 80000,
            'quantity'     => 3,
            'subtotal'     => 240000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 240000,
            'status'            => 'pending',
        ]);

        // Act: same webhook twice
        $this->postSettlementWebhook('ELCRAFT-IDEM-002')->assertOk();
        $this->postSettlementWebhook('ELCRAFT-IDEM-002')->assertOk();

        // Reservation model: settlement never touches variant stock
        $variant->refresh();
        $this->assertSame(10, $variant->stock,
            'Variant stock must remain untouched by settlement (reserved at checkout)');

        // Exactly ONE shipment
        $this->assertSame(1, Shipment::where('order_id', $order->id)->count(),
            'Exactly one Shipment should exist after two identical webhook calls');
    }

    // ── Test 3: voucher idempotency ───────────────────────────────────────────

    /**
     * When an order has a voucher, sending the same settlement webhook twice
     * must result in exactly ONE VoucherUsage row and used_count incremented by 1.
     */
    public function test_duplicate_webhook_settlement_does_not_double_record_voucher_usage(): void
    {
        $this->mockSignatureValid();

        $user     = User::factory()->create();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung-idem', 'is_active' => true]);
        $product  = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Berlian',
            'slug'        => 'kalung-berlian-idem',
            'price'       => 200000,
            'stock'       => 20,
            'weight'      => 15,
            'is_active'   => true,
        ]);

        $voucher = Voucher::create([
            'code'           => 'IDEM10',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'min_purchase'   => 0,
            'max_discount'   => null,
            'quota'          => 100,
            'used_count'     => 0,
            'start_date'     => now()->subDay(),
            'end_date'       => now()->addDay(),
            'is_active'      => true,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'Siti Rahayu',
            'phone'          => '081200000003',
            'province'       => 'Jawa Tengah',
            'city'           => 'Semarang',
            'district'       => 'Semarang Tengah',
            'postal_code'    => '50111',
            'full_address'   => 'Jl. Voucher No. 3',
        ]);

        $order = Order::create([
            'order_number'    => 'ELCRAFT-IDEM-003',
            'user_id'         => $user->id,
            'address_id'      => $address->id,
            'voucher_id'      => $voucher->id,
            'subtotal'        => 200000,
            'voucher_discount' => 20000,
            'total_amount'    => 180000,
            'status'          => 'pending',
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 200000,
            'quantity'     => 1,
            'subtotal'     => 200000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 180000,
            'status'            => 'pending',
        ]);

        // Act: same webhook twice
        $this->postSettlementWebhook('ELCRAFT-IDEM-003')->assertOk();
        $this->postSettlementWebhook('ELCRAFT-IDEM-003')->assertOk();

        // Exactly ONE VoucherUsage row
        $usageCount = VoucherUsage::where('order_id', $order->id)->count();
        $this->assertSame(1, $usageCount,
            "VoucherUsage should have exactly 1 row; found {$usageCount}");

        // used_count incremented by exactly 1
        $voucher->refresh();
        $this->assertSame(1, $voucher->used_count,
            'Voucher used_count should be 1, not 2');

        // Reservation model: settlement never touches product stock
        $product->refresh();
        $this->assertSame(20, $product->stock,
            'Product stock must remain untouched by settlement (reserved at checkout)');

        // Order paid
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }
}
