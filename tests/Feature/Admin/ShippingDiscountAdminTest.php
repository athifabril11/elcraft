<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingDiscountRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ShippingDiscountAdminTest
 *
 * Tests admin access control and CRUD operations for shipping discount rules.
 * Covers:
 *  - Admin can view the index page
 *  - Admin can create province-scoped free-shipping rule
 *  - Admin can create city-scoped fixed discount rule
 *  - Admin can toggle rule active state
 *  - Admin can delete a rule
 *  - Non-admin (regular user) receives 403 on all admin routes
 *  - Guest is redirected to login on all admin routes
 */
class ShippingDiscountAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function regularUser(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    // ── Access Control ──────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_discount_rules_index(): void
    {
        $this->get('/admin/settings/shipping-discounts')
             ->assertRedirect('/login');
    }

    public function test_regular_user_gets_403_on_discount_rules_index(): void
    {
        $this->actingAs($this->regularUser())
             ->get('/admin/settings/shipping-discounts')
             ->assertForbidden();
    }

    public function test_admin_can_view_discount_rules_index(): void
    {
        $this->actingAs($this->adminUser())
             ->get('/admin/settings/shipping-discounts')
             ->assertOk()
             ->assertViewIs('admin.settings.shipping-discounts.index');
    }

    // ── Create ──────────────────────────────────────────────────────────────

    public function test_admin_can_create_province_scoped_free_shipping_rule(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post('/admin/settings/shipping-discounts', [
            'scope'         => 'province',
            'scope_id'      => 9,
            'scope_name'    => 'Jawa Barat',
            'discount_type' => 'free',
            'description'   => 'Gratis ongkir Jawa Barat',
        ]);

        $response->assertRedirect(route('admin.settings.shipping-discounts.index'));
        $this->assertDatabaseHas('shipping_discount_rules', [
            'scope'         => 'province',
            'scope_id'      => 9,
            'discount_type' => 'free',
            'is_active'     => true,
        ]);
    }

    public function test_admin_can_create_city_scoped_fixed_discount_rule(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post('/admin/settings/shipping-discounts', [
            'scope'           => 'city',
            'scope_id'        => 23,
            'scope_name'      => 'Bandung',
            'discount_type'   => 'fixed',
            'discount_amount' => 10000,
            'description'     => 'Diskon ongkir Bandung',
        ]);

        $response->assertRedirect(route('admin.settings.shipping-discounts.index'));
        $this->assertDatabaseHas('shipping_discount_rules', [
            'scope'           => 'city',
            'scope_id'        => 23,
            'discount_type'   => 'fixed',
            'discount_amount' => 10000,
        ]);
    }

    public function test_non_admin_cannot_create_discount_rule(): void
    {
        $this->actingAs($this->regularUser())
             ->post('/admin/settings/shipping-discounts', [
                 'scope'         => 'province',
                 'scope_id'      => 9,
                 'scope_name'    => 'Jawa Barat',
                 'discount_type' => 'free',
             ])
             ->assertForbidden();

        $this->assertDatabaseCount('shipping_discount_rules', 0);
    }

    // ── Toggle ──────────────────────────────────────────────────────────────

    public function test_admin_can_toggle_rule_active_state(): void
    {
        $rule = ShippingDiscountRule::create([
            'scope'         => 'city',
            'scope_id'      => 23,
            'scope_name'    => 'Bandung',
            'discount_type' => 'free',
            'is_active'     => true,
        ]);

        $this->actingAs($this->adminUser())
             ->post("/admin/settings/shipping-discounts/{$rule->id}/toggle")
             ->assertRedirect(route('admin.settings.shipping-discounts.index'));

        $this->assertDatabaseHas('shipping_discount_rules', [
            'id'        => $rule->id,
            'is_active' => false,
        ]);

        // Toggle back
        $this->actingAs($this->adminUser())
             ->post("/admin/settings/shipping-discounts/{$rule->id}/toggle");

        $this->assertDatabaseHas('shipping_discount_rules', [
            'id'        => $rule->id,
            'is_active' => true,
        ]);
    }

    // ── Delete ──────────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_rule(): void
    {
        $rule = ShippingDiscountRule::create([
            'scope'         => 'city',
            'scope_id'      => 23,
            'scope_name'    => 'Bandung',
            'discount_type' => 'free',
            'is_active'     => true,
        ]);

        $this->actingAs($this->adminUser())
             ->delete("/admin/settings/shipping-discounts/{$rule->id}")
             ->assertRedirect(route('admin.settings.shipping-discounts.index'));

        $this->assertDatabaseMissing('shipping_discount_rules', ['id' => $rule->id]);
    }

    public function test_non_admin_cannot_delete_a_rule(): void
    {
        $rule = ShippingDiscountRule::create([
            'scope'         => 'city',
            'scope_id'      => 23,
            'scope_name'    => 'Bandung',
            'discount_type' => 'free',
            'is_active'     => true,
        ]);

        $this->actingAs($this->regularUser())
             ->delete("/admin/settings/shipping-discounts/{$rule->id}")
             ->assertForbidden();

        $this->assertDatabaseHas('shipping_discount_rules', ['id' => $rule->id]);
    }
}
