<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

/**
 * Feature tests for the Google OAuth flow (Socialite).
 *
 * We mock the Socialite driver so no real HTTP request is made.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper: build a fake Socialite user object ────────────────────────────

    private function fakeSocialiteUser(
        string $id     = 'google-123',
        string $email  = 'googleuser@example.com',
        string $name   = 'Google User',
        string $avatar = 'https://lh3.googleusercontent.com/photo.jpg',
    ): SocialiteUserContract {
        // Concrete anonymous class avoids PHPUnit interface-mock notices
        return new class($id, $email, $name, $avatar) implements SocialiteUserContract {
            public function __construct(
                private string $id,
                private string $email,
                private string $name,
                private string $avatar,
            ) {}

            public function getId(): string         { return $this->id; }
            public function getEmail(): string      { return $this->email; }
            public function getName(): string       { return $this->name; }
            public function getAvatar(): string     { return $this->avatar; }
            public function getNickname(): ?string  { return null; }
            public function getRaw(): array         { return []; }
            public function getToken(): string      { return ''; }
            public function getRefreshToken(): ?string { return null; }
            public function getExpiresIn(): ?int    { return null; }
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

    // ─── Tests ────────────────────────────────────────────────────────────────

    /**
     * A brand-new Google user (no existing account) creates a new User record
     * and is logged in.
     */
    public function test_brand_new_google_user_is_created_and_logged_in(): void
    {
        $googleUser = $this->fakeSocialiteUser(
            id:     'google-new-001',
            email:  'brandnew@example.com',
            name:   'Brand New User',
            avatar: 'https://avatar.url/photo.jpg',
        );

        $this->mockSocialiteCallback($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Redirected to home (customer role)
        $response->assertRedirect(route('home'));

        // A new user record was created
        $this->assertDatabaseHas('users', [
            'email'     => 'brandnew@example.com',
            'google_id' => 'google-new-001',
            'name'      => 'Brand New User',
            'role'      => 'customer',
        ]);

        // The new user is logged in
        $this->assertAuthenticated();
    }

    /**
     * An existing user with matching email but no google_id gets their
     * google_id linked — no duplicate user record is created.
     */
    public function test_existing_email_user_gets_google_id_linked_not_duplicated(): void
    {
        // Pre-existing user registered via email/password
        $existing = User::create([
            'name'              => 'Existing User',
            'email'             => 'existing@example.com',
            'password'          => bcrypt('secret'),
            'role'              => 'customer',
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        $this->assertNull($existing->google_id);

        $googleUser = $this->fakeSocialiteUser(
            id:    'google-link-002',
            email: 'existing@example.com',
            name:  'Existing User',
        );

        $this->mockSocialiteCallback($googleUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));

        // google_id was linked to the existing record
        $this->assertDatabaseHas('users', [
            'id'        => $existing->id,
            'email'     => 'existing@example.com',
            'google_id' => 'google-link-002',
        ]);

        // No duplicate record was created
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());

        $this->assertAuthenticated();
    }

    /**
     * An existing user with a matching google_id logs in directly — no new
     * record is created and their data is not modified.
     */
    public function test_returning_google_user_with_existing_google_id_is_logged_in(): void
    {
        $existing = User::create([
            'name'              => 'Returning User',
            'email'             => 'returning@example.com',
            'password'          => null,
            'google_id'         => 'google-returning-003',
            'avatar'            => 'https://old-avatar.url',
            'role'              => 'customer',
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        $googleUser = $this->fakeSocialiteUser(
            id:    'google-returning-003',
            email: 'returning@example.com',
            name:  'Returning User',
        );

        $this->mockSocialiteCallback($googleUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));

        // Still only one user record
        $this->assertSame(1, User::where('google_id', 'google-returning-003')->count());

        // The logged-in user is the pre-existing one
        $this->assertAuthenticatedAs($existing);
    }

    /**
     * An admin user authenticated via Google is redirected to the admin dashboard.
     */
    public function test_google_admin_user_is_redirected_to_admin_dashboard(): void
    {
        User::create([
            'name'              => 'Admin User',
            'email'             => 'admin@example.com',
            'password'          => null,
            'google_id'         => 'google-admin-004',
            'role'              => 'admin',
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        $googleUser = $this->fakeSocialiteUser(
            id:    'google-admin-004',
            email: 'admin@example.com',
            name:  'Admin User',
        );

        $this->mockSocialiteCallback($googleUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('admin.dashboard'));
    }
}
