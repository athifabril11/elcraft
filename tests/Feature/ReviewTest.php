<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Order $order;
    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $address = $this->user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'John Doe',
            'phone'          => '081234567890',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Kecamatan',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Merdeka No. 10',
        ]);

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

        $this->order = Order::create([
            'order_number' => 'ELCRAFT-REVIEW123',
            'user_id'      => $this->user->id,
            'address_id'   => $address->id,
            'subtotal'     => 50000,
            'total_amount' => 50000,
            'status'       => 'selesai', // Selesai status
        ]);

        $this->orderItem = $this->order->items()->create([
            'product_id'   => $this->product->id,
            'product_name' => $this->product->name,
            'price'        => 50000,
            'quantity'     => 1,
            'subtotal'     => 50000,
        ]);
    }

    public function test_guest_cannot_submit_review(): void
    {
        $response = $this->post(route('reviews.store'), [
            'order_item_id' => $this->orderItem->id,
            'rating'        => 5,
            'comment'       => 'Sangat bagus!',
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseEmpty('reviews');
    }

    public function test_user_cannot_review_unpurchased_product(): void
    {
        // Another order item that doesn't belong to a delivered order of this user
        $otherUser = User::factory()->create();
        $otherAddress = $otherUser->addresses()->create([
            'label'          => 'Kantor',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081234567891',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Kecamatan',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Kantor No. 20',
        ]);
        $otherOrder = Order::create([
            'order_number' => 'ELCRAFT-OTHER-123',
            'user_id'      => $otherUser->id,
            'address_id'   => $otherAddress->id,
            'subtotal'     => 50000,
            'total_amount' => 50000,
            'status'       => 'selesai',
        ]);
        $otherOrderItem = $otherOrder->items()->create([
            'product_id'   => $this->product->id,
            'product_name' => $this->product->name,
            'price'        => 50000,
            'quantity'     => 1,
            'subtotal'     => 50000,
        ]);

        $response = $this
            ->actingAs($this->user)
            ->from('/orders/' . $this->order->order_number)
            ->post(route('reviews.store'), [
                'order_item_id' => $otherOrderItem->id,
                'rating'        => 5,
                'comment'       => 'Mencoba meretas review!',
            ]);

        $response->assertRedirect('/orders/' . $this->order->order_number);
        $response->assertSessionHas('error');
        $this->assertDatabaseEmpty('reviews');
    }

    public function test_user_cannot_review_undelivered_order(): void
    {
        $this->order->update(['status' => 'paid']); // Paid but not delivered yet

        $response = $this
            ->actingAs($this->user)
            ->from('/orders/' . $this->order->order_number)
            ->post(route('reviews.store'), [
                'order_item_id' => $this->orderItem->id,
                'rating'        => 5,
                'comment'       => 'Belum sampai barangnya tapi mau review',
            ]);

        $response->assertRedirect('/orders/' . $this->order->order_number);
        $response->assertSessionHas('error');
        $this->assertDatabaseEmpty('reviews');
    }

    public function test_user_can_submit_valid_review_with_optional_photo(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('review_photo.jpg');

        $response = $this
            ->actingAs($this->user)
            ->from('/orders/' . $this->order->order_number)
            ->post(route('reviews.store'), [
                'order_item_id' => $this->orderItem->id,
                'rating'        => 4,
                'comment'       => 'Barang mewah dan sangat memuaskan.',
                'image'         => $file,
            ]);

        $response->assertRedirect('/orders/' . $this->order->order_number);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reviews', [
            'user_id'       => $this->user->id,
            'order_item_id' => $this->orderItem->id,
            'rating'        => 4,
            'comment'       => 'Barang mewah dan sangat memuaskan.',
            'is_approved'   => false,
        ]);

        $review = Review::first();
        $this->assertNotNull($review->image);
        Storage::disk('public')->assertExists($review->image);

        // Verify product averages did not update yet (since is_approved is false)
        $this->product->refresh();
        $this->assertEquals(0.0, $this->product->rating_avg);
        $this->assertEquals(0, $this->product->rating_count);
    }

    public function test_observer_updates_rating_upon_approval(): void
    {
        $review = Review::create([
            'product_id'    => $this->product->id,
            'user_id'       => $this->user->id,
            'order_item_id' => $this->orderItem->id,
            'rating'        => 5,
            'comment'       => 'Luar biasa!',
            'is_approved'   => false,
        ]);

        // Rating average/count should be 0.0/0 since it is not approved yet
        $this->product->refresh();
        $this->assertEquals(0.0, $this->product->rating_avg);
        $this->assertEquals(0, $this->product->rating_count);

        // Approve the review
        $review->update(['is_approved' => true]);

        // Should now be updated to 5.0 and count 1
        $this->product->refresh();
        $this->assertEquals(5.0, $this->product->rating_avg);
        $this->assertEquals(1, $this->product->rating_count);

        // Add a second approved review
        $otherUser = User::factory()->create();
        $otherAddress = $otherUser->addresses()->create([
            'label'          => 'Rumah',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081234567891',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Kecamatan',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Kantor No. 20',
        ]);
        $otherOrder = Order::create([
            'order_number' => 'ELCRAFT-OTHER-456',
            'user_id'      => $otherUser->id,
            'address_id'   => $otherAddress->id,
            'subtotal'     => 50000,
            'total_amount' => 50000,
            'status'       => 'selesai',
        ]);
        $otherOrderItem = $otherOrder->items()->create([
            'product_id'   => $this->product->id,
            'product_name' => $this->product->name,
            'price'        => 50000,
            'quantity'     => 1,
            'subtotal'     => 50000,
        ]);

        $review2 = Review::create([
            'product_id'    => $this->product->id,
            'user_id'       => $otherUser->id,
            'order_item_id' => $otherOrderItem->id,
            'rating'        => 3,
            'comment'       => 'Biasa saja.',
            'is_approved'   => true, // E.g. created as approved (e.g. from admin panel or via seeder)
        ]);

        $this->product->refresh();
        // Average of 5 and 3 is 4
        $this->assertEquals(4.0, $this->product->rating_avg);
        $this->assertEquals(2, $this->product->rating_count);
    }
}
