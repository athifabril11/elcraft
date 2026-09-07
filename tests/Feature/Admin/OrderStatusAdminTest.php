<?php

namespace Tests\Feature\Admin;

use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OrderStatusAdminTest
 *
 * Tests admin access control and status-advance functionality for the minimal
 * admin orders module. Covers:
 *  - Guest redirected to login on both routes
 *  - Non-admin gets 403 on both routes
 *  - Admin can view the orders index and see orders listed
 *  - Admin can advance a paid order to diproses
 *  - Admin can advance a diproses order to dikirim
 *  - Admin CANNOT advance a pending order
 *  - Admin CANNOT advance a dikirim order further
 *  - Admin CANNOT advance a selesai order
 *  - Admin CANNOT advance a dibatalkan order
 */
class OrderStatusAdminTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function regularUser(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    /**
     * Create a minimal order with the given status.
     * Matches the Order::create pattern used throughout the existing test suite.
     */
    private function makeOrder(User $user, string $status = 'pending'): Order
    {
        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Test No. 1',
        ]);

        return Order::create([
            'order_number' => 'ORD-ADMINTEST-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => $status,
        ]);
    }

    // ── Access Control: Index ────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_orders_index(): void
    {
        $this->get('/admin/orders')
             ->assertRedirect('/login');
    }

    public function test_non_admin_gets_403_on_orders_index(): void
    {
        $this->actingAs($this->regularUser())
             ->get('/admin/orders')
             ->assertForbidden();
    }

    // ── Access Control: Advance Status ───────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_advance_status(): void
    {
        $user  = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->makeOrder($user, 'paid');

        $this->patch("/admin/orders/{$order->id}/advance-status")
             ->assertRedirect('/login');
    }

    public function test_non_admin_gets_403_on_advance_status(): void
    {
        $user  = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->makeOrder($user, 'paid');

        $this->actingAs($this->regularUser())
             ->patch("/admin/orders/{$order->id}/advance-status")
             ->assertForbidden();
    }

    // ── View ─────────────────────────────────────────────────────────────────

    public function test_admin_can_view_orders_index(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $order    = $this->makeOrder($customer, 'paid');

        $this->actingAs($this->adminUser())
             ->get('/admin/orders')
             ->assertOk()
             ->assertViewIs('admin.orders.index')
             ->assertViewHas('orders')
             ->assertSee($order->order_number);
    }

    public function test_orders_index_shows_all_orders(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $o1 = $this->makeOrder($customer, 'paid');
        $o2 = $this->makeOrder($customer, 'diproses');
        $o3 = $this->makeOrder($customer, 'pending');

        $this->actingAs($this->adminUser())
             ->get('/admin/orders')
             ->assertOk()
             ->assertSee($o1->order_number)
             ->assertSee($o2->order_number)
             ->assertSee($o3->order_number);
    }

    // ── Advance: paid → diproses ──────────────────────────────────────────────

    public function test_admin_can_advance_paid_order_to_diproses(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $order    = $this->makeOrder($customer, 'paid');

        $this->actingAs($this->adminUser())
             ->patch("/admin/orders/{$order->id}/advance-status")
             ->assertRedirect(route('admin.orders.index'))
             ->assertSessionHas('success');

        $this->assertEquals('diproses', $order->fresh()->status);
    }

    // ── Advance: diproses → dikirim ───────────────────────────────────────────

    public function test_admin_can_advance_diproses_order_to_dikirim(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $order    = $this->makeOrder($customer, 'diproses');

        $this->actingAs($this->adminUser())
             ->patch("/admin/orders/{$order->id}/advance-status")
             ->assertRedirect(route('admin.orders.index'))
             ->assertSessionHas('success');

        $this->assertEquals('dikirim', $order->fresh()->status);
    }

    // ── Rejected transitions ──────────────────────────────────────────────────

    public function test_admin_cannot_advance_pending_order(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $order    = $this->makeOrder($customer, 'pending');

        $this->actingAs($this->adminUser())
             ->patch("/admin/orders/{$order->id}/advance-status")
             ->assertRedirect(route('admin.orders.index'))
             ->assertSessionHas('error');

        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_admin_cannot_advance_dikirim_order_further(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $order    = $this->makeOrder($customer, 'dikirim');

        $this->actingAs($this->adminUser())
             ->patch("/admin/orders/{$order->id}/advance-status")
             ->assertRedirect(route('admin.orders.index'))
             ->assertSessionHas('error');

        $this->assertEquals('dikirim', $order->fresh()->status);
    }

    public function test_admin_cannot_advance_selesai_order(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $order    = $this->makeOrder($customer, 'selesai');

        $this->actingAs($this->adminUser())
             ->patch("/admin/orders/{$order->id}/advance-status")
             ->assertRedirect(route('admin.orders.index'))
             ->assertSessionHas('error');

        $this->assertEquals('selesai', $order->fresh()->status);
    }

    public function test_admin_cannot_advance_dibatalkan_order(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $order    = $this->makeOrder($customer, 'dibatalkan');

        $this->actingAs($this->adminUser())
             ->patch("/admin/orders/{$order->id}/advance-status")
             ->assertRedirect(route('admin.orders.index'))
             ->assertSessionHas('error');

        $this->assertEquals('dibatalkan', $order->fresh()->status);
    }

    public function test_orders_index_displays_payment_method_and_courier(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->makeOrder($customer, 'paid');
        $order->update([
            'courier' => 'jne',
            'courier_service' => 'REG',
            'shipping_cost' => 18000,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'midtrans_order_id' => 'MID-' . uniqid(),
            'payment_method' => 'bca_va',
            'amount' => $order->total_amount,
            'status' => 'success',
        ]);

        $this->actingAs($this->adminUser())
             ->get(route('admin.orders.index'))
             ->assertOk()
             ->assertSee('JNE (REG)')
             ->assertSee('BCA Virtual Account');
    }
}
