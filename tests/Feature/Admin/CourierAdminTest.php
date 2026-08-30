<?php

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Courier;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CourierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CourierAdminTest
 *
 * Tests courier database management and admin toggle UI:
 *  - 6 couriers are seeded and active by default
 *  - Guest is redirected to login
 *  - Non-admin gets 403
 *  - Admin can view couriers index
 *  - Admin can toggle courier active state
 *  - Deactivating a courier makes shipping-cost & checkout requests rejected (422)
 *  - Reactivating a courier makes shipping-cost & checkout requests accepted again
 *  - Checkout page only renders active couriers
 */
class CourierAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function regularUser(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(CourierSeeder::class);
    }

    // ── Seed Verification ───────────────────────────────────────────────────

    public function test_all_six_couriers_are_active_by_default_after_seeding(): void
    {
        $this->assertDatabaseCount('couriers', 6);
        $this->assertEquals(6, Courier::active()->count());

        $expectedCodes = ['jne', 'jnt', 'pos', 'tiki', 'lion', 'sap'];
        $this->assertEquals($expectedCodes, Courier::getActiveCodes());
    }

    // ── Access Control ──────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_couriers_page(): void
    {
        $this->get('/admin/settings/couriers')
             ->assertRedirect('/login');
    }

    public function test_regular_user_gets_403_on_couriers_page(): void
    {
        $this->actingAs($this->regularUser())
             ->get('/admin/settings/couriers')
             ->assertForbidden();
    }

    public function test_admin_can_view_couriers_page(): void
    {
        $response = $this->actingAs($this->adminUser())
             ->get('/admin/settings/couriers');

        $response->assertOk()
                 ->assertViewIs('admin.settings.couriers.index')
                 ->assertViewHas('couriers');

        $this->assertCount(6, $response->viewData('couriers'));
    }

    public function test_non_admin_cannot_toggle_courier(): void
    {
        $courier = Courier::where('code', 'lion')->firstOrFail();

        $this->actingAs($this->regularUser())
             ->patch("/admin/settings/couriers/{$courier->id}")
             ->assertForbidden();

        $this->assertTrue($courier->fresh()->is_active);
    }

    // ── Toggle Functionality ────────────────────────────────────────────────

    public function test_admin_can_toggle_courier_status(): void
    {
        $admin = $this->adminUser();
        $courier = Courier::where('code', 'lion')->firstOrFail();

        // 1. Deactivate
        $response = $this->actingAs($admin)
             ->patch("/admin/settings/couriers/{$courier->id}");

        $response->assertRedirect(route('admin.settings.couriers.index'));
        $this->assertFalse($courier->fresh()->is_active);
        $this->assertNotContains('lion', Courier::getActiveCodes());

        // 2. Reactivate
        $response = $this->actingAs($admin)
             ->patch("/admin/settings/couriers/{$courier->id}");

        $response->assertRedirect(route('admin.settings.couriers.index'));
        $this->assertTrue($courier->fresh()->is_active);
        $this->assertContains('lion', Courier::getActiveCodes());
    }

    public function test_deactivated_courier_is_rejected_in_shipping_cost_calculation(): void
    {
        $admin = $this->adminUser();
        $courier = Courier::where('code', 'lion')->firstOrFail();

        // Deactivate lion
        $this->actingAs($admin)->patch("/admin/settings/couriers/{$courier->id}");

        // Now cost calculation request for lion should fail validation (422)
        $response = $this->postJson('/shipping/cost', [
            'city_id' => 23,
            'weight'  => 1000,
            'courier' => 'lion',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['courier']);
    }

    public function test_reactivated_courier_is_accepted_in_shipping_cost_calculation(): void
    {
        $admin = $this->adminUser();
        $courier = Courier::where('code', 'lion')->firstOrFail();

        // Deactivate lion
        $this->actingAs($admin)->patch("/admin/settings/couriers/{$courier->id}");

        // Reactivate lion
        $this->actingAs($admin)->patch("/admin/settings/couriers/{$courier->id}");

        Http::fake([
            '*/domestic-cost*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [
                    [
                        'code'        => 'lion',
                        'service'     => 'REGPACK',
                        'description' => 'Regular Package',
                        'cost'        => 15000,
                        'etd'         => '2-3',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/shipping/cost', [
            'city_id' => 23,
            'weight'  => 1000,
            'courier' => 'lion',
        ]);

        $response->assertOk()
                 ->assertJson(['success' => true]);
    }

    public function test_checkout_page_renders_only_active_couriers(): void
    {
        $user = $this->regularUser();

        // Create product and add to cart so checkout page doesn't redirect
        $category = Category::create(['name' => 'Crafts', 'slug' => 'crafts']);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Handmade Bag',
            'slug'        => 'handmade-bag',
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 500,
            'is_active'   => true,
        ]);
        $cart = $user->getOrCreateCart();
        CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        // Deactivate Lion Parcel and SAP Express
        Courier::whereIn('code', ['lion', 'sap'])->update(['is_active' => false]);
        Courier::clearCache();

        $response = $this->actingAs($user)->get('/checkout');

        $response->assertOk();
        $response->assertSee('JNE');
        $response->assertSee('J&T Express');
        $response->assertSee('POS Indonesia');
        $response->assertSee('TIKI');
        $response->assertDontSee('Lion Parcel');
        $response->assertDontSee('SAP Express');
    }
}
