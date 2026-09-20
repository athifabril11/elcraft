<?php

namespace Tests\Feature\Admin;

use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WhatsAppSettingAdminTest
 *
 * Feature tests for admin WhatsApp settings:
 *  - Guest is redirected to login
 *  - Regular user gets 403
 *  - Admin can view settings page (200 OK)
 *  - Validation rejects short number (< 10 digits), value remains unchanged
 *  - Admin can update with valid number, ShopSetting updated and redirects with success flash
 *  - Non-admin cannot update setting
 */
class WhatsAppSettingAdminTest extends TestCase
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
            ['key' => 'admin_whatsapp_number'],
            ['value' => '62XXXXXXXXXX']
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/settings/whatsapp')
             ->assertRedirect('/login');
    }

    public function test_regular_user_gets_403_forbidden(): void
    {
        $this->actingAs($this->regularUser())
             ->get('/admin/settings/whatsapp')
             ->assertForbidden();
    }

    public function test_admin_can_view_whatsapp_settings_page(): void
    {
        $response = $this->actingAs($this->adminUser())
             ->get('/admin/settings/whatsapp');

        $response->assertOk()
                 ->assertViewIs('admin.settings.whatsapp.index')
                 ->assertViewHas('waNumber', '62XXXXXXXXXX');
    }

    public function test_post_with_invalid_short_number_fails_validation_and_leaves_setting_unchanged(): void
    {
        $response = $this->actingAs($this->adminUser())
             ->from('/admin/settings/whatsapp')
             ->post('/admin/settings/whatsapp', [
                 'wa_number' => '123',
             ]);

        $response->assertRedirect('/admin/settings/whatsapp')
                 ->assertSessionHasErrors(['wa_number']);

        $this->assertEquals('62XXXXXXXXXX', ShopSetting::get('admin_whatsapp_number'));
    }

    public function test_post_with_valid_number_updates_setting_and_redirects_with_success_flash(): void
    {
        $response = $this->actingAs($this->adminUser())
             ->post('/admin/settings/whatsapp', [
                 'wa_number' => '6281234567890',
             ]);

        $response->assertRedirect(route('admin.settings.whatsapp.index'))
                 ->assertSessionHas('success');

        $this->assertEquals('6281234567890', ShopSetting::get('admin_whatsapp_number'));
    }

    public function test_non_admin_cannot_update_whatsapp_number(): void
    {
        $this->actingAs($this->regularUser())
             ->post('/admin/settings/whatsapp', [
                 'wa_number' => '6281234567890',
             ])
             ->assertForbidden();

        $this->assertEquals('62XXXXXXXXXX', ShopSetting::get('admin_whatsapp_number'));
    }
}