<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\MidtransService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Simulasi perilaku customer tak terduga / edge case
 * Menguji ketahanan sistem terhadap perilaku tidak standar
 */
class UnexpectedCustomerBehaviorTest extends TestCase
{
    use RefreshDatabase;

    /*
     * Catatan: skenario konkurensi (checkout race pada unit terakhir,
     * pengiriman webhook duplikat, dan pemakaian kuota voucher bersamaan)
     * diverifikasi manual di MySQL staging. Basis data SQLite test suite ini
     * berjalan dalam satu proses PHP, sehingga request tidak pernah berjalan
     * bersamaan dan lockForUpdate menjadi no-op — test semacam itu tidak
     * bisa berarti apa-apa di lingkungan test ini.
     */

    private function setupCustomer(): array
    {
        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $cat = Category::create(['name' => 'Test', 'slug' => 'test-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Produk Test',
            'slug' => 'produk-test-' . uniqid(),
            'price' => 100000,
            'stock' => 10,
            'weight' => 50,
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'variant_name' => 'Varian A',
            'price' => 100000,
            'stock' => 5,
            'is_active' => true,
            'variant_type' => 'size',
        ]);

        return [$user, $product, $variant];
    }

    public function test_customer_adds_to_cart_then_product_becomes_inactive_before_checkout(): void
    {
        [$user, $product, $variant] = $this->setupCustomer();

        // 1. Customer add ke cart (produk aktif)
        $this->actingAs($user)->postJson('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        // 2. Admin menonaktifkan produk
        $product->update(['is_active' => false]);

        // Gotcha testing Laravel: request cart/add meng-cache relasi cart
        // (dan product-nya) di instance $user ini. Tanpa drop, request
        // checkout berikutnya memakai model basi — bukan perilaku request
        // nyata yang selalu resolve model segar.
        $user->unsetRelation('cart');

        // 3. Customer coba checkout — harus gagal validasi is_active
        $address = $user->addresses()->create([
            'label' => 'Rumah', 'recipient_name' => $user->name, 'phone' => '08123456789',
            'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
            'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
        ]);

        // Ongkir diverifikasi server-side SEBELUM cek is_active — mock wajib ada.
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 10000, 'etd' => '2'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        // Checkout harus GAGAL sebelum menyentuh Midtrans sama sekali.
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldNotReceive('createSnapToken');
        $midtransMock->shouldNotReceive('cancelTransaction');
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this->actingAs($user)->postJson(route('checkout.token'), [
            'address_id' => $address->id,
            'recipient_name' => 'Jane Doe',
            'recipient_phone' => '08123456789',
            'full_address' => 'Jl. Test',
            'city' => 'Bandung',
            'city_id' => 23,
            'postal_code' => '40132',
            'courier' => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method' => 'gopay',
            'shipping_cost' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Salah satu produk di keranjang tidak tersedia lagi.',
            ]);

        // Tidak ada order/payment baru yang dibuat
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);

        // Stok tidak berubah (reservasi tidak pernah dijalankan)
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame(5, (int) $variant->fresh()->stock);

        // Item keranjang tidak berubah
        $this->assertSame(1, CartItem::where('product_id', $product->id)->count());
        $this->assertSame(2, (int) CartItem::where('product_id', $product->id)->value('quantity'));
    }

    public function test_customer_adds_variant_then_variant_becomes_inactive_before_checkout(): void
    {
        [$user, $product, $variant] = $this->setupCustomer();

        // Add varian ke cart
        $this->actingAs($user)->postJson('/cart/add', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        // Nonaktifkan varian
        $variant->update(['is_active' => false]);

        // Drop cache relasi cart dari request cart/add (lihat catatan pada tes
        // inactive-product) supaya request checkout memakai model segar.
        $user->unsetRelation('cart');

        $address = $user->addresses()->create([
            'label' => 'Rumah', 'recipient_name' => $user->name, 'phone' => '08123456789',
            'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
            'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
        ]);

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 10000, 'etd' => '2'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldNotReceive('createSnapToken');
        $midtransMock->shouldNotReceive('cancelTransaction');
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this->actingAs($user)->postJson(route('checkout.token'), [
            'address_id' => $address->id,
            'recipient_name' => 'Jane Doe',
            'recipient_phone' => '08123456789',
            'full_address' => 'Jl. Test',
            'city' => 'Bandung',
            'city_id' => 23,
            'postal_code' => '40132',
            'courier' => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method' => 'gopay',
            'shipping_cost' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Salah satu produk di keranjang tidak tersedia lagi.',
            ]);

        // Tidak ada order/payment baru yang dibuat
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);

        // Stok tidak berubah (reservasi tidak pernah dijalankan)
        $this->assertSame(5, (int) $variant->fresh()->stock);
        $this->assertFalse((bool) $variant->fresh()->is_active);
        $this->assertSame(10, (int) $product->fresh()->stock);

        // Item keranjang tidak berubah
        $this->assertSame(1, CartItem::where('variant_id', $variant->id)->count());
        $this->assertSame(1, (int) CartItem::where('variant_id', $variant->id)->value('quantity'));
    }

    public function test_rapid_double_click_add_to_cart_does_not_duplicate(): void
    {
        [$user, $product] = $this->setupCustomer();

        // Simulasi double-click: kirim 2 request add hampir bersamaan
        $responses = [];
        for ($i = 0; $i < 2; $i++) {
            $responses[] = $this->actingAs($user)->postJson('/cart/add', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        }

        // Kedua request harus sukses (idempotent via quantity merge)
        foreach ($responses as $r) {
            $r->assertOk();
            $r->assertJson(['success' => true]);
        }

        // Di database hanya 1 cart item dengan quantity = 2
        $cart = $user->fresh()->cart;
        $this->assertEquals(1, $cart->allItems()->count());
        $this->assertEquals(2, $cart->allItems()->first()->quantity);
    }

    public function test_customer_refreshes_payment_page_multiple_times_token_reused(): void
    {
        [$user, $product] = $this->setupCustomer();

        $address = $user->addresses()->create([
            'label' => 'Rumah', 'recipient_name' => $user->name, 'phone' => '08123456789',
            'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
            'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
        ]);

        // Buat order pending langsung (bypass checkout untuk test ini)
        $order = Order::create([
            'order_number' => 'ELCRAFT-TEST-' . uniqid(),
            'user_id' => $user->id,
            'address_id' => $address->id,
            'subtotal' => 100000,
            'total_amount' => 110000,
            'shipping_cost' => 10000,
            'courier' => 'jne',
            'courier_service' => 'REG',
            'status' => 'pending',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100000, 'subtotal' => 100000, 'product_name' => $product->name]);
        Payment::create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount' => 110000,
            'status' => 'pending',
            'snap_token' => 'test-token-' . uniqid(),
            'expired_at' => now()->addHours(24),
        ]);

        // Simulasi user refresh halaman payment (request resume-payment)
        $response2 = $this->actingAs($user)->getJson(route('orders.resume-payment', $order->order_number));
        $response2->assertOk()->assertJsonStructure(['snap_token']);

        // Refresh lagi - harus mendapat token YANG SAMA (idempotent)
        $response3 = $this->actingAs($user)->getJson(route('orders.resume-payment', $order->order_number));
        $response3->assertOk();

        $this->assertEquals($response2->json('snap_token'), $response3->json('snap_token'));
    }

    public function test_customer_cancels_order_while_webhook_settlement_arrives_race(): void
    {
        [$user, $product] = $this->setupCustomer();

        $address = $user->addresses()->create([
            'label' => 'Rumah', 'recipient_name' => $user->name, 'phone' => '08123456789',
            'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
            'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
        ]);

        // Buat order pending langsung
        $order = Order::create([
            'order_number' => 'ELCRAFT-TEST-' . uniqid(),
            'user_id' => $user->id,
            'address_id' => $address->id,
            'subtotal' => 100000,
            'total_amount' => 110000,
            'shipping_cost' => 10000,
            'courier' => 'jne',
            'courier_service' => 'REG',
            'status' => 'pending',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100000, 'subtotal' => 100000, 'product_name' => $product->name]);
        Payment::create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount' => 110000,
            'status' => 'pending',
            'snap_token' => 'test-token-' . uniqid(),
            'expired_at' => now()->addHours(24),
        ]);

        // Simulasi: webhook settlement arrive SAAT customer klik cancel
        // Webhook di-handle dulu (markAsPaid) -> status paid
        config()->set('midtrans.server_key_sandbox', 'SB-Test-Key-Race');
        $webhookPayload = [
            'order_id' => $order->order_number,
            'transaction_status' => 'settlement',
            'payment_type' => 'gopay',
            'status_code' => '200',
            'gross_amount' => (string) $order->total_amount,
            'signature_key' => hash('sha512', $order->order_number . '200' . $order->total_amount . config('midtrans.server_key_sandbox')),
        ];

        $this->postJson('/midtrans/webhook', $webhookPayload)->assertOk();

        $order->refresh();
        $this->assertEquals('paid', $order->status);

        // Sekarang customer klik cancel (seharusnya GAGAL karena sudah paid)
        $cancelResponse = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));
        $cancelResponse->assertRedirect();
        $cancelResponse->assertSessionHas('error');

        $order->refresh();
        $this->assertEquals('paid', $order->status); // Masih paid, tidak berubah
    }

    public function test_customer_tries_to_checkout_with_zero_stock_item(): void
    {
        [$user, $product] = $this->setupCustomer();

        $product->update(['stock' => 0]); // Stok habis

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => $product->price]);

        $address = $user->addresses()->create([
            'label' => 'Rumah', 'recipient_name' => $user->name, 'phone' => '08123456789',
            'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
            'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
        ]);

        // Cek stok terjadi SEBELUM ongkir dan Midtrans — keduanya tidak boleh dipanggil.
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldNotReceive('calculateCost');
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldNotReceive('createSnapToken');
        $midtransMock->shouldNotReceive('cancelTransaction');
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this->actingAs($user)->postJson(route('checkout.token'), [
            'address_id' => $address->id,
            'recipient_name' => 'Jane Doe',
            'recipient_phone' => '08123456789',
            'full_address' => 'Jl. Test',
            'city' => 'Bandung',
            'city_id' => 23,
            'postal_code' => '40132',
            'courier' => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method' => 'gopay',
            'shipping_cost' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Stok produk "Produk Test" tidak mencukupi. Tersedia: 0',
            ]);

        // Tidak ada order/payment baru yang dibuat
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);

        // Stok tidak berubah dan item keranjang tetap ada
        $this->assertSame(0, (int) $product->fresh()->stock);
        $this->assertSame(1, CartItem::where('product_id', $product->id)->count());
        $this->assertSame(1, (int) CartItem::where('product_id', $product->id)->value('quantity'));
    }

    public function test_customer_tries_to_use_expired_voucher(): void
    {
        [$user, $product] = $this->setupCustomer();

        $voucher = Voucher::create([
            'code' => 'EXPIRED10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'quota' => 10,
            'used_count' => 0,
            'min_purchase' => 50000,
            'max_discount' => 50000,
            'start_date' => now()->subDays(10)->format('Y-m-d'),
            'end_date' => now()->subDays(1)->format('Y-m-d'), // Sudah expired
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => $product->price]);

        $address = $user->addresses()->create([
            'label' => 'Rumah', 'recipient_name' => $user->name, 'phone' => '08123456789',
            'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
            'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
        ]);

        // Ongkir diverifikasi server-side SEBELUM validasi voucher.
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 10000, 'etd' => '2'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        // Checkout harus GAGAL di validasi voucher — sebelum membatalkan
        // pesanan lama atau membuat Snap token.
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldNotReceive('createSnapToken');
        $midtransMock->shouldNotReceive('cancelTransaction');
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this->actingAs($user)->postJson(route('checkout.token'), [
            'address_id' => $address->id,
            'recipient_name' => 'Jane Doe',
            'recipient_phone' => '08123456789',
            'full_address' => 'Jl. Test',
            'city' => 'Bandung',
            'city_id' => 23,
            'postal_code' => '40132',
            'courier' => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method' => 'gopay',
            'shipping_cost' => 0,
            'voucher_code' => 'EXPIRED10',
        ]);

        // Voucher expired -> checkout DITOLAK 422, bukan jalan tanpa diskon diam-diam
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Kode voucher tidak valid atau sudah habis.',
            ]);

        // Tidak ada order/payment baru; voucher tidak terpakai; stok & cart utuh
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame(1, CartItem::where('product_id', $product->id)->count());
        $this->assertSame(1, (int) CartItem::where('product_id', $product->id)->value('quantity'));
    }

    public function test_customer_manipulates_quantity_to_negative_or_zero(): void
    {
        [$user, $product] = $this->setupCustomer();

        $response = $this->actingAs($user)->postJson('/cart/add', [
            'product_id' => $product->id,
            'quantity' => -5, // Negatif
        ]);

        $response->assertStatus(422); // Validation error
        $response->assertJsonValidationErrors('quantity');
    }

    public function test_customer_tries_to_add_more_than_stock(): void
    {
        [$user, $product] = $this->setupCustomer();
        $product->update(['stock' => 3]);

        $response = $this->actingAs($user)->postJson('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 10, // Lebih dari stok
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'Stok produk tidak mencukupi.']);
    }

    public function test_customer_tries_to_access_another_users_order(): void
    {
        [$user1, $product] = $this->setupCustomer();
        $user2 = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);

        $address = $user1->addresses()->create([
            'label' => 'Rumah', 'recipient_name' => $user1->name, 'phone' => '08123456789',
            'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
            'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
        ]);

        // User1 buat order langsung
        $order = Order::create([
            'order_number' => 'ELCRAFT-TEST-' . uniqid(),
            'user_id' => $user1->id,
            'address_id' => $address->id,
            'subtotal' => 100000,
            'total_amount' => 110000,
            'shipping_cost' => 10000,
            'courier' => 'jne',
            'courier_service' => 'REG',
            'status' => 'pending',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100000, 'subtotal' => 100000, 'product_name' => $product->name]);
        Payment::create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount' => 110000,
            'status' => 'pending',
            'snap_token' => 'test-token-' . uniqid(),
            'expired_at' => now()->addHours(24),
        ]);

        // User2 coba akses order User1
        $response = $this->actingAs($user2)->get(route('orders.show', $order->order_number));
        $response->assertNotFound(); // 404, bukan 403 (idempotent guard)

        // User2 coba cancel order User1
        $response = $this->actingAs($user2)->post(route('orders.cancel', $order->order_number));
        $response->assertNotFound();
    }

    public function test_customer_tries_to_cancel_already_paid_order(): void
    {
        [$user, $product] = $this->setupCustomer();

        $address = $user->addresses()->create([
            'label' => 'Rumah', 'recipient_name' => $user->name, 'phone' => '08123456789',
            'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
            'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
        ]);

        // Buat order pending langsung
        $order = Order::create([
            'order_number' => 'ELCRAFT-TEST-' . uniqid(),
            'user_id' => $user->id,
            'address_id' => $address->id,
            'subtotal' => 100000,
            'total_amount' => 110000,
            'shipping_cost' => 10000,
            'courier' => 'jne',
            'courier_service' => 'REG',
            'status' => 'pending',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100000, 'subtotal' => 100000, 'product_name' => $product->name]);
        Payment::create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount' => 110000,
            'status' => 'pending',
            'snap_token' => 'test-token-' . uniqid(),
            'expired_at' => now()->addHours(24),
        ]);

        // Simulasi webhook settlement
        config()->set('midtrans.server_key_sandbox', 'SB-Test-Key-CancelPaid');
        $webhookPayload = [
            'order_id' => $order->order_number,
            'transaction_status' => 'settlement',
            'payment_type' => 'gopay',
            'status_code' => '200',
            'gross_amount' => (string) $order->total_amount,
            'signature_key' => hash('sha512', $order->order_number . '200' . $order->total_amount . config('midtrans.server_key_sandbox')),
        ];
        $this->postJson('/midtrans/webhook', $webhookPayload);

        // Customer coba cancel order yang sudah paid
        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $order->refresh();
        $this->assertEquals('paid', $order->status);
    }

    public function test_customer_opens_return_request_for_delivered_order_then_cancels_order(): void
    {
        [$user, $product] = $this->setupCustomer();

        // Buat order selesai
        $order = Order::create([
            'order_number' => 'ELCRAFT-TEST-' . uniqid(),
            'user_id' => $user->id,
            'address_id' => $user->addresses()->first()?->id ?? $user->addresses()->create([
                'label' => 'Rumah', 'recipient_name' => $user->name, 'phone' => '08123456789',
                'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
                'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
            ])->id,
            'subtotal' => 100000, 'total_amount' => 110000, 'shipping_cost' => 10000,
            'courier' => 'jne', 'courier_service' => 'REG', 'status' => 'selesai',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100000, 'subtotal' => 100000, 'product_name' => $product->name]);

        // Customer ajukan retur
        $return = \App\Models\ReturnRequest::create([
            'return_number' => 'RET-' . uniqid(),
            'order_id' => $order->id,
            'user_id' => $user->id,
            'reason' => 'Rusak',
            'resolution_type' => 'refund',
            'status' => 'diajukan',
        ]);

        // Coba cancel order (harus gagal, status selesai)
        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));
        $response->assertRedirect()->assertSessionHas('error');
    }

    public function test_customer_uses_dev_tools_to_manipulate_product_id(): void
    {
        [$user, $product] = $this->setupCustomer();
        $nonExistentId = 999999;

        $response = $this->actingAs($user)->postJson('/cart/add', [
            'product_id' => $nonExistentId,
            'quantity' => 1,
        ]);

        $response->assertStatus(422); // Validation error: exists:products,id
    }

    public function test_customer_tries_checkout_with_empty_cart(): void
    {
        [$user] = $this->setupCustomer();

        $address = $user->addresses()->create([
            'label' => 'Rumah', 'recipient_name' => $user->name, 'phone' => '08123456789',
            'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong',
            'postal_code' => '40132', 'full_address' => 'Jl. Test', 'is_default' => true,
        ]);

        // Keranjang kosong -> validasi gagal SEBELUM ongkir dan Midtrans.
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldNotReceive('calculateCost');
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldNotReceive('createSnapToken');
        $midtransMock->shouldNotReceive('cancelTransaction');
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this->actingAs($user)->postJson(route('checkout.token'), [
            'address_id' => $address->id,
            'recipient_name' => 'Jane Doe',
            'recipient_phone' => '08123456789',
            'full_address' => 'Jl. Test',
            'city' => 'Bandung',
            'city_id' => 23,
            'postal_code' => '40132',
            'courier' => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method' => 'gopay',
            'shipping_cost' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Keranjang belanja Anda kosong.',
            ]);

        // Tidak ada order/payment yang dibuat
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);

        // Keranjang tetap kosong (tidak ada item yang dikonversi)
        $this->assertSame(0, CartItem::where('user_id', $user->id)->count());
    }
}