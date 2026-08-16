<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the "Lanjutkan Pembayaran" (resume payment) endpoint.
 *
 * Covers:
 *  1. Pending order with a non-expired payment → returns expired:false + snap_token
 *  2. Pending order with an expired payment    → returns expired:true
 *  3. Another user's order                     → 404 (IDOR protection via user scoping)
 *  4. Non-pending order (already paid)         → returns expired:true
 */
class ResumePaymentTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper: create an address for the given user ──────────────────────────
    private function makeAddress(User $user): Address
    {
        return $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Kecamatan',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Test No. 1',
        ]);
    }

    // ── Helper: create a pending order + payment for the given user ───────────
    private function makePendingOrderWithPayment(User $user, array $paymentOverrides = []): array
    {
        $address = $this->makeAddress($user);

        $order = Order::create([
            'order_number' => 'ELCRAFT-RESUMETEST-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => 'pending',
        ]);

        $payment = Payment::create(array_merge([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 100000,
            'status'            => 'pending',
            'snap_token'        => 'test_snap_token_abc123',
            'expired_at'        => now()->addHours(24), // non-expired default
        ], $paymentOverrides));

        return [$order, $payment];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * 1. A pending order whose snap_token has NOT expired yet.
     *    Endpoint should return { "expired": false, "snap_token": "..." }
     */
    public function test_resume_payment_returns_snap_token_when_not_expired(): void
    {
        $user = User::factory()->create();
        [$order, $payment] = $this->makePendingOrderWithPayment($user, [
            'snap_token' => 'valid_snap_token_xyz',
            'expired_at' => now()->addHours(12), // still 12h left
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('orders.resume-payment', $order->order_number));

        $response->assertOk()
            ->assertJson([
                'expired'    => false,
                'snap_token' => 'valid_snap_token_xyz',
            ]);
    }

    /**
     * 2. A pending order whose snap_token HAS expired (expired_at in the past).
     *    Endpoint should return { "expired": true }
     */
    public function test_resume_payment_returns_expired_when_token_is_past_ttl(): void
    {
        $user = User::factory()->create();
        [$order, $payment] = $this->makePendingOrderWithPayment($user, [
            'expired_at' => now()->subMinutes(1), // 1 minute ago
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('orders.resume-payment', $order->order_number));

        $response->assertOk()
            ->assertJson(['expired' => true])
            ->assertJsonMissing(['snap_token']);
    }

    /**
     * 3. A user attempts to resume payment for another user's order.
     *    The user-scoped query (firstOrFail) should return 404, never 403,
     *    to avoid leaking order existence.
     */
    public function test_resume_payment_is_forbidden_for_another_users_order(): void
    {
        $owner  = User::factory()->create();
        $hacker = User::factory()->create();

        [$order] = $this->makePendingOrderWithPayment($owner);

        $response = $this
            ->actingAs($hacker)
            ->getJson(route('orders.resume-payment', $order->order_number));

        // firstOrFail() on user->orders() returns 404 for another user's order
        $response->assertNotFound();
    }

    /**
     * 4. A non-pending order (e.g. already 'paid') cannot be resumed.
     *    Endpoint should return { "expired": true }
     */
    public function test_resume_payment_returns_expired_for_paid_order(): void
    {
        $user    = User::factory()->create();
        $address = $this->makeAddress($user);

        $order = Order::create([
            'order_number' => 'ELCRAFT-PAIDORDER-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 80000,
            'total_amount' => 80000,
            'status'       => 'paid',   // ← not pending
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 80000,
            'status'            => 'success',
            'snap_token'        => 'already_paid_token',
            'expired_at'        => now()->addHours(20),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('orders.resume-payment', $order->order_number));

        $response->assertOk()
            ->assertJson(['expired' => true])
            ->assertJsonMissing(['snap_token']);
    }

    /**
     * 5. A pending order with no Payment record at all.
     *    Endpoint should return { "expired": true } gracefully.
     */
    public function test_resume_payment_returns_expired_when_no_payment_record(): void
    {
        $user    = User::factory()->create();
        $address = $this->makeAddress($user);

        $order = Order::create([
            'order_number' => 'ELCRAFT-NOPAYMENT-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 50000,
            'total_amount' => 50000,
            'status'       => 'pending',
        ]);
        // No Payment::create() — payment record deliberately absent

        $response = $this
            ->actingAs($user)
            ->getJson(route('orders.resume-payment', $order->order_number));

        $response->assertOk()
            ->assertJson(['expired' => true]);
    }
}
