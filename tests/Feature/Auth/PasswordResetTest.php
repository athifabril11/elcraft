<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ResetPassword::$toMailCallback = null;

        parent::tearDown();
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_after_successful_password_reset_login_route_forwards_status_flash_to_home(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            // Step 1: Submit the reset form — redirects to route('login') with 'status' flash.
            $resetResponse = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $resetResponse->assertRedirect(route('login'));

            // Step 2: Follow the redirect to GET /login.
            // The login GET route closure should forward the status flash and redirect to home
            // with show_login_modal, auth_modal_tab, and status all present.
            $loginResponse = $this->get(route('login'));

            $loginResponse->assertRedirect(route('home'));
            $loginResponse->assertSessionHas('show_login_modal', true);
            $loginResponse->assertSessionHas('auth_modal_tab', 'login');
            $loginResponse->assertSessionHas('status');

            return true;
        });
    }

    public function test_forgot_password_survives_email_failure(): void
    {
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            throw new \RuntimeException('SMTP down (simulasi)');
        });

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_forgot_password_for_unknown_email_keeps_existing_branching(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email' => __('passwords.user')]);
    }
}
