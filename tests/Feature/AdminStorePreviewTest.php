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
}
