<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ReturnStockRestoreTest
 *
 * Under the reserve-at-checkout stock model, goods returned via an
 * approved 'tukar_barang' return must be restocked exactly once when the
 * return reaches 'selesai' (laravel-ecommerce-engine: restore stock in a
 * DB transaction, idempotent via the status-transition guard).
 *
 * 'refund' returns do NOT restock — the money is returned instead.
 */
class ReturnStockRestoreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    /** @return array{0: ReturnRequest, 1: Product} */
    private function makeTukarReturn(int $stock, int $orderQty, int $returnQty, string $resolution = 'tukar_barang'): array
    {
        $user = $this->customer();
        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Restock No. 1',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-RSTK-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 150000,
            'status'       => 'selesai',
        ]);

        \App\Models\Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 150000,
            'status'            => 'success',
        ]);

        $cat = Category::create(['name' => 'Bro', 'slug' => 'bro-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Bro Emas',
            'slug'        => 'bro-emas-' . uniqid(),
            'price'       => 150000,
            'stock'       => $stock, // remaining stock AFTER the order reservation
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $item = OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => $product->price,
            'quantity'     => $orderQty,
            'subtotal'     => $product->price * $orderQty,
        ]);

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-RSTK-' . strtoupper(substr(uniqid(), -5)),
            'resolution_type' => $resolution,
            'reason'          => 'Barang tidak sesuai deskripsi.',
            'status'          => 'diajukan',
        ]);

        ReturnItem::create([
            'return_id'     => $ret->id,
            'order_item_id' => $item->id,
            'quantity'      => $returnQty,
        ]);

        return [$ret, $product];
    }

    private function approveAndComplete(ReturnRequest $ret, User $admin): void
    {
        $this->actingAs($admin)
            ->patch(route('admin.returns.approve', $ret->id))
            ->assertRedirect();
        $this->actingAs($admin)
            ->patch(route('admin.returns.advance', $ret->id))
            ->assertRedirect(); // disetujui -> diproses
        $this->actingAs($admin)
            ->patch(route('admin.returns.advance', $ret->id))
            ->assertRedirect(); // diproses -> selesai (+ restock)
    }

    public function test_tukar_barang_return_completion_restores_stock(): void
    {
        // Order qty 2 already reserved (stock shows 8); return all 2 units.
        [$ret, $product] = $this->makeTukarReturn(stock: 8, orderQty: 2, returnQty: 2);
        $admin = $this->admin();

        $this->approveAndComplete($ret, $admin);

        $this->assertDatabaseHas('returns', ['id' => $ret->id, 'status' => 'selesai']);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_partial_tukar_barang_return_restores_only_returned_quantity(): void
    {
        // Order qty 3; customer returns only 1 unit.
        [$ret, $product] = $this->makeTukarReturn(stock: 7, orderQty: 3, returnQty: 1);
        $admin = $this->admin();

        $this->approveAndComplete($ret, $admin);

        $this->assertSame(8, (int) $product->fresh()->stock);
    }

    public function test_refund_return_completion_does_not_restock(): void
    {
        [$ret, $product] = $this->makeTukarReturn(stock: 8, orderQty: 2, returnQty: 2, resolution: 'refund');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.returns.approve', $ret->id), ['refund_amount' => 1000])
            ->assertRedirect();

        // Refund must be executed before a refund-type return can complete
        $this->actingAs($admin)
            ->patch(route('admin.returns.refund_manual', $ret->id), ['manual_notes' => 'Transfer bank bukti #123'])
            ->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.returns.advance', $ret->id))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.returns.advance', $ret->id))->assertRedirect();

        $this->assertDatabaseHas('returns', ['id' => $ret->id, 'status' => 'selesai']);
        $this->assertSame(8, (int) $product->fresh()->stock);
    }

    public function test_re_advance_after_completion_does_not_double_restore(): void
    {
        [$ret, $product] = $this->makeTukarReturn(stock: 8, orderQty: 2, returnQty: 2);
        $admin = $this->admin();

        $this->approveAndComplete($ret, $admin);
        $this->assertSame(10, (int) $product->fresh()->stock);

        // Extra advance after 'selesai' must be rejected — no double restock
        $this->actingAs($admin)
            ->patch(route('admin.returns.advance', $ret->id))
            ->assertSessionHas('error');

        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_rejected_return_does_not_restock(): void
    {
        [$ret, $product] = $this->makeTukarReturn(stock: 8, orderQty: 2, returnQty: 2);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.returns.reject', $ret->id), ['admin_notes' => 'Melewati batas waktu retur.'])
            ->assertRedirect();

        $this->assertDatabaseHas('returns', ['id' => $ret->id, 'status' => 'ditolak']);
        $this->assertSame(8, (int) $product->fresh()->stock);
    }

    public function test_tukar_barang_return_with_variant_syncs_parent_stock(): void
    {
        $user = $this->customer();
        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Varian No. 9',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-RSTKV-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 200000,
            'total_amount' => 200000,
            'status'       => 'selesai',
        ]);

        $cat = Category::create(['name' => 'Cincin', 'slug' => 'cincin-rstk-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Cincin Restock',
            'slug'        => 'cincin-rstk-' . uniqid(),
            'price'       => 100000,
            'stock'       => 15, // v1: 5 remaining + v2: 10
            'weight'      => 10,
            'is_active'   => true,
        ]);
        $v1 = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Ukuran',
            'variant_name' => 'Size 5',
            'stock'        => 5,
            'is_active'    => true,
        ]);
        ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Ukuran',
            'variant_name' => 'Size 6',
            'stock'        => 10,
            'is_active'    => true,
        ]);

        $item = OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'variant_id'   => $v1->id,
            'product_name' => $product->name,
            'variant_name' => $v1->variant_name,
            'price'        => 100000,
            'quantity'     => 2,
            'subtotal'     => 200000,
        ]);

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-RSTKV-' . strtoupper(substr(uniqid(), -5)),
            'resolution_type' => 'tukar_barang',
            'reason'          => 'Ukuran kekecilan.',
            'status'          => 'diajukan',
        ]);

        ReturnItem::create([
            'return_id'     => $ret->id,
            'order_item_id' => $item->id,
            'quantity'      => 2,
        ]);

        $admin = $this->admin();
        $this->approveAndComplete($ret, $admin);

        $this->assertSame(7, (int) $v1->fresh()->stock); // 5 + 2
        $this->assertSame(17, (int) $product->fresh()->stock); // 7 + 10
    }
}
