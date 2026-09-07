<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\ShippingDiscountRule;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAdminTest extends TestCase
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

    public function test_guest_is_redirected_to_login_on_admin_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_non_admin_gets_403_on_admin_dashboard(): void
    {
        $this->actingAs($this->customer())
             ->get('/admin/dashboard')
             ->assertForbidden();
    }

    public function test_admin_can_view_dashboard_with_metrics(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        // Create category and product
        $cat = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $prd = Product::create([
            'category_id' => $cat->id,
            'name' => 'Kalung Mutiara',
            'slug' => 'kalung-mutiara',
            'price' => 150000,
            'stock' => 2, // low stock <= 3
            'weight' => 100,
            'is_active' => true,
        ]);

        // Create address and order
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
            'order_number' => 'ORD-TEST-1',
            'user_id' => $customer->id,
            'address_id' => $addr->id,
            'subtotal' => 150000,
            'total_amount' => 150000,
            'status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $prd->id,
            'product_name' => $prd->name,
            'price' => 150000,
            'quantity' => 1,
            'subtotal' => 150000,
        ]);

        // Create return request
        ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'return_number' => 'RET-TEST-1',
            'resolution_type' => 'refund',
            'reason' => 'Barang rusak saat diterima',
            'status' => 'diajukan',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertViewHas([
                'totalRevenue',
                'ordersNeedingAttention',
                'pendingReturnsCount',
                'lowStockCount',
                'totalCustomers',
                'totalOrders',
            ]);

        $response->assertSee('Ringkasan Toko el Craft');
        $response->assertSee('Kalung Mutiara');
        $response->assertSee('ORD-TEST-1');
    }
}
