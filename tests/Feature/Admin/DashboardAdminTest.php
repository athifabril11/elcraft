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
        $response->assertViewHas('chartRevenue');
        $response->assertViewHas('heroSparkline');
        $response->assertViewHas('period', 'this_month');
    }

    public function test_admin_can_filter_dashboard_by_period(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

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

        // Order today
        Order::create([
            'order_number' => 'ORD-TODAY',
            'user_id' => $customer->id,
            'address_id' => $addr->id,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'paid',
            'created_at' => now(),
        ]);

        // Order 45 days ago
        $oldOrder = Order::create([
            'order_number' => 'ORD-OLD',
            'user_id' => $customer->id,
            'address_id' => $addr->id,
            'subtotal' => 200000,
            'total_amount' => 200000,
            'status' => 'paid',
        ]);
        $oldOrder->timestamps = false;
        $oldOrder->created_at = now()->subDays(45);
        $oldOrder->save();

        // 1. Period = today
        $responseToday = $this->actingAs($admin)->get('/admin/dashboard?period=today');
        $responseToday->assertOk();
        $responseToday->assertViewHas('period', 'today');
        $orderCountsToday = $responseToday->viewData('orderCountsByStatus');
        $this->assertEquals(1, $orderCountsToday['paid']);
        $this->assertCount(7, $responseToday->viewData('heroSparkline'));

        // 2. Period = all
        $responseAll = $this->actingAs($admin)->get('/admin/dashboard?period=all');
        $responseAll->assertOk();
        $responseAll->assertViewHas('period', 'all');
        $orderCountsAll = $responseAll->viewData('orderCountsByStatus');
        $this->assertEquals(2, $orderCountsAll['paid']);
        $this->assertCount(7, $responseAll->viewData('heroSparkline'));

        // Check filter pill renders
        $responseAll->assertSee('Semua');
        $responseAll->assertSee('Tren Penjualan');
        $responseAll->assertSee('(Semua Waktu)');
    }

    public function test_dashboard_deduplication_of_stock_and_customer_cards(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $cat = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        Product::create([
            'category_id' => $cat->id,
            'name' => 'Produk Kritis Test',
            'slug' => 'produk-kritis-test',
            'price' => 50000,
            'stock' => 1,
            'weight' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertOk();

        // Top dismissible alert banner shows low stock product details and close button
        $response->assertSee('Peringatan Stok Menipis');
        $response->assertSee('Produk Kritis Test');
        $response->assertSee('Sisa 1');
        $response->assertSee('Tutup Notifikasi');

        // Row 1 Card 3 shows Stok Kritis card
        $response->assertSee('Stok Kritis');
        $response->assertSee('Produk Stok Menipis');

        // Row 5 shows Recent Reviews widget alongside Top Products
        $response->assertSee('Ulasan Terbaru');

        // Row 2 Pelanggan Terdaftar card does not show avatar preview row
        $response->assertDontSee('Pelanggan terbaru');
    }
}


