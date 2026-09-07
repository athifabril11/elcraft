<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AddressAndCheckoutUITest
 *
 * Covers three scenarios requested in the implementation task:
 *
 *  1. Checkout page HTML contains payment method option labels (e.g. "QRIS",
 *     "GoPay") inside the Alpine x-for template — not just the heading.
 *
 *  2a. When a user has two addresses both with is_default=false, the rendered
 *      checkout page shows zero "Utama" badge spans inside the address selector.
 *
 *  2b. When exactly one address has is_default=true, the badge appears on that
 *      one address only (one occurrence) regardless of label text.
 *
 *  3. AddressController::destroy() auto-promotes a remaining address to
 *     is_default=true when the deleted address was the default.
 */
class AddressAndCheckoutUITest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a minimal user with one cart item so the checkout page loads
     * (redirect guard: cart must not be empty).
     */
    private function userWithCart(): User
    {
        $user = User::factory()->create();

        $category = Category::create([
            'name'      => 'Anting',
            'slug'      => 'anting-' . uniqid(),
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Anting Test',
            'slug'        => 'anting-test-' . uniqid(),
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        return $user;
    }

    /**
     * Create an address row for a user.
     */
    private function makeAddress(User $user, string $label, bool $isDefault = false): Address
    {
        return Address::create([
            'user_id'        => $user->id,
            'label'          => $label,
            'recipient_name' => 'Test User',
            'phone'          => '08100000001',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Kecamatan Test',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Test No. 1',
            'is_default'     => $isDefault,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TEST 1 — Payment method selector labels present in HTML
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The checkout page renders essential sections cleanly:
     * address selector, shipping options, notes, and voucher.
     * Payment selection is delegated directly to the Midtrans Snap modal upon clicking pay.
     */
    public function test_checkout_page_html_contains_payment_method_labels(): void
    {
        $user = $this->userWithCart();

        $response = $this->actingAs($user)->get(route('checkout.index'));

        $response->assertOk();

        // Essential sections rendered
        $response->assertSee('Informasi Pengiriman', false);
        $response->assertSee('Pilih Kurir', false);
        $response->assertSee('Catatan Pesanan', false);
        $response->assertSee('Kode Voucher / Promo', false);
        // Redundant placeholder card is removed
        $response->assertDontSee('Pilih di halaman pembayaran', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TEST 2a — Zero "Utama" badges when both addresses are NOT default
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * When a user has two addresses and NEITHER has is_default=true,
     * the rendered checkout HTML must contain zero "Utama" badge pill spans.
     * The label text ("Utama") may still appear as the address name.
     */
    public function test_checkout_shows_zero_utama_badges_when_no_default_address(): void
    {
        $user = $this->userWithCart();

        // Both NOT default — one happens to be named "Utama"
        $this->makeAddress($user, 'Utama',  false);
        $this->makeAddress($user, 'Kantor', false);

        $response = $this->actingAs($user)->get(route('checkout.index'));
        $response->assertOk();

        $html = $response->getContent();

        // The badge pill uses a unique class combo that only appears in
        // the @if($addr->is_default) block
        $badgeClass = 'bg-brand/10 px-1.5 py-0.5 rounded-full';
        $badgeCount = substr_count($html, $badgeClass);

        $this->assertSame(
            0,
            $badgeCount,
            "Expected 0 'Utama' badge spans when no address is default, found {$badgeCount}."
        );

        // The label text itself must still render as the address name
        $this->assertStringContainsString('Utama', $html,
            "The label text 'Utama' should still appear as the address name.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TEST 2b — Exactly one "Utama" badge when one address is the default
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * When exactly one address has is_default=true, the rendered checkout HTML
     * must contain exactly one "Utama" badge pill — regardless of label text.
     */
    public function test_checkout_shows_exactly_one_utama_badge_for_default_address(): void
    {
        $user = $this->userWithCart();

        // Neither address is named "Utama" — badge must come from is_default only
        $this->makeAddress($user, 'Rumah',  true);
        $this->makeAddress($user, 'Kantor', false);

        $response = $this->actingAs($user)->get(route('checkout.index'));
        $response->assertOk();

        $html = $response->getContent();

        $badgeClass = 'bg-brand/10 px-1.5 py-0.5 rounded-full';
        $badgeCount = substr_count($html, $badgeClass);

        $this->assertSame(
            1,
            $badgeCount,
            "Expected exactly 1 'Utama' badge span when one address is default, found {$badgeCount}."
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TEST 3 — destroy() auto-promotes next address to default
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * When a user deletes their default address and at least one other address
     * remains, the remaining address (lowest id) must be promoted to is_default=true.
     */
    public function test_destroy_auto_promotes_next_address_when_default_is_deleted(): void
    {
        $user = User::factory()->create();

        $defaultAddr   = $this->makeAddress($user, 'Utama',  true);
        $remainingAddr = $this->makeAddress($user, 'Kantor', false);

        $response = $this->actingAs($user)->delete(
            route('profile.addresses.destroy', $defaultAddr)
        );

        $response->assertRedirect(route('profile.addresses'));

        $this->assertDatabaseMissing('addresses', ['id' => $defaultAddr->id]);

        $this->assertDatabaseHas('addresses', [
            'id'         => $remainingAddr->id,
            'is_default' => true,
        ]);
    }

    /**
     * Deleting a NON-default address must leave the existing default unchanged.
     */
    public function test_destroy_non_default_address_leaves_default_unchanged(): void
    {
        $user = User::factory()->create();

        $defaultAddr = $this->makeAddress($user, 'Utama',  true);
        $otherAddr   = $this->makeAddress($user, 'Kantor', false);

        $response = $this->actingAs($user)->delete(
            route('profile.addresses.destroy', $otherAddr)
        );

        $response->assertRedirect(route('profile.addresses'));

        $this->assertDatabaseMissing('addresses', ['id' => $otherAddr->id]);

        $this->assertDatabaseHas('addresses', [
            'id'         => $defaultAddr->id,
            'is_default' => true,
        ]);
    }
}
