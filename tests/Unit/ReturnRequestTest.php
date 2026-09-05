<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ReturnRequestTest
 *
 * Covers the schema-level behaviour of the returns + return_items tables
 * and the Eloquent relationship graph. Includes tests for the three new
 * columns added in the second migration: return_number, resolution_type,
 * refund_amount.
 */
class ReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function makeOrder(User $user): Order
    {
        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Test No. 1',
        ]);

        return Order::create([
            'order_number' => 'ORD-RET-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 150000,
            'status'       => 'selesai',
        ]);
    }

    private function makeOrderItem(Order $order): OrderItem
    {
        $category = Category::create([
            'name' => 'Cat ' . uniqid(),
            'slug' => 'cat-' . uniqid(),
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Produk ' . uniqid(),
            'slug'        => 'produk-' . uniqid(),
            'price'       => 150000,
            'stock'       => 10,
            'weight'      => 100,
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

    /**
     * Helper: create a ReturnRequest with all required NOT NULL columns.
     * return_number is unique per call via uniqid().
     */
    private function makeReturnRequest(
        Order $order,
        string $resolutionType = 'refund',
        ?string $returnNumber = null,
    ): ReturnRequest {
        return ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $order->user_id,
            'return_number'   => $returnNumber ?? ('RET-TEST-' . uniqid()),
            'resolution_type' => $resolutionType,
            'reason'          => 'Produk cacat saat diterima.',
            'status'          => 'diajukan',
        ]);
    }

    // ── Original tests (updated for new NOT NULL columns) ─────────────────────

    public function test_return_request_can_be_created_for_an_order(): void
    {
        $user   = $this->makeUser();
        $order  = $this->makeOrder($user);
        $return = $this->makeReturnRequest($order);

        $this->assertDatabaseHas('returns', [
            'id'       => $return->id,
            'order_id' => $order->id,
            'status'   => 'diajukan',
        ]);
    }

    public function test_same_order_cannot_have_two_return_requests(): void
    {
        $this->expectException(QueryException::class);

        $user  = $this->makeUser();
        $order = $this->makeOrder($user);

        $this->makeReturnRequest($order); // first — ok
        $this->makeReturnRequest($order); // second — must throw (unique order_id)
    }

    public function test_return_items_relationship_returns_associated_items(): void
    {
        $user   = $this->makeUser();
        $order  = $this->makeOrder($user);
        $item1  = $this->makeOrderItem($order);
        $item2  = $this->makeOrderItem($order);
        $return = $this->makeReturnRequest($order);

        ReturnItem::create([
            'return_id'     => $return->id,
            'order_item_id' => $item1->id,
            'quantity'      => 1,
        ]);
        ReturnItem::create([
            'return_id'     => $return->id,
            'order_item_id' => $item2->id,
            'quantity'      => 1,
        ]);

        $loaded = $return->items()->get();

        $this->assertCount(2, $loaded);
        $this->assertTrue($loaded->pluck('order_item_id')->contains($item1->id));
        $this->assertTrue($loaded->pluck('order_item_id')->contains($item2->id));
    }

    public function test_order_return_request_relationship_resolves_correctly(): void
    {
        $user   = $this->makeUser();
        $order  = $this->makeOrder($user);
        $return = $this->makeReturnRequest($order);

        $resolved = $order->returnRequest()->first();

        $this->assertNotNull($resolved);
        $this->assertSame($return->id, $resolved->id);
    }

    public function test_same_order_item_cannot_appear_twice_in_one_return_request(): void
    {
        $this->expectException(QueryException::class);

        $user      = $this->makeUser();
        $order     = $this->makeOrder($user);
        $orderItem = $this->makeOrderItem($order);
        $return    = $this->makeReturnRequest($order);

        ReturnItem::create([
            'return_id'     => $return->id,
            'order_item_id' => $orderItem->id,
            'quantity'      => 1,
        ]);

        // Same order item in same return — must throw (compound unique index)
        ReturnItem::create([
            'return_id'     => $return->id,
            'order_item_id' => $orderItem->id,
            'quantity'      => 1,
        ]);
    }

    public function test_default_status_is_diajukan(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user);

        $return = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $order->user_id,
            'return_number'   => 'RET-DEFAULT-' . uniqid(),
            'resolution_type' => 'refund',
            'reason'          => 'Test default status',
            // 'status' intentionally omitted
        ]);

        $this->assertSame('diajukan', $return->fresh()->status);
    }

    public function test_evidence_photo_path_is_nullable(): void
    {
        $user   = $this->makeUser();
        $order  = $this->makeOrder($user);
        $return = $this->makeReturnRequest($order);

        $this->assertNull($return->fresh()->evidence_photo_path);
    }

    // ── New tests for return_number, resolution_type, refund_amount ───────────

    public function test_return_number_must_be_unique(): void
    {
        $this->expectException(QueryException::class);

        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        $this->makeReturnRequest($this->makeOrder($user1), 'refund', 'RET-DUPE-001');
        $this->makeReturnRequest($this->makeOrder($user2), 'refund', 'RET-DUPE-001'); // must throw
    }

    public function test_resolution_type_tukar_barang_saves_correctly(): void
    {
        $user   = $this->makeUser();
        $return = $this->makeReturnRequest($this->makeOrder($user), 'tukar_barang');

        $this->assertSame('tukar_barang', $return->fresh()->resolution_type);
    }

    public function test_resolution_type_refund_saves_correctly(): void
    {
        $user   = $this->makeUser();
        $return = $this->makeReturnRequest($this->makeOrder($user), 'refund');

        $this->assertSame('refund', $return->fresh()->resolution_type);
    }

    public function test_invalid_resolution_type_raises_query_exception(): void
    {
        $this->expectException(QueryException::class);

        $user  = $this->makeUser();
        $order = $this->makeOrder($user);

        ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $order->user_id,
            'return_number'   => 'RET-INVALID-' . uniqid(),
            'resolution_type' => 'invalid_value', // not in ENUM — must throw
            'reason'          => 'Test invalid enum.',
        ]);
    }

    public function test_refund_amount_is_null_for_exchange_type(): void
    {
        $user   = $this->makeUser();
        $return = $this->makeReturnRequest($this->makeOrder($user), 'tukar_barang');

        $this->assertNull($return->fresh()->refund_amount);
    }

    public function test_refund_amount_stores_decimal_value_for_refund_type(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user);

        $return = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $order->user_id,
            'return_number'   => 'RET-REFUND-' . uniqid(),
            'resolution_type' => 'refund',
            'reason'          => 'Produk salah kirim.',
            'refund_amount'   => 125000.00,
        ]);

        $this->assertEquals('125000.00', $return->fresh()->refund_amount);
    }
}
