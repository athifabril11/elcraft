<?php

namespace Tests\Feature\Admin;

use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OrderAutoCompleteAdminTest
 *
 * Tests admin access control and functionality for the order auto-complete
 * settings page.
 * Covers:
 *  - Guest is redirected to login
 *  - Non-admin gets 403
 *  - Admin can view the page and see the current value
 *  - Admin can update the value
 *  - Validation rejects non-integer, values below 1, and values above 30
 */
class OrderAutoCompleteAdminTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        ShopSetting::updateOrCreate(
            ['key' => 'order_auto_complete_days'],
            ['value' => '7']
        );
    }

    // ── Access Control ──────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_order_auto_complete_page(): void
    {
        $this->get('/admin/settings/order-auto-complete')
             ->assertRedirect('/login');
    }

    public function test_non_admin_gets_403_on_order_auto_complete_page(): void
    {
        $this->actingAs($this->regularUser())
             ->get('/admin/settings/order-auto-complete')
             ->assertForbidden();
    }

    // ── View ────────────────────────────────────────────────────────────────

    public function test_admin_can_view_order_auto_complete_page(): void
    {
        $this->actingAs($this->adminUser())
             ->get('/admin/settings/order-auto-complete')
             ->assertOk()
             ->assertViewIs('admin.settings.order-auto-complete.index')
             ->assertViewHas('autoCompleteDays', 7);
    }

    public function test_admin_sees_current_value_on_page(): void
    {
        ShopSetting::set('order_auto_complete_days', '14');

        $this->actingAs($this->adminUser())
             ->get('/admin/settings/order-auto-complete')
             ->assertOk()
             ->assertViewHas('autoCompleteDays', 14);
    }

    // ── Update ──────────────────────────────────────────────────────────────

    public function test_admin_can_update_auto_complete_days(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/order-auto-complete', [
                 'auto_complete_days' => 14,
             ])
             ->assertRedirect(route('admin.settings.order-auto-complete.index'))
             ->assertSessionHas('success');

        $this->assertEquals('14', ShopSetting::get('order_auto_complete_days'));
    }

    public function test_non_admin_cannot_update_auto_complete_days(): void
    {
        $this->actingAs($this->regularUser())
             ->post('/admin/settings/order-auto-complete', [
                 'auto_complete_days' => 14,
             ])
             ->assertForbidden();

        // Value must remain unchanged
        $this->assertEquals('7', ShopSetting::get('order_auto_complete_days'));
    }

    // ── Validation ──────────────────────────────────────────────────────────

    public function test_validation_rejects_missing_value(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/order-auto-complete', [])
             ->assertSessionHasErrors(['auto_complete_days']);
    }

    public function test_validation_rejects_non_integer(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/order-auto-complete', [
                 'auto_complete_days' => 'abc',
             ])
             ->assertSessionHasErrors(['auto_complete_days']);
    }

    public function test_validation_rejects_value_below_1(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/order-auto-complete', [
                 'auto_complete_days' => 0,
             ])
             ->assertSessionHasErrors(['auto_complete_days']);
    }

    public function test_validation_rejects_negative_value(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/order-auto-complete', [
                 'auto_complete_days' => -5,
             ])
             ->assertSessionHasErrors(['auto_complete_days']);
    }

    public function test_validation_rejects_value_above_30(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/order-auto-complete', [
                 'auto_complete_days' => 31,
             ])
             ->assertSessionHasErrors(['auto_complete_days']);
    }

    public function test_validation_accepts_boundary_value_1(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/order-auto-complete', [
                 'auto_complete_days' => 1,
             ])
             ->assertRedirect(route('admin.settings.order-auto-complete.index'));

        $this->assertEquals('1', ShopSetting::get('order_auto_complete_days'));
    }

    public function test_validation_accepts_boundary_value_30(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/order-auto-complete', [
                 'auto_complete_days' => 30,
             ])
             ->assertRedirect(route('admin.settings.order-auto-complete.index'));

        $this->assertEquals('30', ShopSetting::get('order_auto_complete_days'));
    }
}
