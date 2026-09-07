<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now(), 'name' => 'Budi Santoso', 'email' => 'budi@gmail.com']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/global-search?q=test')->assertRedirect('/login');
    }

    public function test_customer_gets_403(): void
    {
        $this->actingAs($this->customer())
            ->get('/admin/global-search?q=test')
            ->assertForbidden();
    }

    public function test_short_query_returns_empty_results(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson('/admin/global-search?q=a')
            ->assertOk()
            ->json();

        $this->assertEquals(0, $response['total']);
    }

    public function test_admin_can_search_products_orders_customers_and_menus(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        // 1. Create product
        $cat = Category::create(['name' => 'Aksesoris', 'slug' => 'aksesoris', 'is_active' => true]);
        $prod = Product::create([
            'category_id' => $cat->id,
            'name' => 'Gelang Perak Mutiara',
            'slug' => 'gelang-perak-mutiara',
            'price' => 250000,
            'stock' => 5,
            'weight' => 50,
            'is_active' => true,
        ]);

        // 2. Create order
        $addr = $customer->addresses()->create([
            'label' => 'Rumah',
            'recipient_name' => $customer->name,
            'phone' => '081234567890',
            'province' => 'Jawa Timur',
            'city' => 'Jember',
            'district' => 'Sumbersari',
            'postal_code' => '68121',
            'full_address' => 'Jl. Mutiara No. 5',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-MUTIARA-01',
            'user_id' => $customer->id,
            'address_id' => $addr->id,
            'subtotal' => 250000,
            'total_amount' => 250000,
            'status' => 'paid',
        ]);

        // Search for "mutiara" -> Should match product and order!
        $response = $this->actingAs($admin)
            ->getJson('/admin/global-search?q=mutiara')
            ->assertOk()
            ->json();

        $this->assertGreaterThan(0, $response['total']);
        $this->assertNotEmpty($response['results']['products']);
        $this->assertNotEmpty($response['results']['orders']);
        $this->assertEquals('Gelang Perak Mutiara', $response['results']['products'][0]['title']);
        $this->assertStringContainsString('/admin/products', $response['results']['products'][0]['url']);
        $this->assertNotEmpty($response['results']['products'][0]['store_url']);
        $this->assertEquals('#ORD-MUTIARA-01', $response['results']['orders'][0]['title']);

        // Search for "budi" -> Should match customer!
        $custResponse = $this->actingAs($admin)
            ->getJson('/admin/global-search?q=budi')
            ->assertOk()
            ->json();

        $this->assertNotEmpty($custResponse['results']['customers']);
        $this->assertEquals('Budi Santoso', $custResponse['results']['customers'][0]['title']);

        // Search for "voucher" -> Should match menu navigation!
        $menuResponse = $this->actingAs($admin)
            ->getJson('/admin/global-search?q=voucher')
            ->assertOk()
            ->json();

        $this->assertNotEmpty($menuResponse['results']['menus']);
        $this->assertEquals('Kelola Voucher Diskon', $menuResponse['results']['menus'][0]['title']);
    }
}
