<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests covering the anonymize-or-hard-delete account deletion flow,
 * and the is_active enforcement on both login paths.
 */
class AccountDeletionAnonymizationTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Create a user with a linked order so the FK prevents hard-delete. */
    private function userWithOrder(): User
    {
        $user = User::factory()->create();

        $address = Address::create([
            'user_id'        => $user->id,
            'label'          => 'Rumah',
            'recipient_name' => $user->name,
            'phone'          => '08123456789',
            'province'       => 'Jawa Tengah',
            'city'           => 'Purwokerto',
            'district'       => 'Purwokerto Utara',
            'postal_code'    => '53111',
            'full_address'   => 'Jl. Test No. 1',
            'is_default'     => true,
        ]);

        Order::create([
            'order_number'     => 'ORD-TEST-001',
            'user_id'          => $user->id,
            'address_id'       => $address->id,
            'subtotal'         => 100000,
            'discount_amount'  => 0,
            'voucher_discount' => 0,
            'shipping_cost'    => 15000,
            'total_amount'     => 115000,
            'status'           => 'paid',
        ]);

        return $user;
    }

    // ── Hard-delete path (user with NO orders) ─────────────────────────────────

    public function test_user_without_orders_is_hard_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh(), 'User row should be gone (hard-deleted).');
    }

    // ── Anonymize path (user WITH orders) ─────────────────────────────────────

    public function test_user_with_orders_is_anonymized_not_hard_deleted(): void
    {
        $user = $this->userWithOrder();
        $originalOrderCount = $user->orders()->count();
        $userId = $user->id;

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        // Row still exists (not deleted)
        $fresh = User::find($userId);
        $this->assertNotNull($fresh, 'User row must still exist after anonymization.');

        // PII has been wiped
        $this->assertSame('Pengguna Terhapus', $fresh->name);
        $this->assertStringContainsString('@deleted.elcraft.local', $fresh->email);
        $this->assertNull($fresh->google_id);
        $this->assertNull($fresh->phone);
        $this->assertNull($fresh->avatar);

        // Account marked inactive and anonymized
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertNotNull($fresh->anonymized_at);

        // Orders are completely untouched
        $this->assertSame(
            $originalOrderCount,
            Order::where('user_id', $userId)->count(),
            'Orders must be preserved after anonymization.'
        );
    }

    public function test_anonymized_user_address_records_are_preserved(): void
    {
        $user = $this->userWithOrder();
        $userId = $user->id;
        $addressCount = Address::where('user_id', $userId)->count();

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertSame(
            $addressCount,
            Address::where('user_id', $userId)->count(),
            'Address records must be preserved after anonymization.'
        );
    }

    // ── is_active enforcement — email/password login ───────────────────────────

    public function test_inactive_user_cannot_login_with_email_password(): void
    {
        $user = User::factory()->create([
            'password'  => bcrypt('password'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ── is_active enforcement — Google login (found by email) ─────────────────

    public function test_inactive_email_matched_google_user_is_rejected(): void
    {
        // Pre-existing inactive user (anonymized) — matched by email
        $user = User::factory()->create([
            'email'     => 'returning@example.com',
            'is_active' => false,
        ]);

        $googleUser = $this->buildFakeSocialiteUser(
            id: 'google-inactive-999',
            email: 'returning@example.com',
        );

        $this->mockSocialiteCallback($googleUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'Akun ini sudah tidak aktif.');
        $this->assertGuest();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function buildFakeSocialiteUser(
        string $id,
        string $email = 'google@example.com',
        string $name  = 'Google User',
    ): \Laravel\Socialite\Contracts\User {
        return new class($id, $email, $name) implements \Laravel\Socialite\Contracts\User {
            public function __construct(
                private string $id,
                private string $email,
                private string $name,
            ) {}
            public function getId(): string              { return $this->id; }
            public function getEmail(): string           { return $this->email; }
            public function getName(): string            { return $this->name; }
            public function getAvatar(): string          { return ''; }
            public function getNickname(): ?string       { return null; }
            public function getRaw(): array              { return []; }
            public function getToken(): string           { return ''; }
            public function getRefreshToken(): ?string   { return null; }
            public function getExpiresIn(): ?int         { return null; }
            public function getApprovedScopes(): array   { return []; }
        };
    }

    private function mockSocialiteCallback(\Laravel\Socialite\Contracts\User $googleUser): void
    {
        $provider = \Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);
    }
}
