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

        $response->assertRedirect(route('checkout.index'));

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

        $response->assertRedirect(route('checkout.index'));
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
}
