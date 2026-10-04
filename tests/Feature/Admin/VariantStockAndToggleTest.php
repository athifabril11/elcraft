<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VariantStockAndToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function mockSignatureValid(): void
    {
        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);
    }

    private function postSettlementWebhook(string $orderNumber): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $orderNumber,
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'signature_key'      => 'dummy_signature',
            'payment_type'       => 'bank_transfer',
            'fraud_status'       => 'accept',
        ]);
    }

    /**
     * 1. Webhook markAsPaid() correctly syncs parent product.stock to the sum of its variants'
     * stock after decrementing a variant.
     */
    public function test_webhook_mark_as_paid_syncs_parent_product_stock_after_variant_decrement(): void
    {
        $this->mockSignatureValid();

        $cat = Category::create(['name' => 'Cincin', 'slug' => 'cincin']);
        $product = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Cincin Emas',
            'slug'        => 'cincin-emas',
            'price'       => 100000,
            'stock'       => 20, // initial aggregate: 8 + 12
            'weight'      => 50,
            'is_active'   => true,
        ]);

        $v1 = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Size 16',
            'stock'        => 8,
            'is_active'    => true,
        ]);

        $v2 = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Size 18',
            'stock'        => 12,
            'is_active'    => true,
        ]);

        $customer = $this->customer();
        $address = $customer->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081200000001',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Kalimantan No. 1',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-VAR-001',
            'user_id'      => $customer->id,
            'address_id'   => $address->id,
            'status'       => 'pending',
            'subtotal'     => 100000,
            'shipping_cost'=> 10000,
            'total_amount' => 110000,
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'variant_id'   => $v1->id,
            'product_name' => $product->name,
            'variant_name' => $v1->variant_name,
            'price'        => 100000,
            'quantity'     => 3,
            'subtotal'     => 300000,
        ]);

        $res = $this->postSettlementWebhook($order->order_number);
        $res->assertOk();

        // v1 stock decreased by 3 (8 - 3 = 5)
        $this->assertEquals(5, $v1->fresh()->stock);
        // v2 untouched (12)
        $this->assertEquals(12, $v2->fresh()->stock);
        // parent product stock synced to sum: 5 + 12 = 17
        $this->assertEquals(17, $product->fresh()->stock);
    }

    /**
     * 2. Webhook processing an order with multiple items for different variants of the SAME product
     * correctly syncs stock only once with the correct final sum.
     */
    public function test_webhook_with_multiple_variants_of_same_product_syncs_correct_final_sum(): void
    {
        $this->mockSignatureValid();

        $cat = Category::create(['name' => 'Gelang', 'slug' => 'gelang']);
        $product = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Gelang Perak',
            'slug'        => 'gelang-perak',
            'price'       => 75000,
            'stock'       => 25, // initial: 10 + 15
            'weight'      => 40,
            'is_active'   => true,
        ]);

        $v1 = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Silver S',
            'stock'        => 10,
            'is_active'    => true,
        ]);

        $v2 = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Silver M',
            'stock'        => 15,
            'is_active'    => true,
        ]);

        $customer = $this->customer();
        $address = $customer->addresses()->create([
            'label'          => 'Rumah',
            'recipient_name' => 'John Doe',
            'phone'          => '081200000002',
            'province'       => 'Jawa Timur',
            'city'           => 'Surabaya',
            'district'       => 'Gubeng',
            'postal_code'    => '60281',
            'full_address'   => 'Jl. Dharmawangsa No. 2',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-VAR-002',
            'user_id'      => $customer->id,
            'address_id'   => $address->id,
            'status'       => 'pending',
            'subtotal'     => 150000,
            'shipping_cost'=> 10000,
            'total_amount' => 160000,
        ]);

        // Item 1: 4 of Variant 1
        $order->items()->create([
            'product_id'   => $product->id,
            'variant_id'   => $v1->id,
            'product_name' => $product->name,
            'variant_name' => $v1->variant_name,
            'price'        => 75000,
            'quantity'     => 4,
            'subtotal'     => 300000,
        ]);

        // Item 2: 5 of Variant 2
        $order->items()->create([
            'product_id'   => $product->id,
            'variant_id'   => $v2->id,
            'product_name' => $product->name,
            'variant_name' => $v2->variant_name,
            'price'        => 75000,
            'quantity'     => 5,
            'subtotal'     => 375000,
        ]);

        $res = $this->postSettlementWebhook($order->order_number);
        $res->assertOk();

        // v1: 10 - 4 = 6
        $this->assertEquals(6, $v1->fresh()->stock);
        // v2: 15 - 5 = 10
        $this->assertEquals(10, $v2->fresh()->stock);
        // parent: 6 + 10 = 16
        $this->assertEquals(16, $product->fresh()->stock);
    }

    /**
     * 3. Admin can toggle a variant's is_active status via the new AJAX route;
     * verify JSON response and database change.
     */
    public function test_admin_can_toggle_variant_status_via_ajax_route(): void
    {
        $admin = $this->admin();
        $cat = Category::create(['name' => 'Kalung', 'slug' => 'kalung']);
        $product = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Kalung Mutiara',
            'slug'        => 'kalung-mutiara',
            'price'       => 120000,
            'stock'       => 10,
            'weight'      => 30,
            'is_active'   => true,
        ]);

        $variant = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Putih 40cm',
            'stock'        => 10,
            'is_active'    => true,
        ]);

        // Toggle from true -> false
        $res = $this->actingAs($admin)
            ->patchJson(route('admin.products.variants.toggle', [$product, $variant]));

        $res->assertOk()
            ->assertJson([
                'success'   => true,
                'is_active' => false,
            ]);

        $this->assertFalse($variant->fresh()->is_active);

        // Toggle back from false -> true
        $res2 = $this->actingAs($admin)
            ->patchJson(route('admin.products.variants.toggle', [$product, $variant]));

        $res2->assertOk()
            ->assertJson([
                'success'   => true,
                'is_active' => true,
            ]);

        $this->assertTrue($variant->fresh()->is_active);
    }

    /**
     * 4. Toggling a variant that doesn't belong to the given product returns 404.
     */
    public function test_toggling_mismatched_variant_and_product_returns_404(): void
    {
        $admin = $this->admin();
        $cat = Category::create(['name' => 'Anting', 'slug' => 'anting']);

        $product1 = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Anting Perak',
            'slug'        => 'anting-perak',
            'price'       => 50000,
            'stock'       => 5,
            'weight'      => 20,
            'is_active'   => true,
        ]);

        $product2 = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Anting Emas',
            'slug'        => 'anting-emas',
            'price'       => 90000,
            'stock'       => 5,
            'weight'      => 20,
            'is_active'   => true,
        ]);

        $variantOfProduct2 = ProductVariant::create([
            'product_id'   => $product2->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Kuning',
            'stock'        => 5,
            'is_active'    => true,
        ]);

        // Request with product1 but variant of product2
        $res = $this->actingAs($admin)
            ->patchJson(route('admin.products.variants.toggle', [$product1, $variantOfProduct2]));

        $res->assertNotFound();
    }

    /**
     * 5. Non-admin / guest cannot access the variant toggle route.
     */
    public function test_guest_and_non_admin_cannot_toggle_variant(): void
    {
        $cat = Category::create(['name' => 'Bros', 'slug' => 'bros']);
        $product = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Bros Mutiara',
            'slug'        => 'bros-mutiara',
            'price'       => 40000,
            'stock'       => 5,
            'weight'      => 15,
            'is_active'   => true,
        ]);
        $variant = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Gold',
            'stock'        => 5,
            'is_active'    => true,
        ]);

        // Guest
        $this->patchJson(route('admin.products.variants.toggle', [$product, $variant]))
            ->assertUnauthorized();

        // Customer (non-admin)
        $customer = $this->customer();
        $this->actingAs($customer)
            ->patchJson(route('admin.products.variants.toggle', [$product, $variant]))
            ->assertForbidden();
    }

    /**
     * 6. Saving a product via the admin update() form with a variant's is_active checkbox unchecked
     * correctly persists is_active = false for that variant.
     */
    public function test_saving_product_update_with_unchecked_variant_checkbox_persists_inactive(): void
    {
        $admin = $this->admin();
        $cat = Category::create(['name' => 'Aksesoris', 'slug' => 'aksesoris']);
        $product = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Liontin Batu',
            'slug'        => 'liontin-batu',
            'price'       => 85000,
            'stock'       => 10,
            'weight'      => 30,
            'is_active'   => true,
        ]);

        $variant = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Ruby',
            'stock'        => 10,
            'is_active'    => true,
        ]);

        // Submit update with is_active = 0 (simulating unchecked checkbox with hidden 0)
        $res = $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'category_id' => $cat->id,
            'name'        => $product->name,
            'price'       => $product->price,
            'stock'       => $product->stock,
            'weight'      => $product->weight,
            'variants'    => [
                [
                    'id'           => $variant->id,
                    'variant_name' => $variant->variant_name,
                    'stock'        => $variant->stock,
                    'is_active'    => '0',
                ],
            ],
        ]);

        $res->assertRedirect(route('admin.products.index'));
        $this->assertFalse((bool) $variant->fresh()->is_active);
    }

    /**
     * 7. The new dashboard low-stock ranking correctly includes a variant-level entry
     * for a product that has variants.
     */
    public function test_dashboard_low_stock_ranking_includes_variant_level_entry(): void
    {
        $admin = $this->admin();
        $cat = Category::create(['name' => 'Kerajinan', 'slug' => 'kerajinan']);

        // Parent product has healthy aggregate stock = 20, but one variant has critical stock = 0
        $product = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Gelang Manik',
            'slug'        => 'gelang-manik',
            'price'       => 30000,
            'stock'       => 20,
            'weight'      => 25,
            'is_active'   => true,
        ]);

        $criticalVariant = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Hitam',
            'stock'        => 0, // Critical <= 3
            'is_active'    => true,
        ]);

        $healthyVariant = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Opsi',
            'variant_name' => 'Putih',
            'stock'        => 20,
            'is_active'    => true,
        ]);

        $res = $this->actingAs($admin)->get(route('admin.dashboard'));
        $res->assertOk();

        $ranking = $res->viewData('lowStockRanking');
        $criticalCount = $res->viewData('lowStockCriticalCount');

        $this->assertGreaterThanOrEqual(1, $criticalCount);

        // Find the critical variant in the ranking
        $found = collect($ranking)->first(fn($item) =>
            $item['name'] === 'Gelang Manik' &&
            $item['variant_name'] === 'Hitam' &&
            $item['stock'] === 0
        );

        $this->assertNotNull($found, 'Variant with stock=0 should appear in the low-stock ranking');
    }

    /**
     * 8. The dashboard ranking correctly includes non-variant products using their own stock field.
     */
    public function test_dashboard_low_stock_ranking_includes_non_variant_products(): void
    {
        $admin = $this->admin();
        $cat = Category::create(['name' => 'Souvenir', 'slug' => 'souvenir']);

        $nonVariantProduct = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Gantungan Kunci Kayu',
            'slug'        => 'gantungan-kunci-kayu',
            'price'       => 15000,
            'stock'       => 2, // Critical <= 3
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $res = $this->actingAs($admin)->get(route('admin.dashboard'));
        $res->assertOk();

        $ranking = $res->viewData('lowStockRanking');
        $criticalCount = $res->viewData('lowStockCriticalCount');

        $this->assertGreaterThanOrEqual(1, $criticalCount);

        // Find the non-variant product in the ranking
        $found = collect($ranking)->first(fn($item) =>
            $item['name'] === 'Gantungan Kunci Kayu' &&
            $item['variant_name'] === null &&
            $item['stock'] === 2
        );

        $this->assertNotNull($found, 'Non-variant product with stock=2 should appear in the low-stock ranking');
    }
}
