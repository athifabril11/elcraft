<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Address;
use App\Services\MidtransService;
use App\Services\ShippingService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_active_cart(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name' => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address' => 'Jl. Merdeka No. 10',
                'city' => 'Bandung',
                'postal_code' => '40115',
            ]);

        $response->assertStatus(422);
    }

    public function test_checkout_can_create_snap_token(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Anting Emas',
            'slug' => 'anting-emas',
            'price' => 50000,
            'stock' => 10,
            'weight' => 5,
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Mock ShippingService — returns a verified cost without calling the real API
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 23000, 'etd' => '2-3'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        // Mock Midtrans Service
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturn(['token' => 'dummy_snap_token', 'redirect_url' => 'https://dummy.url']);
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address'    => 'Jl. Merdeka No. 10',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40115',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'gopay',
                'shipping_cost'   => 0, // tampered client value — server must ignore this
                'notes'           => 'Tolong dibungkus kado',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['snap_token', 'order_id']);

        // total_amount = subtotal (50000 x 2) + server-verified shipping (23000) = 123000
        // The client-submitted shipping_cost=0 must have been IGNORED
        $this->assertDatabaseHas('orders', [
            'user_id'      => $user->id,
            'total_amount' => 123000,
            'shipping_cost' => 23000,
            'status'       => 'pending',
            'notes'        => 'Tolong dibungkus kado',
        ]);

        $this->assertDatabaseHas('payments', [
            'amount'     => 123000,
            'status'     => 'pending',
            'snap_token' => 'dummy_snap_token',
        ]);
    }

    public function test_cart_is_emptied_when_snap_token_is_created(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Gelang Perak',
            'slug' => 'gelang-perak',
            'price' => 75000,
            'stock' => 5,
            'weight' => 100,
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 10000, 'etd' => '2'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturn(['token' => 'dummy_snap_token', 'redirect_url' => 'https://dummy.url']);
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address'    => 'Jl. Merdeka No. 10',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40115',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'snap',
            ]);

        $response->assertOk()->assertJsonStructure(['snap_token', 'order_id']);

        // Order pending tetap ada (untuk dibayar lagi via Pesanan Saya)
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);

        // Keranjang harus KOSONG sekarang — item sudah menjadi order
        $this->assertSame(0, $cart->items()->count(), 'Cart should be emptied after snap token creation');
    }

    public function test_snap_http_failure_cancels_order_and_keeps_cart(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Cincin', 'slug' => 'cincin', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cincin Emas',
            'slug' => 'cincin-emas',
            'price' => 100000,
            'stock' => 3,
            'weight' => 50,
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 10000, 'etd' => '2'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andThrow(new \Exception('Midtrans down'));
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address'    => 'Jl. Merdeka No. 10',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40115',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'snap',
            ]);

        $response->assertStatus(500);

        // Keranjang harus tetap utuh
        $this->assertSame(1, $cart->items()->count(), 'Cart must be preserved when Midtrans fails');
        // Order dibuat di Txn A lalu dibatalkan karena Snap gagal
        $this->assertSame(1, Order::where('user_id', $user->id)->count());
        $order = Order::where('user_id', $user->id)->first();
        $this->assertSame('dibatalkan', $order->status);
        // Baris Payment TIDAK dibuat
        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
    }

    public function test_snap_failure_with_failing_stock_restore_leaves_order_pending(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Cincin', 'slug' => 'cincin-restore', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cincin Emas',
            'slug' => 'cincin-emas-restore',
            'price' => 100000,
            'stock' => 3,
            'weight' => 50,
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 10000, 'etd' => '2'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andThrow(new \Exception('Midtrans down'));
        $this->app->instance(MidtransService::class, $midtransMock);

        // Restore stok DIBUAT GAGAL: emergency cancel harus menggulung SELURUH
        // blok pembatalan — order tidak boleh berstatus 'dibatalkan' sementara
        // stok belum dipulihkan (baik dua-duanya terpasang atau tidak sama sekali).
        $stockMock = Mockery::mock(StockService::class . '[restoreForOrder]');
        $stockMock->shouldReceive('restoreForOrder')
            ->andThrow(new \RuntimeException('Gagal memulihkan stok'));
        $this->app->instance(StockService::class, $stockMock);

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address'    => 'Jl. Merdeka No. 10',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40115',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'snap',
            ]);

        $response->assertStatus(500);

        $order = Order::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(
            'pending',
            $order->status,
            'Restore gagal → seluruh blok emergency cancel harus rollback: order TIDAK boleh "dibatalkan" dengan stok belum pulih'
        );
        $this->assertSame(2, $product->refresh()->stock, 'Stok tetap ter-reserve untuk order yang masih pending');
        $this->assertSame(1, $cart->items()->count(), 'Keranjang tetap utuh');
    }

    public function test_full_address_over_255_chars_is_rejected_with_validation_error(): void
    {
        $user = User::factory()->create();

        // 300 karakter — lolos kolom TEXT tapi harus ditolak validasi (422,
        // bukan 500) karena CheckoutController memberi batas max:255.
        $longAddress = str_repeat('a', 300);

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address'    => $longAddress,
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40115',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'snap',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['full_address']);
    }

    public function test_checkout_validates_stock(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Anting Emas',
            'slug' => 'anting-emas',
            'price' => 50000,
            'stock' => 1,
            'weight' => 5,
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name' => 'John Doe',
                'recipient_phone' => '081234567890',
                'full_address' => 'Jl. Merdeka No. 10',
                'city' => 'Bandung',
                'postal_code' => '40115',
            ]);

        $response->assertStatus(422);
    }

    public function test_webhook_payment_success(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Anting Emas',
            'slug' => 'anting-emas',
            'price' => 50000,
            'stock' => 10,
            'weight' => 5,
            'is_active' => true,
        ]);

        $address = $user->addresses()->create([
            'label' => 'Utama',
            'recipient_name' => 'John Doe',
            'phone' => '081234567890',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'district' => 'Kecamatan',
            'postal_code' => '40115',
            'full_address' => 'Jl. Merdeka No. 10',
        ]);

        $order = Order::create([
            'order_number' => 'ELCRAFT-TEST1234',
            'user_id' => $user->id,
            'address_id' => $address->id,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 50000,
            'quantity' => 2,
            'subtotal' => 100000,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount' => 100000,
            'status' => 'pending',
        ]);

        // Mock Webhook signature check
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this->postJson(route('midtrans.webhook'), [
            'order_id' => 'ELCRAFT-TEST1234',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => 'dummy_signature',
            'payment_type' => 'gopay',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'success',
            'payment_method' => 'gopay',
        ]);

        $product->refresh();
        $this->assertEquals(10, $product->stock); // reserved at checkout; settlement doesn't touch stock
    }

    /**
     * Regression test for Midtrans HTTP 400:
     * "transaction_details.gross_amount is not equal to the sum of item_details"
     *
     * Verifies that when shipping cost AND a voucher discount are both applied,
     * the $items array passed to createSnapToken() includes SHIPPING and DISCOUNT
     * rows so that sum(price * quantity) == gross_amount exactly.
     */
    public function test_item_details_sum_equals_gross_amount_with_shipping_and_voucher(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Perak',
            'slug'        => 'kalung-perak',
            'price'       => 100000,
            'stock'       => 20,
            'weight'      => 50,
            'is_active'   => true,
        ]);

        // Cart: 2 × 100000 = subtotal 200000
        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 2,
        ]);

        // Create a valid percentage voucher: 10% off → discount = 20000
        $voucher = \App\Models\Voucher::create([
            'code'           => 'DISKON10',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'min_purchase'   => 0,
            'max_discount'   => null,
            'quota'          => 100,
            'used_count'     => 0,
            'start_date'     => now()->subDay(),
            'end_date'       => now()->addDay(),
            'is_active'      => true,
        ]);

        // Shipping: 15000
        // Expected totals:
        //   subtotal         = 200000
        //   shipping         = 15000
        //   voucher_discount = 20000   (10% of 200000)
        //   total            = 200000 + 15000 - 20000 = 195000
        //
        // Expected item_details:
        //   product row:  100000 * 2 = 200000
        //   SHIPPING row:  15000 * 1 =  15000
        //   DISCOUNT row: -20000 * 1 = -20000
        //   SUM                      = 195000  ← must equal gross_amount

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 15000, 'etd' => '2-3'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        // Capture the $order array passed to createSnapToken() for assertion
        $capturedOrder = null;
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturnUsing(function (array $order, array $customer) use (&$capturedOrder) {
                $capturedOrder = $order;
                return ['token' => 'test_snap_token_regression', 'redirect_url' => 'https://dummy.url'];
            });
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'Jane Doe',
                'recipient_phone' => '082100000001',
                'full_address'    => 'Jl. Sudirman No. 5',
                'city'            => 'Jakarta',
                'city_id'         => 151,
                'postal_code'     => '10110',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'gopay',
                'shipping_cost'   => 0,   // client value — must be ignored server-side
                'voucher_code'    => 'DISKON10',
            ]);

        $response->assertOk()->assertJsonStructure(['snap_token', 'order_id']);

        // ── Core assertion: sum of item_details == gross_amount ──────────────
        $this->assertNotNull($capturedOrder, 'createSnapToken() was not called');

        $grossAmount = $capturedOrder['total'];
        $itemDetails = $capturedOrder['items'];

        $itemSum = array_sum(array_map(
            fn($item) => $item['price'] * $item['quantity'],
            $itemDetails
        ));

        $this->assertSame(
            $grossAmount,
            $itemSum,
            sprintf(
                'Midtrans mismatch: gross_amount=%d but sum(item_details)=%d. ' .
                'Items: %s',
                $grossAmount,
                $itemSum,
                json_encode($itemDetails)
            )
        );

        // ── Verify SHIPPING row is present ───────────────────────────────────
        $shippingRow = collect($itemDetails)->firstWhere('id', 'SHIPPING');
        $this->assertNotNull($shippingRow, 'SHIPPING row missing from item_details');
        $this->assertSame(15000, $shippingRow['price']);
        $this->assertSame(1,     $shippingRow['quantity']);

        // ── Verify DISCOUNT row is present with negative price ───────────────
        $discountRow = collect($itemDetails)->firstWhere('id', 'DISCOUNT');
        $this->assertNotNull($discountRow, 'DISCOUNT row missing from item_details');
        $this->assertSame(-20000, $discountRow['price']);
        $this->assertSame(1,      $discountRow['quantity']);

        // ── Verify the order was saved with the correct total in DB ──────────
        $this->assertDatabaseHas('orders', [
            'user_id'          => $user->id,
            'total_amount'     => 195000,
            'shipping_cost'    => 15000,
            'voucher_discount' => 20000,
            'status'           => 'pending',
        ]);
    }

    /**
     * Boundary case: shipping only, no voucher.
     * sum(item_details) must still equal gross_amount.
     */
    public function test_item_details_sum_equals_gross_amount_shipping_only(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Emas',
            'slug'        => 'gelang-emas',
            'price'       => 75000,
            'stock'       => 10,
            'weight'      => 30,
            'is_active'   => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        // subtotal=75000, shipping=10000, total=85000, no discount
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'pos', 'service' => 'Paket Biasa', 'description' => 'Biasa', 'cost' => 10000, 'etd' => '3-5'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $capturedOrder = null;
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')->once()
            ->andReturnUsing(function (array $order) use (&$capturedOrder) {
                $capturedOrder = $order;
                return ['token' => 'tok_shipping_only', 'redirect_url' => 'https://x'];
            });
        $this->app->instance(MidtransService::class, $midtransMock);

        $this->actingAs($user)->postJson(route('checkout.token'), [
            'recipient_name'  => 'Test User',
            'recipient_phone' => '08100000002',
            'full_address'    => 'Jl. Test No. 1',
            'city'            => 'Surabaya',
            'city_id'         => 444,
            'postal_code'     => '60111',
            'courier'         => 'pos',
            'courier_service' => 'pos-Paket Biasa',
            'payment_method'  => 'gopay',
            'shipping_cost'   => 0,
        ])->assertOk();

        $grossAmount = $capturedOrder['total'];
        $itemSum = array_sum(array_map(
            fn($item) => $item['price'] * $item['quantity'],
            $capturedOrder['items']
        ));

        $this->assertSame($grossAmount, $itemSum,
            "Shipping-only: gross_amount=$grossAmount but sum(item_details)=$itemSum");

        $this->assertNotNull(
            collect($capturedOrder['items'])->firstWhere('id', 'SHIPPING'),
            'SHIPPING row missing when shipping > 0'
        );
        $this->assertNull(
            collect($capturedOrder['items'])->firstWhere('id', 'DISCOUNT'),
            'DISCOUNT row should not appear when no voucher is applied'
        );
    }
    /**
     * Regression test: when a user re-submits checkout while a previous
     * pending order already exists (abandoned Snap popup), the system must
     * atomically cancel the old order/payment and create exactly one new
     * pending order — no duplicate pending orders.
     *
     * Verifies:
     *  1. Old order.status → 'dibatalkan'
     *  2. Old payment.status → 'failed'
     *  3. A new order.status = 'pending' is created
     *  4. Exactly one 'pending' order exists for the user after the second attempt
     */
    public function test_existing_pending_order_is_cancelled_on_new_checkout(): void
    {
        $user     = User::factory()->create();
        $category = Category::create(['name' => 'Bros', 'slug' => 'bros', 'is_active' => true]);
        $product  = Product::create([
            'category_id' => $category->id,
            'name'        => 'Bros Emas',
            'slug'        => 'bros-emas',
            'price'       => 60000,
            'stock'       => 15,
            'weight'      => 20,
            'is_active'   => true,
        ]);

        // ── Setup: existing address (required by updateOrCreate inside transaction) ──
        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'Jane Doe',
            'phone'          => '082199999999',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Kecamatan',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Lama No. 99',
        ]);

        // ── Setup: a pre-existing pending order (simulates abandoned Snap popup) ──
        $oldOrder = Order::create([
            'order_number' => 'ELCRAFT-OLDABANDONED',
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 60000,
            'total_amount' => 60000,
            'status'       => 'pending',
        ]);

        $oldPayment = Payment::create([
            'order_id'          => $oldOrder->id,
            'midtrans_order_id' => $oldOrder->order_number,
            'amount'            => 60000,
            'status'            => 'pending',
            'snap_token'        => 'old_abandoned_snap_token',
        ]);

        // ── Setup: active cart for the second checkout attempt ──
        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        // ── Mocks: no real API calls ──────────────────────────────────────────
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 12000, 'etd' => '2'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturn(['token' => 'new_snap_token_after_cancel', 'redirect_url' => 'https://dummy.url']);
        $midtransMock->shouldReceive('cancelTransaction')
            ->once()
            ->with('ELCRAFT-OLDABANDONED')
            ->andReturn(['outcome' => 'cancelled', 'status_code' => 200, 'message' => '']);
        $this->app->instance(MidtransService::class, $midtransMock);

        // ── Act: second checkout attempt ──────────────────────────────────────
        $response = $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'Jane Doe',
                'recipient_phone' => '082199999999',
                'full_address'    => 'Jl. Lama No. 99',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40115',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'gopay',
                'shipping_cost'   => 0,
            ]);

        $response->assertOk()->assertJsonStructure(['snap_token', 'order_id']);

        // ── Assert 1: Old order is now 'dibatalkan' ───────────────────────────
        $this->assertDatabaseHas('orders', [
            'id'     => $oldOrder->id,
            'status' => 'dibatalkan',
        ]);

        // ── Assert 2: Old payment is now 'failed' ─────────────────────────────
        $this->assertDatabaseHas('payments', [
            'id'     => $oldPayment->id,
            'status' => 'failed',
        ]);

        // ── Assert 3: A NEW pending order was created ─────────────────────────
        // total = 60000 + 12000 = 72000
        $this->assertDatabaseHas('orders', [
            'user_id'      => $user->id,
            'status'       => 'pending',
            'total_amount' => 72000,
        ]);

        // ── Assert 4: Exactly ONE pending order for this user ─────────────────
        $pendingCount = Order::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $this->assertSame(1, $pendingCount,
            "Expected exactly 1 pending order after re-checkout, found {$pendingCount}");

        // ── Assert 5: New payment record exists and is NOT the old one ────────
        $this->assertDatabaseHas('payments', [
            'snap_token' => 'new_snap_token_after_cancel',
            'status'     => 'pending',
        ]);
    }

    public function test_guest_add_to_cart_returns_401_json(): void
    {
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Anting Emas',
            'slug' => 'anting-emas',
            'price' => 50000,
            'stock' => 10,
            'weight' => 5,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_add_to_cart(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kalung Berlian',
            'slug' => 'kalung-berlian',
            'price' => 150000,
            'stock' => 5,
            'weight' => 10,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'cart_count' => 1,
            ]);
    }

    public function test_guest_visiting_cart_is_redirected_with_modal_flash(): void
    {
        $response = $this->get(route('cart.index'));

        // Guest gets redirected back (to home since there's no referrer), not to /login
        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringNotContainsString('/login', $redirectUrl);
        $response->assertSessionHas('show_login_modal', true);
    }

    public function test_authenticated_user_can_view_cart_page(): void
    {
        $user = User::factory()->create();
        $response = $this
            ->actingAs($user)
            ->get(route('cart.index'));

        $response->assertOk();
        $response->assertViewHas('cart');
    }

    public function test_guest_visiting_checkout_is_redirected_with_modal_flash(): void
    {
        $response = $this->get(route('checkout.index'));

        // Guest gets redirected back, not to /login or /cart
        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringNotContainsString('/login', $redirectUrl);
        $response->assertSessionHas('show_login_modal', true);
    }

    public function test_checkout_with_existing_address_id_reuses_address_without_creating_new_one(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Cincin', 'slug' => 'cincin', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Cincin Perak',
            'slug'        => 'cincin-perak',
            'price'       => 50000,
            'stock'       => 5,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $existingAddress = Address::create([
            'user_id'        => $user->id,
            'label'          => 'Rumahku',
            'recipient_name' => 'Budi Santoso',
            'phone'          => '08123456789',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40132',
            'full_address'   => 'Jl. Dago No. 123',
            'is_default'     => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $shippingMock = $this->mock(\App\Services\ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                [
                    'code'        => 'jne',
                    'service'     => 'REG',
                    'description' => 'Layanan Reguler',
                    'cost'        => 10000,
                    'etd'         => '2-3 hari',
                ],
            ]);

        $midtransMock = $this->mock(\App\Services\MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturn([
                'token'        => 'test-snap-token-12345',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test-snap-token-12345',
            ]);

        $response = $this->actingAs($user)->postJson(route('checkout.token'), [
            'address_id'      => $existingAddress->id,
            'recipient_name'  => 'Budi Santoso (Updated)',
            'recipient_phone' => '08123456789',
            'full_address'    => 'Jl. Dago No. 123',
            'city'            => 'Bandung',
            'city_id'         => 23,
            'province_id'     => 9,
            'postal_code'     => '40132',
            'courier'         => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method'  => 'snap',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['snap_token', 'order_id']);

        // Assert user STILL only has 1 address in database
        $this->assertEquals(1, $user->addresses()->count());
        $this->assertEquals('Budi Santoso (Updated)', $existingAddress->fresh()->recipient_name);
        $this->assertEquals('Rumahku', $existingAddress->fresh()->label);
        $this->assertTrue((bool) $existingAddress->fresh()->is_default);
    }
}
