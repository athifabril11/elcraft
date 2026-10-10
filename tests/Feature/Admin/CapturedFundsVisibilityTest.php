<?php

namespace Tests\Feature\Admin;

use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapturedFundsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function createCustomer(): User
    {
        return User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
    }

    private function createOrderWithPayment(User $user, string $orderStatus, string $paymentStatus, string $orderNumber): Order
    {
        $address = $user->addresses()->create([
            'label'          => 'Rumah',
            'recipient_name' => $user->name,
            'phone'          => '081234567890',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Karimata No. 1',
        ]);

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => $orderStatus,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $orderNumber,
            'payment_type'      => 'bank_transfer',
            'payment_method'    => 'bca_va',
            'status'            => $paymentStatus,
            'amount'            => 100000,
        ]);

        return $order;
    }

    public function test_orders_index_filters_captured_funds_correctly(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        // (a) dibatalkan + payment success (stuck captured funds)
        $capturedOrder = $this->createOrderWithPayment($customer, 'dibatalkan', 'success', 'ORD-CAPTURED-STUCK');

        // (b) dibatalkan + payment failed/expired (normal cancelled order)
        $cancelledUnpaid = $this->createOrderWithPayment($customer, 'dibatalkan', 'expired', 'ORD-CANCELLED-NORMAL');

        // (c) paid + payment success (normal paid order)
        $paidOrder = $this->createOrderWithPayment($customer, 'paid', 'success', 'ORD-PAID-NORMAL');

        $response = $this->actingAs($admin)
            ->get(route('admin.orders.index', ['payment_captured' => 1]));

        $response->assertOk();
        $response->assertSee('ORD-CAPTURED-STUCK');
        $response->assertDontSee('ORD-CANCELLED-NORMAL');
        $response->assertDontSee('ORD-PAID-NORMAL');
        $response->assertSee('Dana Tertangkap');
    }

    public function test_orders_index_captured_filter_with_search(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $this->createOrderWithPayment($customer, 'dibatalkan', 'success', 'ORD-CAPTURED-ALPHA');
        $this->createOrderWithPayment($customer, 'dibatalkan', 'success', 'ORD-CAPTURED-BETA');

        $response = $this->actingAs($admin)
            ->get(route('admin.orders.index', ['payment_captured' => 1, 'q' => 'ALPHA']));

        $response->assertOk();
        $response->assertSee('ORD-CAPTURED-ALPHA');
        $response->assertDontSee('ORD-CAPTURED-BETA');
    }

    public function test_dashboard_shows_captured_funds_banner_when_present(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        // Create captured order
        $this->createOrderWithPayment($customer, 'dibatalkan', 'success', 'ORD-CAPTURED-1');

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Dana Tertangkap');
        $response->assertSee(route('admin.orders.index', ['payment_captured' => 1]));
    }

    public function test_dashboard_does_not_show_captured_funds_banner_when_none(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        // Only normal orders
        $this->createOrderWithPayment($customer, 'dibatalkan', 'expired', 'ORD-CANCELLED-NORMAL');
        $this->createOrderWithPayment($customer, 'paid', 'success', 'ORD-PAID-NORMAL');

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('admin.orders.index', ['payment_captured' => 1]));
    }

    public function test_captured_funds_do_not_count_towards_total_revenue(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        // Selesai order (50,000)
        $completedOrder = $this->createOrderWithPayment($customer, 'selesai', 'success', 'ORD-SELESAI');
        $completedOrder->update(['total_amount' => 50000]);

        // Dibatalkan order with captured payment (100,000)
        $this->createOrderWithPayment($customer, 'dibatalkan', 'success', 'ORD-CAPTURED-STUCK');

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalRevenue', 50000.0);
        $response->assertViewHas('capturedStuckCount', 1);
    }
}
