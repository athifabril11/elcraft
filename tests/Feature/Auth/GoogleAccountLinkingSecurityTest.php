<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

/**
 * Security tests for account pre-hijacking protection in the Google OAuth
 * callback (GoogleController::callback).
 *
 * An attacker must NOT be able to pre-register a victim's email with a
 * password of their choosing (email_verified_at NULL) and then quietly gain
 * a linked account (plus a known password) when the victim signs in with
 * Google. Linking an unverified local account must verify the email,
 * invalidate the pre-chosen password and rotate the remember token.
 *
 * Google's own email_verified claim is honoured: without it (or when false)
 * the callback must neither link nor create accounts.
 *
 * The Socialite driver is mocked — no real Google network access.
 */
class GoogleAccountLinkingSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers (self-contained, mirroring GoogleLoginTest's approach) ───────

    /**
     * Build a fake Socialite user. The raw payload is exposed exactly like
     * Laravel\Socialite\AbstractUser exposes it: a public $user array
     * readable via getRaw(). $emailVerified = null omits the
     * email_verified key entirely; default is true.
     */
    private function fakeSocialiteUser(
        string $id     = 'google-sec-001',
        string $email  = 'victim@example.com',
        string $name   = 'Victim User',
        string $avatar = 'https://lh3.googleusercontent.com/photo.jpg',
        ?bool $emailVerified = true,
    ): SocialiteUserContract {
        return new class($id, $email, $name, $avatar, $emailVerified) implements SocialiteUserContract {
            public array $user;

            public function __construct(
                private string $id,
                private string $email,
                private string $name,
                private string $avatar,
                ?bool $emailVerified,
            ) {
                $this->user = $emailVerified === null
                    ? []
                    : ['email_verified' => $emailVerified];
            }

            public function getId(): string          { return $this->id; }
            public function getEmail(): string       { return $this->email; }
            public function getName(): string        { return $this->name; }
            public function getAvatar(): string      { return $this->avatar; }
            public function getNickname(): ?string   { return null; }
            public function getRaw(): array          { return $this->user; }
            public function getToken(): string       { return ''; }
            public function getRefreshToken(): ?string { return null; }
            public function getExpiresIn(): ?int     { return null; }
            public function getApprovedScopes(): array { return []; }
        };
    }

    /** Stub the Socialite driver so callback() receives our fake Google user. */
    private function mockSocialiteCallback(SocialiteUserContract $googleUser): void
    {
        $provider = \Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);
    }

    private function makeLocalUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'              => 'Local User',
            'email'             => 'victim@example.com',
            'password'          => bcrypt('AttackerChosenPass1!'),
            'role'              => 'customer',
            'is_active'         => true,
            'email_verified_at' => null,
        ], $overrides));
    }

    // ─── 1. Pre-registered (unverified) local account takeover ────────────────

    public function test_unverified_local_account_is_neutralised_when_linking_verified_google(): void
    {
        $user = $this->makeLocalUser();
        $oldPassword = 'AttackerChosenPass1!';
        $oldRememberToken = 'pre-registered-remember-token';
        $user->remember_token = $oldRememberToken;
        $user->save();

        $this->assertNull($user->email_verified_at);

        $this->mockSocialiteCallback($this->fakeSocialiteUser(
            id:            'google-takeover-001',
            email:         'victim@example.com',
            emailVerified: true,
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));

        $user->refresh();

        // Logged in as the same (single) account, now linked via Google.
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-takeover-001', $user->google_id);
        $this->assertNotNull($user->email_verified_at);

        // The pre-registrant's password no longer works; token was rotated.
        $this->assertFalse(Hash::check($oldPassword, $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);

        // No duplicate row for that email.
        $this->assertSame(1, User::where('email', 'victim@example.com')->count());
    }

    // ─── 2. Verified local account keeps its password ─────────────────────────

    public function test_verified_local_account_is_linked_without_password_change(): void
    {
        $user = $this->makeLocalUser([
            'email_verified_at' => now(),
            'password'          => bcrypt('MyRealPassword1!'),
        ]);
        $oldRememberToken = 'verified-remember-token';
        $user->remember_token = $oldRememberToken;
        $user->save();

        $this->mockSocialiteCallback($this->fakeSocialiteUser(
            id:            'google-verified-002',
            email:         'victim@example.com',
            emailVerified: true,
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));

        $user->refresh();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-verified-002', $user->google_id);

        // Existing credentials are preserved for a verified account.
        $this->assertTrue(Hash::check('MyRealPassword1!', $user->password));
        $this->assertSame($oldRememberToken, $user->remember_token);
        $this->assertSame(1, User::where('email', 'victim@example.com')->count());
    }

    // ─── 3. Unverified Google claim + existing local account ──────────────────

    public function test_unverified_google_email_does_not_link_existing_account(): void
    {
        $user = $this->makeLocalUser([
            'email_verified_at' => now(),
            'password'          => bcrypt('LocalPassword1!'),
        ]);

        $this->mockSocialiteCallback($this->fakeSocialiteUser(
            id:            'google-unverified-003',
            email:         'victim@example.com',
            emailVerified: false,
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('show_login_modal', true);
        $response->assertSessionHas('auth_modal_tab', 'login');
        $response->assertSessionHas(
            'error',
            'Email Google kamu belum terverifikasi, silakan gunakan metode login lain.'
        );

        $this->assertGuest();

        $user->refresh();
        $this->assertNull($user->google_id);
        $this->assertTrue(Hash::check('LocalPassword1!', $user->password));
    }

    // ─── 4. Unverified Google claim + brand-new email ─────────────────────────

    public function test_unverified_google_email_does_not_create_new_account(): void
    {
        $this->mockSocialiteCallback($this->fakeSocialiteUser(
            id:            'google-unverified-004',
            email:         'brandnew-unverified@example.com',
            emailVerified: false,
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'brandnew-unverified@example.com',
        ]);
    }

    // ─── 5. Payload without any email_verified key ────────────────────────────

    public function test_missing_email_verified_key_is_treated_as_unverified(): void
    {
        $user = $this->makeLocalUser([
            'email_verified_at' => now(),
            'password'          => bcrypt('LocalPassword1!'),
        ]);

        // emailVerified: null → the raw payload omits the key entirely.
        $this->mockSocialiteCallback($this->fakeSocialiteUser(
            id:            'google-no-claim-005',
            email:         'victim@example.com',
            emailVerified: null,
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas(
            'error',
            'Email Google kamu belum terverifikasi, silakan gunakan metode login lain.'
        );

        $this->assertGuest();

        $user->refresh();
        $this->assertNull($user->google_id);
        $this->assertTrue(Hash::check('LocalPassword1!', $user->password));
    }

    // ─── 6. Inactive unverified local account ─────────────────────────────────

    public function test_inactive_unverified_local_account_is_rejected_untouched(): void
    {
        $user = $this->makeLocalUser([
            'is_active'         => false,
            'email_verified_at' => null,
            'password'          => bcrypt('InactivePassword1!'),
        ]);

        $this->mockSocialiteCallback($this->fakeSocialiteUser(
            id:            'google-inactive-006',
            email:         'victim@example.com',
            emailVerified: true,
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'Akun ini sudah tidak aktif.');

        $this->assertGuest();

        $user->refresh();
        $this->assertNull($user->google_id);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('InactivePassword1!', $user->password));
    }

    // ─── 7. Returning google_id user unaffected by the claim ──────────────────

    public function test_returning_google_id_user_logs_in_even_when_claim_is_false(): void
    {
        $user = $this->makeLocalUser([
            'google_id'         => 'google-returning-007',
            'email_verified_at' => now(),
            'password'          => null,
        ]);

        $this->mockSocialiteCallback($this->fakeSocialiteUser(
            id:            'google-returning-007',
            email:         'victim@example.com',
            emailVerified: false,
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user->fresh());
    }
}
