<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_redirects_to_home_with_modal_flash(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('show_login_modal', true);
        $response->assertSessionHas('auth_modal_tab', 'register');
    }

    public function test_new_users_can_register_using_standard_post(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        // After registration, server redirects to email verification notice.
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_ajax_registration_with_valid_data_returns_json_success_and_logs_in(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertStatus(200);
        // Response includes success flag AND a redirect URL pointing to the
        // email verification notice so the frontend can navigate there.
        $response->assertJson([
            'success'  => true,
            'redirect' => route('verification.notice'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ]);
    }

    public function test_ajax_registration_with_invalid_data_returns_422_with_field_errors(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/register', [
            'name' => '',
            'email' => 'existing@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $this->assertGuest();
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registering_new_user_dispatches_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/register', [
            'name' => 'Email Check User',
            'email' => 'verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'verify@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
