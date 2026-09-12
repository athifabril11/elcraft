<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ReviewApprovalStatusTest
 *
 * Verifies the three-state review moderation system:
 *   is_approved = null  -> pending  (newly submitted)
 *   is_approved = true  -> approved
 *   is_approved = false -> rejected
 */
class ReviewApprovalStatusTest extends TestCase
{
    use RefreshDatabase;

    // -- Helpers ---------------------------------------------------------------

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customerUser(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function makeSelesaiOrderItem(User $customer): OrderItem
    {
        $address = $customer->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $customer->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Test No. 1',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-REVTEST-' . uniqid(),
            'user_id'      => $customer->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 150000,
            'status'       => 'selesai',
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'test-review-cat'],
            ['name' => 'Test Review Category', 'is_active' => true]
        );

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Test Review',
            'slug'        => 'gelang-test-review-' . uniqid(),
            'description' => 'Test product for review',
            'price'       => 150000,
            'weight'      => 50,
            'stock'       => 10,
            'is_active'   => true,
        ]);

        return OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 150000,
            'quantity'     => 1,
            'subtotal'     => 150000,
        ]);
    }

    private function seedReview(OrderItem $item, User $customer, mixed $isApproved): Review
    {
        return Review::create([
            'product_id'    => $item->product_id,
            'user_id'       => $customer->id,
            'order_item_id' => $item->id,
            'rating'        => 5,
            'comment'       => 'Test review comment',
            'is_approved'   => $isApproved,
        ]);
    }

    // -- Test 1: Customer store() lands as null --------------------------------

    public function test_new_review_via_store_endpoint_has_null_is_approved(): void
    {
        $customer = $this->customerUser();
        $item     = $this->makeSelesaiOrderItem($customer);

        $this->actingAs($customer)
             ->post(route('reviews.store'), [
                 'order_item_id' => $item->id,
                 'rating'        => 4,
                 'comment'       => 'Produk bagus!',
             ])
             ->assertRedirect();

        $review = Review::where('order_item_id', $item->id)->firstOrFail();

        $this->assertNull($review->is_approved,
            'New review must have is_approved = null (pending), not false.');
    }

    // -- Test 2: null review appears in pending tab ---------------------------

    public function test_null_review_appears_in_admin_pending_tab(): void
    {
        $customer = $this->customerUser();
        $item     = $this->makeSelesaiOrderItem($customer);
        $this->seedReview($item, $customer, null);

        $this->actingAs($this->adminUser())
             ->get(route('admin.reviews.index', ['status' => 'pending']))
             ->assertOk()
             ->assertViewHas('reviews', function ($reviews) {
                 return $reviews->count() === 1 && is_null($reviews->first()->is_approved);
             });
    }

    // -- Test 3: null does NOT appear in approved or rejected tabs ------------

    public function test_null_review_does_not_appear_in_approved_tab(): void
    {
        $customer = $this->customerUser();
        $item     = $this->makeSelesaiOrderItem($customer);
        $this->seedReview($item, $customer, null);

        $this->actingAs($this->adminUser())
             ->get(route('admin.reviews.index', ['status' => 'approved']))
             ->assertOk()
             ->assertViewHas('reviews', fn($reviews) => $reviews->count() === 0);
    }

    public function test_null_review_does_not_appear_in_rejected_tab(): void
    {
        $customer = $this->customerUser();
        $item     = $this->makeSelesaiOrderItem($customer);
        $this->seedReview($item, $customer, null);

        $this->actingAs($this->adminUser())
             ->get(route('admin.reviews.index', ['status' => 'rejected']))
             ->assertOk()
             ->assertViewHas('reviews', fn($reviews) => $reviews->count() === 0);
    }

    // -- Test 4: admin approve() sets true ------------------------------------

    public function test_admin_approve_sets_is_approved_true_and_appears_in_approved_tab(): void
    {
        $customer = $this->customerUser();
        $item     = $this->makeSelesaiOrderItem($customer);
        $review   = $this->seedReview($item, $customer, null);

        $this->actingAs($this->adminUser())
             ->patch(route('admin.reviews.approve', $review))
             ->assertRedirect();

        $this->assertTrue($review->fresh()->is_approved,
            'Approved review must have is_approved = true.');

        $this->actingAs($this->adminUser())
             ->get(route('admin.reviews.index', ['status' => 'approved']))
             ->assertViewHas('reviews', fn($reviews) => $reviews->contains('id', $review->id));
    }

    // -- Test 5: admin reject() sets false ------------------------------------

    public function test_admin_reject_sets_is_approved_false_and_appears_in_rejected_tab(): void
    {
        $customer = $this->customerUser();
        $item     = $this->makeSelesaiOrderItem($customer);
        $review   = $this->seedReview($item, $customer, null);

        $this->actingAs($this->adminUser())
             ->patch(route('admin.reviews.reject', $review))
             ->assertRedirect();

        $this->assertFalse($review->fresh()->is_approved,
            'Rejected review must have is_approved = false.');

        $this->actingAs($this->adminUser())
             ->get(route('admin.reviews.index', ['status' => 'rejected']))
             ->assertViewHas('reviews', fn($reviews) => $reviews->contains('id', $review->id));
    }

    // -- Test 6: per-tab counts -----------------------------------------------

    public function test_admin_index_counts_correctly_reflect_all_three_states(): void
    {
        $customer = $this->customerUser();

        $item1 = $this->makeSelesaiOrderItem($customer);
        $item2 = $this->makeSelesaiOrderItem($customer);
        $item3 = $this->makeSelesaiOrderItem($customer);

        $this->seedReview($item1, $customer, null);  // pending
        $this->seedReview($item2, $customer, true);  // approved
        $this->seedReview($item3, $customer, false); // rejected

        $this->actingAs($this->adminUser())
             ->get(route('admin.reviews.index', ['status' => 'all']))
             ->assertOk()
             ->assertViewHas('counts', function (array $counts) {
                 return $counts['all']      === 3
                     && $counts['pending']  === 1
                     && $counts['approved'] === 1
                     && $counts['rejected'] === 1;
             });
    }

    // -- Test 7: legacy false rows stay in rejected (backward compat) ----------

    public function test_legacy_false_review_stays_in_rejected_tab(): void
    {
        $customer = $this->customerUser();
        $item     = $this->makeSelesaiOrderItem($customer);
        $review   = $this->seedReview($item, $customer, false);

        $this->actingAs($this->adminUser())
             ->get(route('admin.reviews.index', ['status' => 'rejected']))
             ->assertOk()
             ->assertViewHas('reviews', fn($reviews) => $reviews->contains('id', $review->id));

        $this->actingAs($this->adminUser())
             ->get(route('admin.reviews.index', ['status' => 'pending']))
             ->assertViewHas('reviews', fn($reviews) => ! $reviews->contains('id', $review->id));
    }
}