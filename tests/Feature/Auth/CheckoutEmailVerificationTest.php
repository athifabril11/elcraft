<?php

namespace Tests\Feature\Auth;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(): Product
    {
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting', 'is_active' => true]);
        return Product::create([
            'category_id' => $category->id,
            'name'        => 'Anting Rose Gold',
            'slug'        => 'anting-rose-gold',
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 5,
            'is_active'   => true,
        ]);
    }

    private function createAddress(User $user): Address
    {
        return Address::create([
            'user_id'        => $user->id,
            'label'          => 'Rumah',
            'recipient_name' => 'Test User',
            'phone'          => '08123456789',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40132',
            'full_address'   => 'Jl. Dago No. 10',
            'is_default'     => true,
        ]);
    }

    // ─── Unverified user is blocked from ALL protected pages ─────────────────

    public function test_unverified_user_cannot_access_cart_wishlist_profile_or_orders(): void
    {
        $user = User::factory()->unverified()->create();

        // Cart
        $this->actingAs($user)->get('/cart')
            ->assertRedirect(route('verification.notice'));

        // Wishlist
        $this->actingAs($user)->get('/wishlist')
            ->assertRedirect(route('verification.notice'));

        // Profile
        $this->actingAs($user)->get('/profile')
            ->assertRedirect(route('verification.notice'));

        // Profile addresses
        $this->actingAs($user)->get('/profile/addresses')
            ->assertRedirect(route('verification.notice'));

        // Orders list
        $this->actingAs($user)->get('/orders')
            ->assertRedirect(route('verification.notice'));

        // Order detail (any order number — middleware fires before controller)
        $this->actingAs($user)->get('/orders/ELC-TEST-0001')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_hitting_checkout_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        // Setup active cart and address
        $cart = $user->getOrCreateCart();
        $product = $this->createProduct();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->createAddress($user);

        $response = $this->actingAs($user)->get('/checkout');

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_hitting_checkout_snap_token_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post('/checkout/snap-token', [
            'address_id' => 1,
            'courier'    => 'jne',
            'service'    => 'REG',
        ]);

        $response->assertRedirect(route('verification.notice'));
    }

    // ─── Verified user can access everything ─────────────────────────────────

    public function test_verified_user_can_access_cart_wishlist_profile_and_orders(): void
    {
        $user = User::factory()->create(); // verified by default in factory

        $this->actingAs($user)->get('/cart')->assertStatus(200);
        $this->actingAs($user)->get('/wishlist')->assertStatus(200);
        $this->actingAs($user)->get('/profile')->assertStatus(200);
        $this->actingAs($user)->get('/orders')->assertStatus(200);
    }

    public function test_verified_user_can_access_checkout(): void
    {
        $user = User::factory()->create(); // verified by default in factory

        $cart = $user->getOrCreateCart();
        $product = $this->createProduct();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->createAddress($user);

        $response = $this->actingAs($user)->get('/checkout');

        $response->assertStatus(200);
    }

    // ─── Google user is auto-verified — no friction anywhere ─────────────────

    public function test_google_user_has_verified_email_and_can_access_all_protected_routes(): void
    {
        $googleUser = User::create([
            'name'              => 'Google User',
            'email'             => 'googleuser@example.com',
            'google_id'         => 'google-123456789',
            'email_verified_at' => now(),
            'role'              => 'customer',
            'is_active'         => true,
            'password'          => bcrypt('random-secret-hash'),
        ]);

        $this->actingAs($googleUser)->get('/cart')->assertStatus(200);
        $this->actingAs($googleUser)->get('/wishlist')->assertStatus(200);
        $this->actingAs($googleUser)->get('/profile')->assertStatus(200);
        $this->actingAs($googleUser)->get('/orders')->assertStatus(200);
    }

    public function test_google_user_has_verified_email_and_can_access_checkout(): void
    {
        $googleUser = User::create([
            'name'              => 'Google User',
            'email'             => 'googleuser@example.com',
            'google_id'         => 'google-123456789',
            'email_verified_at' => now(),
            'role'              => 'customer',
            'is_active'         => true,
            'password'          => bcrypt('random-secret-hash'),
        ]);

        $cart = $googleUser->getOrCreateCart();
        $product = $this->createProduct();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->createAddress($googleUser);

        $response = $this->actingAs($googleUser)->get('/checkout');

        $response->assertStatus(200);
    }

    // ─── Guests still get the existing modal-flash redirect (unaffected) ─────

    public function test_guest_visiting_cart_is_redirected_with_modal_flash(): void
    {
        $response = $this->get('/cart');

        $response->assertRedirect();
        $response->assertSessionHas('show_login_modal', true);
    }

    public function test_guest_visiting_profile_is_redirected_with_modal_flash(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect();
        $response->assertSessionHas('show_login_modal', true);
    }
}
