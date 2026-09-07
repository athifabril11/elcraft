<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_redirects_to_home_with_modal_flash(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('show_login_modal', true);
        $response->assertSessionHas('auth_modal_tab', 'login');
    }

    /**
     * Storefront layout contains Rose Gold brand elements,
     * the Google auth button, and the tabbed login/register modal form fields.
     */
    public function test_storefront_contains_tabbed_auth_modal_with_brand_and_google_button(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Brand name
        $response->assertSee('el Craft');
        // Google OAuth button links to the redirect route
        $response->assertSee(route('auth.google.redirect'));
        $response->assertSee('Masuk dengan Google');
        $response->assertSee('Daftar dengan Google');
        // Modal tab buttons and form elements
        $response->assertSee('id="login-modal"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="remember"', false);
    }

    public function test_users_can_authenticate_using_standard_post(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_ajax_login_with_valid_credentials_returns_json_success(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_ajax_login_with_invalid_credentials_returns_422_with_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_admin_user_redirects_to_admin_dashboard_on_login(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email'    => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_user_ajax_login_returns_admin_redirect_url(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->postJson('/login', [
            'email'    => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertStatus(200);
        $response->assertJson([
            'success'  => true,
            'redirect' => route('admin.dashboard'),
        ]);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
