<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Category;
use App\Models\Product;
use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderPendingActionsTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderForUser(User $user, string $status = 'pending', ?\Carbon\Carbon $expiredAt = null): Order
    {
        $address = Address::create([
            'user_id'        => $user->id,
            'label'          => 'Rumah',
            'recipient_name' => $user->name,
            'phone'          => '08123456789',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40132',
            'full_address'   => 'Jl. Dago No. 10',
            'is_default'     => true,
        ]);

        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Emas',
            'slug'        => 'kalung-emas',
            'price'       => 100000,
            'stock'       => 10,
            'weight'      => 50,
            'is_active'   => true,
        ]);

        $order = Order::create([
            'order_number'     => 'ELCRAFT-TEST-' . uniqid(),
            'user_id'          => $user->id,
            'address_id'       => $address->id,
            'subtotal'         => 100000,
            'discount_amount'  => 0,
            'voucher_discount' => 0,
            'shipping_cost'    => 10000,
            'courier'          => 'jne',
            'courier_service'  => 'REG',
            'total_amount'     => 110000,
            'status'           => $status,
        ]);

        $order->items()->create([
            'product_id'      => $product->id,
            'product_name'    => $product->name,
            'price'           => $product->price,
            'discount_amount' => 0,
            'quantity'        => 1,
            'subtotal'        => 100000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'payment_method'    => 'snap',
            'amount'            => 110000,
            'status'            => 'pending',
            'snap_token'        => 'snap-token-test-xyz',
            'expired_at'        => $expiredAt ?? now()->addHours(24),
        ]);

        return $order;
    }

    public function test_customer_can_see_pending_order_countdown_and_action_buttons_on_orders_index(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        $response = $this->actingAs($user)->get(route('orders.index'));

        $response->assertOk();
        $response->assertSee('Sisa Waktu:');
        $response->assertSee('Bayar Sekarang');
        $response->assertSee('Batalkan');
        $response->assertSee($order->order_number);
    }

    public function test_expired_pending_order_hides_payment_and_cancel_buttons_on_orders_index(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending', now()->subMinutes(5));

        $response = $this->actingAs($user)->get(route('orders.index'));

        $response->assertOk();
        $response->assertSee('Pembayaran Kedaluwarsa');
        $response->assertDontSee('Bayar Sekarang');
        $response->assertDontSee('Batalkan');
        $response->assertDontSee('Sisa Waktu');
    }

    public function test_customer_can_see_pending_order_countdown_and_action_buttons_on_order_show(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        $response = $this->actingAs($user)->get(route('orders.show', $order->order_number));

        $response->assertOk();
        $response->assertSee('Menunggu Pembayaran');
        $response->assertSee('Sisa Waktu');
        $response->assertSee('Bayar Sekarang');
        $response->assertSee('Batalkan Pesanan');
    }

    public function test_expired_pending_order_hides_payment_and_cancel_buttons_on_order_show(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending', now()->subMinutes(5));

        $response = $this->actingAs($user)->get(route('orders.show', $order->order_number));

        $response->assertOk();
        $response->assertSee('Pembayaran Kedaluwarsa');
        $response->assertDontSee('Bayar Sekarang');
        $response->assertDontSee('Batalkan Pesanan');
        $response->assertDontSee('Sisa Waktu');
    }

    public function test_customer_can_cancel_pending_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        \Illuminate\Support\Facades\Http::fake([
            '*/v2/*/cancel' => \Illuminate\Support\Facades\Http::response(
                ['status_code' => '200', 'status_message' => 'Success, transaction is canceled'],
                200
            ),
        ]);

        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));

        $response->assertRedirect();
        $this->assertEquals('dibatalkan', $order->fresh()->status);
        $this->assertEquals('failed', $order->fresh()->payment->status);
    }

    public function test_customer_cannot_cancel_non_pending_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'paid');

        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));

        $response->assertRedirect();
        $this->assertEquals('paid', $order->fresh()->status);
    }

    public function test_customer_can_resume_payment_and_receive_snap_token(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        $response = $this->actingAs($user)->getJson(route('orders.resume-payment', $order->order_number));

        $response->assertOk();
        $response->assertJson([
            'expired'    => false,
            'snap_token' => 'snap-token-test-xyz',
        ]);
    }

    public function test_webhook_pending_syncs_expiry_time_and_payment_type(): void
    {        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        $midtransMock = $this->mock(\App\Services\MidtransService::class);
        $midtransMock->shouldReceive('verifySignature')->once()->andReturn(true);

        $specificExpiry = '2026-10-05 18:30:00';

        $response = $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $order->order_number,
            'transaction_status' => 'pending',
            'payment_type'       => 'bca_va',
            'expiry_time'        => $specificExpiry,
        ]);

        $response->assertOk();
        $this->assertEquals('bca_va', $order->fresh()->payment->payment_type);
        $this->assertEquals('2026-10-05 18:30:00', $order->fresh()->payment->expired_at->format('Y-m-d H:i:s'));
    }

    public function test_cancelled_order_shows_reorder_button_on_index_and_show(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'dibatalkan');

        $indexResponse = $this->actingAs($user)->get(route('orders.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Beli Lagi');
        $indexResponse->assertDontSee('Ganti / Bayar');

        $showResponse = $this->actingAs($user)->get(route('orders.show', $order->order_number));
        $showResponse->assertOk();
        $showResponse->assertSee('Beli Lagi');
        $showResponse->assertDontSee('Ganti / Bayar');
    }

    public function test_pending_order_does_not_show_reorder_button(): void
    {
        $user = User::factory()->create();
        $this->createOrderForUser($user, 'pending');

        $response = $this->actingAs($user)->get(route('orders.index'));

        $response->assertOk();
        $response->assertDontSee('Beli Lagi');
    }

    public function test_customer_can_reorder_from_cancelled_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'dibatalkan');

        $response = $this->actingAs($user)->post(route('orders.reorder', $order->order_number));

        $response->assertRedirect(route('cart.index'));

        $cart = $user->fresh()->cart;
        $this->assertNotNull($cart);
        $this->assertCount(1, $cart->items);
        $this->assertEquals($order->items->first()->product_id, $cart->items->first()->product_id);
        $this->assertEquals(1, $cart->items->first()->quantity);
    }

    public function test_reorder_appends_to_existing_cart_item(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'selesai');

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $order->items->first()->product_id,
            'variant_id' => null,
            'quantity'   => 2,
        ]);

        $response = $this->actingAs($user)->post(route('orders.reorder', $order->order_number));

        $response->assertRedirect(route('cart.index'));
        $this->assertEquals(1, $cart->fresh()->items->count());
        $this->assertEquals(3, $cart->fresh()->items->first()->quantity);
    }

    public function test_reorder_fails_when_product_out_of_stock(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'dibatalkan');
        $order->items->first()->product->update(['stock' => 0, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('orders.reorder', $order->order_number));

        $response->assertRedirect();
        $this->assertNull($user->fresh()->cart);
    }

    /** Helper: buat produk aktif + taruh di cart user dengan qty & seleksi tertentu. */
    private function makeProductInCart(User $user, string $suffix, int $stock, bool $selected, int $qty = 1): Product
    {
        $category = Category::create(['name' => 'Umum', 'slug' => 'umum-' . $suffix, 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Produk ' . $suffix,
            'slug'        => 'produk-' . $suffix,
            'price'       => 25000,
            'stock'       => $stock,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $user->getOrCreateCart()->items()->create([
            'product_id'  => $product->id,
            'variant_id'  => null,
            'quantity'    => $qty,
            'is_selected' => $selected,
        ]);

        return $product;
    }

    public function test_reorder_deselects_other_selected_items_and_redirects_to_cart(): void
    {
        // (a) Cart berisi A & B (dua-duanya terpilih); reorder order berisi C:
        //     A & B menjadi tidak terpilih, C terpilih, redirect ke cart.index.
        $user = User::factory()->create();
        $productA = $this->makeProductInCart($user, 'aaa', 5, true);
        $productB = $this->makeProductInCart($user, 'bbb', 5, true);
        $order = $this->createOrderForUser($user, 'dibatalkan');
        $productC = $order->items->first()->product;

        $response = $this->actingAs($user)->post(route('orders.reorder', $order->order_number));

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('success');

        $cart = $user->fresh()->cart;
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $productA->id, 'is_selected' => false]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $productB->id, 'is_selected' => false]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $productC->id, 'is_selected' => true]);
    }

    public function test_reorder_merges_quantity_capped_by_stock_and_selects_line(): void
    {
        // (b) Produk & varian sama sudah ada di cart: jumlah merge (capped stok) dan line terpilih.
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'dibatalkan');
        $product = $order->items->first()->product;
        $product->update(['stock' => 2]); // existing 2 + reorder 1 > stok 2 → cap di 2

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id'  => $product->id,
            'variant_id'  => null,
            'quantity'    => 2,
            'is_selected' => false,
        ]);

        $response = $this->actingAs($user)->post(route('orders.reorder', $order->order_number));

        $response->assertRedirect(route('cart.index'));

        $merged = $cart->fresh()->allItems()->where('product_id', $product->id)->first();
        $this->assertEquals(2, $merged->quantity, 'Quantity merge harus dibatasi stok');
        $this->assertTrue((bool) $merged->is_selected, 'Line hasil reorder harus terpilih');
    }

    public function test_reorder_all_unavailable_keeps_existing_selections(): void
    {
        // (c) Semua item order tidak tersedia: error response, seleksi cart lama tidak berubah.
        $user = User::factory()->create();
        $productA = $this->makeProductInCart($user, 'aaa', 5, true);
        $order = $this->createOrderForUser($user, 'dibatalkan');
        $order->items->first()->product->update(['stock' => 0]);

        $response = $this->actingAs($user)->post(route('orders.reorder', $order->order_number));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $cart = $user->fresh()->cart;
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $productA->id, 'is_selected' => true]);
        $this->assertEquals(1, $cart->allItems()->count(), 'Tidak boleh ada line baru saat semua item habis');
    }

    public function test_reorder_flash_mentions_skipped_product(): void
    {
        // (d) Satu item tersedia, satu stok habis: flash menyebut produk yang dilewati.
        $user = User::factory()->create();
        $order = $this->createOrderForUser($user, 'dibatalkan'); // item tersedia

        $category = Category::create(['name' => 'Stok', 'slug' => 'stok-habis-' . uniqid(), 'is_active' => true]);
        $outOfStock = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Habis Total',
            'slug'        => 'kalung-habis-' . uniqid(),
            'price'       => 30000,
            'stock'       => 0,
            'weight'      => 10,
            'is_active'   => true,
        ]);
        $order->items()->create([
            'product_id'      => $outOfStock->id,
            'product_name'    => $outOfStock->name,
            'price'           => $outOfStock->price,
            'discount_amount' => 0,
            'quantity'        => 1,
            'subtotal'        => $outOfStock->price,
        ]);

        $response = $this->actingAs($user)->post(route('orders.reorder', $order->order_number));

        $response->assertRedirect(route('cart.index'));
        $success = session('success');
        $this->assertNotNull($success);
        $this->assertStringContainsString('dilewati', $success, 'Flash harus menyebut produk yang dilewati');
        $this->assertStringContainsString('1', $success, 'Flash harus menyebut jumlah produk dilewati');
    }

    public function test_reorder_other_users_order_returns_404(): void
    {
        // (e) Order user lain tetap 404 (ownership check tidak berubah).
        $owner = User::factory()->create();
        $order = $this->createOrderForUser($owner, 'dibatalkan');

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post(route('orders.reorder', $order->order_number))
            ->assertNotFound();

        $this->assertNull($intruder->fresh()->cart);
    }
}
