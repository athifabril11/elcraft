<?php

namespace Tests\Feature\Admin;

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customerUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'              => 'customer',
            'email_verified_at' => now(),
            'is_active'         => true,
        ], $attributes));
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
        $response = $this->get(route('admin.customers.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_customer_management(): void
    {
        $customer = $this->customerUser();
        $response = $this->actingAs($customer)->get(route('admin.customers.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_view_customers_list(): void
    {
        $admin = $this->adminUser();
        $customerA = $this->customerUser(['name' => 'Siti Khadijah', 'email' => 'siti@example.com']);
        $customerB = $this->customerUser(['name' => 'Dewi Lestari', 'email' => 'dewi@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.customers.index'));

        $response->assertOk();
        $response->assertViewIs('admin.customers.index');
        $response->assertSee('Siti Khadijah');
        $response->assertSee('Dewi Lestari');
        $response->assertSee('Total: 2 akun');
    }

    public function test_admin_can_search_customers_by_name_or_email(): void
    {
        $admin = $this->adminUser();
        $this->customerUser(['name' => 'Aisyah Putri', 'email' => 'aisyah@example.com']);
        $this->customerUser(['name' => 'Rina Nose', 'email' => 'rina@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.customers.index', ['q' => 'Aisyah']));

        $response->assertOk();
        $response->assertSee('Aisyah Putri');
        $response->assertDontSee('Rina Nose');
    }

    public function test_admin_can_view_customer_detail_and_orders(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser(['name' => 'Nadia Saphira', 'phone' => '081298765432']);
        $address = $this->createAddress($customer);

        $order = Order::create([
            'order_number' => 'ORD-NADIA-001',
            'user_id'      => $customer->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 150000,
            'status'       => 'selesai',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer->id));

        $response->assertOk();
        $response->assertViewIs('admin.customers.show');
        $response->assertSee('Nadia Saphira');
        $response->assertSee('081298765432');
        $response->assertSee('ORD-NADIA-001');
        $response->assertSee('150.000');
    }

    public function test_admin_can_toggle_customer_active_status(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser(['name' => 'Budi Susanto', 'is_active' => true]);

        $response = $this->actingAs($admin)->patch(route('admin.customers.toggle-status', $customer->id));

        $response->assertRedirect();
        $this->assertFalse($customer->fresh()->is_active);

        // Toggle back to active
        $response2 = $this->actingAs($admin)->patch(route('admin.customers.toggle-status', $customer->id));
        $response2->assertRedirect();
        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_deactivated_customer_cannot_login(): void
    {
        $customer = $this->customerUser([
            'email'     => 'inactive@example.com',
            'password'  => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email'    => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors();
    }
}
