<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        $category = Category::create([
            'name' => 'Anting',
            'slug' => 'anting',
            'is_active' => true
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Anting Emas',
            'slug' => 'anting-emas',
            'price' => 50000,
            'stock' => 10,
            'weight' => 5,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_wishlist_endpoints(): void
    {
        // Index
        $response = $this->get(route('wishlist.index'));
        $response->assertRedirect('/login');

        // Toggle
        $response = $this->post(route('wishlist.toggle'), ['product_id' => $this->product->id]);
        $response->assertRedirect('/login');

        // Destroy
        $response = $this->delete('/wishlist/1');
        $response->assertRedirect('/login');
    }

    public function test_user_can_view_wishlist_page(): void
    {
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this
            ->actingAs($this->user)
            ->get(route('wishlist.index'));

        $response->assertOk();
        $response->assertViewHas('wishlists');
        $response->assertSee($this->product->name);
    }

    public function test_user_can_toggle_wishlist_add_and_remove(): void
    {
        // Add to wishlist
        $response = $this
            ->actingAs($this->user)
            ->postJson(route('wishlist.toggle'), [
                'product_id' => $this->product->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'in_wishlist' => true,
                'count' => 1,
            ]);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        // Remove from wishlist (toggle again)
        $response = $this
            ->actingAs($this->user)
            ->postJson(route('wishlist.toggle'), [
                'product_id' => $this->product->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'in_wishlist' => false,
                'count' => 0,
            ]);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_user_can_destroy_wishlist_item(): void
    {
        $wishlist = Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this
            ->actingAs($this->user)
            ->deleteJson("/wishlist/{$wishlist->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 0,
            ])
            ->assertJsonStructure(['ids']);

        $this->assertDatabaseMissing('wishlists', [
            'id' => $wishlist->id,
        ]);
    }

    public function test_user_cannot_destroy_other_users_wishlist_item(): void
    {
        $otherUser = User::factory()->create();
        $wishlist = Wishlist::create([
            'user_id' => $otherUser->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this
            ->actingAs($this->user)
            ->deleteJson("/wishlist/{$wishlist->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('wishlists', [
            'id' => $wishlist->id,
        ]);
    }

    public function test_wishlist_count_endpoint(): void
    {
        // Guest
        $response = $this->getJson(route('wishlist.count'));
        $response->assertOk()
            ->assertJson([
                'count' => 0,
                'ids' => [],
            ]);

        // Authenticated user with items
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this
            ->actingAs($this->user)
            ->getJson(route('wishlist.count'));

        $response->assertOk()
            ->assertJson([
                'count' => 1,
                'ids' => [$this->product->id],
            ]);
    }
}
