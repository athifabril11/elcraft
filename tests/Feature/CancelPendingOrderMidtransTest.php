<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the Midtrans-aware cancel flow in OrderController::cancel().
 *
 * Follows the same fixture style as CustomerOrderPendingActionsTest.
 */
class CancelPendingOrderMidtransTest extends TestCase
{
    use RefreshDatabase;

    // ────────────────────────────────────────────────────────────────────────
    // Shared fixture helper
    // ────────────────────────────────────────────────────────────────────────

    private function createOrderForUser(User $user, string $status = 'pending'): Order
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

        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung-' . uniqid(), 'is_active' => true]);
        $product  = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Emas',
            'slug'        => 'kalung-emas-' . uniqid(),
            'price'       => 100000,
            'stock'       => 10,
            'weight'      => 50,
            'is_active'   => true,
        ]);

        $order = Order::create([
            'order_number'     => 'ELCRAFT-MT-' . uniqid(),
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
            'snap_token'        => 'snap-token-mt-test',
            'expired_at'        => now()->addHours(24),
        ]);

        return $order;
    }

    // ────────────────────────────────────────────────────────────────────────
    // (a) Midtrans returns status_code 200 → order cancelled locally
    // ────────────────────────────────────────────────────────────────────────

    public function test_midtrans_200_cancels_order_and_payment_and_posts_to_correct_endpoint(): void
    {
        $user  = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        Http::fake([
            '*/v2/*/cancel' => Http::response(
                ['status_code' => '200', 'status_message' => 'Success, transaction is canceled'],
                200
            ),
        ]);

        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));

        $response->assertRedirect();

        $this->assertEquals('dibatalkan', $order->fresh()->status);
        $this->assertEquals('failed', $order->fresh()->payment->status);

        // Verify exactly one POST was sent to the correct Core API cancel endpoint
        Http::assertSentCount(1);
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($order) {
            return str_ends_with($request->url(), '/v2/' . $order->order_number . '/cancel')
                && $request->method() === 'POST'
                && str_starts_with($request->header('Authorization')[0] ?? '', 'Basic ');
        });
    }

    // ────────────────────────────────────────────────────────────────────────
    // (b) Midtrans returns status_code 404 (no transaction) → still cancel locally
    // ────────────────────────────────────────────────────────────────────────

    public function test_midtrans_404_still_cancels_order_locally(): void
    {
        $user  = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        Http::fake([
            '*/v2/*/cancel' => Http::response(
                ['status_code' => '404', 'status_message' => 'Transaction doesn\'t exist.'],
                404
            ),
        ]);

        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));

        $response->assertRedirect();
        $this->assertEquals('dibatalkan', $order->fresh()->status);
    }

    // ────────────────────────────────────────────────────────────────────────
    // (c) Midtrans returns status_code 412 → order stays pending, error flash shown
    // ────────────────────────────────────────────────────────────────────────

    public function test_midtrans_412_leaves_order_pending_and_shows_error_flash(): void
    {
        $user  = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        Http::fake([
            '*/v2/*/cancel' => Http::response(
                ['status_code' => '412', 'status_message' => 'Merchant cannot modify the status of the transaction.'],
                412
            ),
        ]);

        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals('pending', $order->fresh()->payment->status);
    }

    // ────────────────────────────────────────────────────────────────────────
    // (d) HTTP 500 response → order stays pending
    //     Connection exception → order stays pending
    // ────────────────────────────────────────────────────────────────────────

    public function test_midtrans_500_response_leaves_order_pending(): void
    {
        $user  = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        Http::fake([
            '*/v2/*/cancel' => Http::response(
                ['status_code' => '500', 'status_message' => 'Internal Server Error'],
                500
            ),
        ]);

        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_connection_exception_leaves_order_pending(): void
    {
        $user  = User::factory()->create();
        $order = $this->createOrderForUser($user, 'pending');

        Http::fake([
            '*/v2/*/cancel' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals('pending', $order->fresh()->status);
    }

    // ────────────────────────────────────────────────────────────────────────
    // (e) Order already 'paid' → not cancelled, no HTTP request sent
    // ────────────────────────────────────────────────────────────────────────

    public function test_paid_order_is_not_cancelled_and_no_http_request_sent(): void
    {
        $user  = User::factory()->create();
        $order = $this->createOrderForUser($user, 'paid');

        Http::fake(); // catch anything that slips through

        $response = $this->actingAs($user)->post(route('orders.cancel', $order->order_number));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertEquals('paid', $order->fresh()->status);

        Http::assertNothingSent();
    }

    // ────────────────────────────────────────────────────────────────────────
    // (f) Another user's order → 404, no HTTP request sent
    // ────────────────────────────────────────────────────────────────────────

    public function test_another_users_order_returns_404_and_no_http_request_sent(): void
    {
        $owner     = User::factory()->create();
        $attacker  = User::factory()->create();
        $order     = $this->createOrderForUser($owner, 'pending');

        Http::fake(); // catch anything that slips through

        $response = $this->actingAs($attacker)->post(route('orders.cancel', $order->order_number));

        $response->assertNotFound();

        $this->assertEquals('pending', $order->fresh()->status);

        Http::assertNothingSent();
    }
}
