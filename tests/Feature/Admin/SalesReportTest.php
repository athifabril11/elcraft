<?php

namespace Tests\Feature\Admin;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customerUser(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function createAddress(User $user): Address
    {
        return Address::create([
            'user_id'        => $user->id,
            'label'          => 'Rumah',
            'recipient_name' => $user->name,
            'phone'          => '08123456789',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40132',
            'full_address'   => 'Jl. Dago No. 10',
            'is_default'     => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.reports.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_reports(): void
    {
        $customer = $this->customerUser();
        $response = $this->actingAs($customer)->get(route('admin.reports.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_view_reports_and_see_financial_metrics(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser();
        $address = $this->createAddress($customer);

        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Mutiara el Craft',
            'slug'        => 'kalung-mutiara-el-craft',
            'price'       => 150000,
            'stock'       => 20,
            'is_active'   => true,
        ]);

        $order = Order::create([
            'order_number'    => 'ORD-20260907-001',
            'user_id'         => $customer->id,
            'address_id'      => $address->id,
            'subtotal'        => 300000,
            'discount_amount' => 0,
            'voucher_discount'=> 20000,
            'shipping_cost'   => 15000,
            'total_amount'    => 295000,
            'status'          => 'selesai',
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 150000,
            'quantity'     => 2,
            'subtotal'     => 300000,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertViewIs('admin.reports.index');
        $response->assertSee('Laporan &amp; Analitik Penjualan', false);
        $response->assertSee('Kalung Mutiara el Craft');
        $response->assertSee('295.000');
        $response->assertSee('2 pcs');
    }

    public function test_reports_filter_by_period_accurately_scopes_orders(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser();
        $address = $this->createAddress($customer);

        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Rose Gold',
            'slug'        => 'gelang-rose-gold',
            'price'       => 100000,
            'stock'       => 10,
            'is_active'   => true,
        ]);

        // Order today
        $todayOrder = Order::create([
            'order_number' => 'ORD-TODAY-001',
            'user_id'      => $customer->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => 'selesai',
            'created_at'   => Carbon::today()->addHours(2),
        ]);

        OrderItem::create([
            'order_id'     => $todayOrder->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 100000,
            'quantity'     => 1,
            'subtotal'     => 100000,
        ]);

        // Order 40 days ago (outside 30days filter)
        $oldOrder = Order::create([
            'order_number' => 'ORD-OLD-002',
            'user_id'      => $customer->id,
            'address_id'   => $address->id,
            'subtotal'     => 500000,
            'total_amount' => 500000,
            'status'       => 'selesai',
        ]);
        $oldOrder->timestamps = false;
        $oldOrder->created_at = Carbon::now()->subDays(40);
        $oldOrder->save();

        $response = $this->actingAs($admin)->get(route('admin.reports.index', ['period' => '30days']));

        $response->assertOk();
        $response->assertSee('ORD-TODAY-001');
        $response->assertDontSee('ORD-OLD-002');
    }

    public function test_admin_can_export_csv_report(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser();
        $address = $this->createAddress($customer);

        $order = Order::create([
            'order_number' => 'ORD-CSV-12345',
            'user_id'      => $customer->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => 'selesai',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.export'));

        $response->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ORD-CSV-12345', $response->streamedContent());
        $this->assertStringContainsString('No. Pesanan', $response->streamedContent());
    }
}
