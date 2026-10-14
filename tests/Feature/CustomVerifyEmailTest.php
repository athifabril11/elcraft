<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomVerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_verify_email_renders_expected_view_and_subject(): void
    {
        $user = User::factory()->unverified()->create([
            'name'  => 'Nurul Hidayah',
            'email' => 'nurul@example.com',
        ]);

        $notification = new VerifyEmail();
        $mail = $notification->toMail($user);

        $this->assertEquals('Verifikasi Alamat Email Anda - Elcraft', $mail->subject);
        $this->assertEquals('emails.verify-email', $mail->view);

        // Render the view to confirm no syntax or Blade rendering errors
        $rendered = view($mail->view, $mail->viewData)->render();

        $this->assertStringContainsString('Nurul Hidayah', $rendered);
        $this->assertStringContainsString('Verifikasi Email Anda', $rendered);
        $this->assertStringContainsString('el Craft', $rendered);
        $this->assertStringContainsString('Verifikasi Email Saya', $rendered);
        $this->assertStringContainsString('60 menit', $rendered);
    }

    public function test_verification_notification_can_be_sent_to_unverified_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_verification_survives_email_failure(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            throw new \RuntimeException('SMTP down (simulasi)');
        });

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertStatus(302);
        $response->assertSessionHas('status', 'verification-link-failed');
    }

    public function test_verification_page_shows_failure_banner(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->withSession(['status' => 'verification-link-failed'])
            ->get('/verify-email');

        $response->assertStatus(200);
        $response->assertSee('Email verifikasi gagal dikirim', false);
    }

    public function test_verification_page_keeps_success_banner_without_failure_text(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->withSession(['status' => 'verification-link-sent'])
            ->get('/verify-email');

        $response->assertStatus(200);
        $response->assertSee('Tautan verifikasi baru telah dikirimkan', false);
        $response->assertDontSee('Email verifikasi gagal dikirim', false);
    }
}
