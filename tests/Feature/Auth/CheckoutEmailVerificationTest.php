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

    public function test_unverified_user_can_access_cart_wishlist_and_profile(): void
    {
        $user = User::factory()->unverified()->create();

        // Cart page
        $cartResponse = $this->actingAs($user)->get('/cart');
        $cartResponse->assertStatus(200);

        // Wishlist page
        $wishlistResponse = $this->actingAs($user)->get('/wishlist');
        $wishlistResponse->assertStatus(200);

        // Profile page
        $profileResponse = $this->actingAs($user)->get('/profile');
        $profileResponse->assertStatus(200);
    }

    public function test_verified_user_can_access_checkout(): void
    {
        $user = User::factory()->create(); // verified by default in factory

        // Setup active cart and address
        $cart = $user->getOrCreateCart();
        $product = $this->createProduct();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->createAddress($user);

        $response = $this->actingAs($user)->get('/checkout');

        $response->assertStatus(200);
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
}
