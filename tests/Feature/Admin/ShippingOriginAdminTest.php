<?php

namespace Tests\Feature\Admin;

use App\Models\ShopSetting;
use App\Models\User;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * ShippingOriginAdminTest
 *
 * Tests admin access control and functionality for the shipping origin settings page.
 * Covers:
 *  - Guest is redirected to login
 *  - Regular user gets 403
 *  - Admin can view the settings page
 *  - Admin can update the origin city
 *  - ShippingService picks up new origin from ShopSetting after update
 *  - Validation rejects missing/invalid fields
 */
class ShippingOriginAdminTest extends TestCase
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
        Cache::flush();
        // Seed default origin setting
        ShopSetting::updateOrCreate(['key' => 'shipping_origin_city_id'],   ['value' => '256']);
        ShopSetting::updateOrCreate(['key' => 'shipping_origin_city_name'], ['value' => 'Jember']);
    }

    // ── Access Control ──────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_shipping_origin_page(): void
    {
        $this->get('/admin/settings/shipping')
             ->assertRedirect('/login');
    }

    public function test_regular_user_gets_403_on_shipping_origin_page(): void
    {
        $this->actingAs($this->regularUser())
             ->get('/admin/settings/shipping')
             ->assertForbidden();
    }

    public function test_admin_can_view_shipping_origin_page(): void
    {
        $this->actingAs($this->adminUser())
             ->get('/admin/settings/shipping')
             ->assertOk()
             ->assertViewIs('admin.settings.shipping.index')
             ->assertViewHas('originCityId', '256')
             ->assertViewHas('originCityName', 'Jember');
    }

    // ── Update ──────────────────────────────────────────────────────────────

    public function test_admin_can_update_shipping_origin_city(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/shipping', [
                 'origin_city_id'   => 152,
                 'origin_city_name' => 'Kudus',
             ])
             ->assertRedirect(route('admin.settings.shipping.index'));

        $this->assertEquals('152',   ShopSetting::get('shipping_origin_city_id'));
        $this->assertEquals('Kudus', ShopSetting::get('shipping_origin_city_name'));
    }

    public function test_non_admin_cannot_update_shipping_origin(): void
    {
        $this->actingAs($this->regularUser())
             ->post('/admin/settings/shipping', [
                 'origin_city_id'   => 152,
                 'origin_city_name' => 'Kudus',
             ])
             ->assertForbidden();

        // Origin should remain unchanged
        $this->assertEquals('256', ShopSetting::get('shipping_origin_city_id'));
    }

    public function test_update_validates_required_fields(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/shipping', [])
             ->assertSessionHasErrors(['origin_city_id', 'origin_city_name']);
    }

    public function test_update_validates_city_id_must_be_positive_integer(): void
    {
        $this->actingAs($this->adminUser())
             ->post('/admin/settings/shipping', [
                 'origin_city_id'   => 0,
                 'origin_city_name' => 'Kudus',
             ])
             ->assertSessionHasErrors(['origin_city_id']);
    }

    // ── ShippingService picks up new value ──────────────────────────────────

    public function test_shipping_service_reads_origin_from_shop_setting(): void
    {
        // Change origin to a custom ID
        ShopSetting::set('shipping_origin_city_id', '999');

        $service = new ShippingService();

        // Access the protected 'origin' property via reflection
        $reflection = new \ReflectionClass($service);
        $prop = $reflection->getProperty('origin');
        $prop->setAccessible(true);

        $this->assertEquals('999', $prop->getValue($service));
    }

    public function test_shipping_service_falls_back_to_config_when_setting_missing(): void
    {
        // Remove the DB setting
        ShopSetting::where('key', 'shipping_origin_city_id')->delete();

        $service = new ShippingService();

        $reflection = new \ReflectionClass($service);
        $prop = $reflection->getProperty('origin');
        $prop->setAccessible(true);

        // Should fall back to config('rajaongkir.origin') or '152'
        $expected = config('rajaongkir.origin') ?? '152';
        $this->assertEquals($expected, $prop->getValue($service));
    }

    public function test_shipping_service_uses_new_origin_after_admin_update(): void
    {
        $admin = $this->adminUser();

        // Admin updates origin to a new city
        $this->actingAs($admin)->post('/admin/settings/shipping', [
            'origin_city_id'   => 500,
            'origin_city_name' => 'Surabaya',
        ]);

        // Fresh ShippingService instance picks up the new value
        $service = new ShippingService();
        $reflection = new \ReflectionClass($service);
        $prop = $reflection->getProperty('origin');
        $prop->setAccessible(true);

        $this->assertEquals('500', $prop->getValue($service));
    }
}
