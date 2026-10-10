<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderDirectRefundTest extends TestCase
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

    /**
     * Order 'dibatalkan' dengan payment 'success' (dana tertangkap).
     */
    private function makeCapturedOrder(string $paymentMethod = 'gopay'): Order
    {
        $user    = $this->customer();
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

        $order = Order::create([
            'order_number' => 'ORD-DRF-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 165000,
            'status'       => 'dibatalkan',
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 165000,
            'status'            => 'success',
            'payment_method'    => $paymentMethod,
        ]);

        return $order;
    }

    private function fakeRefundSuccess(): void
    {
        Http::fake([
            '*/refund' => Http::response([
                'status_code'        => '200',
                'status_message'     => 'Success, refund request is approved',
                'transaction_status' => 'refund',
            ], 200),
        ]);
    }

    // ── Refund API ───────────────────────────────────────────────────────────

    public function test_admin_can_refund_captured_order_via_api(): void
    {
        $this->fakeRefundSuccess();
        $order = $this->makeCapturedOrder('gopay');

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refund-api', $order->id))
            ->assertRedirect(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
        $this->assertSame('dibatalkan', $order->fresh()->status);
    }

    public function test_refund_api_uses_order_number_as_idempotent_refund_key(): void
    {
        Http::fake([
            '*/refund' => Http::response(['status_code' => '200', 'transaction_status' => 'refund'], 200),
        ]);
        $order = $this->makeCapturedOrder('gopay');

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refund-api', $order->id))
            ->assertRedirect();

        Http::assertSent(function ($request) use ($order) {
            return str_contains($request->url(), $order->order_number . '/refund')
                && $request['refund_key'] === $order->order_number
                && $request['amount'] == 165000;
        });
    }

    public function test_refund_api_blocked_for_unsupported_method(): void
    {
        Http::fake();
        $order = $this->makeCapturedOrder('bank_transfer');

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refund-api', $order->id))
            ->assertRedirect(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
        Http::assertNothingSent();
    }

    // ── Refund Manual ────────────────────────────────────────────────────────

    public function test_admin_can_mark_manual_refund_for_captured_order(): void
    {
        Http::fake();
        $order = $this->makeCapturedOrder('bank_transfer');

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refund-manual', $order->id), [
                'manual_notes' => 'Transfer bank BCA 123.',
            ])
            ->assertRedirect(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
        Http::assertNothingSent();
    }

    public function test_manual_refund_requires_notes(): void
    {
        $order = $this->makeCapturedOrder();

        $this->actingAs($this->admin())
            ->from(route('admin.orders.index', ['payment_captured' => 1]))
            ->post(route('admin.orders.refund-manual', $order->id), ['manual_notes' => ''])
            ->assertSessionHasErrors('manual_notes');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
    }

    // ── Guards ───────────────────────────────────────────────────────────────

    public function test_refund_blocked_when_payment_not_success(): void
    {
        Http::fake();
        $order = $this->makeCapturedOrder();
        Payment::where('order_id', $order->id)->update(['status' => 'failed']);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refund-api', $order->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
        Http::assertNothingSent();
    }

    public function test_refund_blocked_when_order_not_dibatalkan(): void
    {
        Http::fake();
        $order = $this->makeCapturedOrder();
        $order->update(['status' => 'selesai']);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refund-api', $order->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
        Http::assertNothingSent();
    }

    public function test_refund_blocked_when_order_has_open_return(): void
    {
        Http::fake();
        $order = $this->makeCapturedOrder();

        $category = \App\Models\Category::create(['name' => 'Lilin', 'slug' => 'lilin-drf-' . uniqid(), 'is_active' => true]);
        $product  = \App\Models\Product::create([
            'category_id' => $category->id,
            'name'        => 'Lilin Aromaterapi',
            'slug'        => 'lilin-drf-' . uniqid(),
            'price'       => 150000,
            'stock'       => 10,
            'weight'      => 50,
            'is_active'   => true,
        ]);
        $item = $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => 'Lilin Aromaterapi',
            'quantity'     => 1,
            'price'        => 150000,
            'subtotal'     => 150000,
        ]);

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $order->user_id,
            'return_number'   => 'RET-DRF-' . uniqid(),
            'resolution_type' => 'refund',
            'reason'          => 'Cacat.',
            'status'          => 'disetujui',
            'refund_amount'   => 150000,
        ]);
        $ret->items()->create(['order_item_id' => $item->id, 'quantity' => 1]);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refund-api', $order->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
        Http::assertNothingSent();
    }

    public function test_second_refund_attempt_is_blocked_after_success(): void
    {
        $this->fakeRefundSuccess();
        $order = $this->makeCapturedOrder();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refund-api', $order->id))
            ->assertSessionHas('success');

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refund-api', $order->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
    }

    // ── UI: filter dana tertangkap menampilkan tombol ────────────────────────

    public function test_captured_filter_page_shows_refund_buttons(): void
    {
        $order = $this->makeCapturedOrder('gopay');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertOk()
            ->assertSee(route('admin.orders.refund-api', $order->id), false)
            ->assertSee(route('admin.orders.refund-manual', $order->id), false);
    }
}
