<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStorePreviewTest extends TestCase
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

    public function test_admin_is_blocked_from_viewing_cart(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/cart')
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_admin_is_blocked_from_adding_product_to_cart(): void
    {
        $admin = $this->admin();
        $cat = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Kalung Berlian',
            'slug' => 'kalung-berlian',
            'price' => 500000,
            'stock' => 10,
            'weight' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/cart/add', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertForbidden();
        $response->assertJson([
            'success' => false,
            'status' => 'error',
        ]);
    }

    public function test_admin_is_blocked_from_checkout(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/checkout')
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_customer_can_access_cart_and_add_products(): void
    {
        $customer = $this->customer();
        $cat = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Kalung Emas',
            'slug' => 'kalung-emas',
            'price' => 300000,
            'stock' => 10,
            'weight' => 50,
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->get('/cart')
            ->assertOk();

        $response = $this->actingAs($customer)
            ->postJson('/cart/add', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_admin_can_preview_inactive_product_while_customer_gets_404(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $cat = Category::create(['name' => 'Bros', 'slug' => 'bros', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Bros Draft Belum Rilis',
            'slug' => 'bros-draft-belum-rilis',
            'price' => 150000,
            'stock' => 5,
            'weight' => 30,
            'is_active' => false,
        ]);

        // Customer gets 404
        $this->actingAs($customer)
            ->get(route('products.show', $product->slug))
            ->assertNotFound();

        // Admin can preview
        $this->actingAs($admin)
            ->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Bros Draft Belum Rilis');
    }

    public function test_admin_sees_preview_banner_and_dashboard_button_on_storefront(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('Mode Pratinjau Admin');
        $response->assertSee('Transaksi belanja dinonaktifkan untuk akun ini.');
        $response->assertSee('Kembali ke Dashboard Admin');
        $response->assertSee(route('admin.dashboard'));
    }

    public function test_customer_does_not_see_admin_preview_banner(): void
    {
        $customer = $this->customer();

        $response = $this->actingAs($customer)->get('/');

        $response->assertOk();
        $response->assertDontSee('Mode Pratinjau Admin');
        $response->assertDontSee('Transaksi belanja dinonaktifkan untuk akun ini.');
        $response->assertDontSee('Kembali ke Dashboard Admin');
    }

    public function test_customer_cannot_add_inactive_product_to_cart(): void
    {
        $customer = $this->customer();
        $cat = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Produk Tidak Aktif',
            'slug' => 'produk-tidak-aktif',
            'price' => 100000,
            'stock' => 10,
            'weight' => 50,
            'is_active' => false,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/cart/add', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Produk tidak tersedia.',
        ]);
    }

    public function test_customer_cannot_add_inactive_variant_to_cart(): void
    {
        $customer = $this->customer();
        $cat = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Produk Aktif',
            'slug' => 'produk-aktif',
            'price' => 100000,
            'stock' => 10,
            'weight' => 50,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'variant_name' => 'Varian Tidak Aktif',
            'price' => 100000,
            'stock' => 10,
            'is_active' => false,
            'variant_type' => 'size', // required NOT NULL column
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/cart/add', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Produk tidak tersedia.',
        ]);
    }

    public function test_customer_cannot_buy_now_inactive_product(): void
    {
        $customer = $this->customer();
        $cat = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Produk Tidak Aktif',
            'slug' => 'produk-tidak-aktif-2',
            'price' => 100000,
            'stock' => 10,
            'weight' => 50,
            'is_active' => false,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('/cart/buy-now', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Produk tidak tersedia.',
        ]);
    }

    public function test_customer_cannot_update_cart_with_inactive_product(): void
    {
        $customer = $this->customer();
        $cat = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Produk Awal Aktif',
            'slug' => 'produk-awal-aktif',
            'price' => 100000,
            'stock' => 10,
            'weight' => 50,
            'is_active' => true,
        ]);
        $inactiveProduct = Product::create([
            'category_id' => $cat->id,
            'name' => 'Produk Jadi Tidak Aktif',
            'slug' => 'produk-jadi-tidak-aktif',
            'price' => 100000,
            'stock' => 10,
            'weight' => 50,
            'is_active' => false,
        ]);

        // First add active product to cart
        $cart = $customer->getOrCreateCart();
        $item = $cart->allItems()->create([
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // Deactivate the product
        $product->update(['is_active' => false]);

        // Try to update quantity - should fail
        $response = $this->actingAs($customer)
            ->postJson('/cart/update', [
                'item_id' => $item->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Produk tidak tersedia.',
        ]);
    }
}