<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AdminRefundFlowTest
 *
 * Admin refund execution flows:
 *   POST /admin/returns/{return}/refund          (Midtrans Refund API)
 *   POST /admin/returns/{return}/refund-manual   (admin marks external refund)
 *
 * Semantics under test:
 *   - success/duplicate API outcome -> return selesai, refund_status=api,
 *     refunded_at set, payment -> refunded
 *   - rejected/error API outcome    -> return unchanged, flash error
 *   - guards: double refund, wrong resolution, wrong status, authorization
 *   - manual refund requires notes
 */
class AdminRefundFlowTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function makeReturn(
        User $user,
        string $returnStatus = 'disetujui',
        string $resolution = 'refund',
        string $paymentStatus = 'success',
        ?string $paymentMethod = 'credit_card'
    ): ReturnRequest {
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
            'order_number' => 'ORD-ARF-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 150000,
            'status'       => 'selesai',
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 150000,
            'status'            => $paymentStatus,
            'payment_method'    => $paymentMethod,
        ]);

        $cat = Category::create(['name' => 'Cincin', 'slug' => 'cincin-' . uniqid(), 'is_active' => true]);
        $prd = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Cincin Perak',
            'slug'        => 'cincin-perak-' . uniqid(),
            'price'       => 150000,
            'stock'       => 5,
            'weight'      => 5,
            'is_active'   => true,
        ]);
        $item = $order->items()->create([
            'product_id'   => $prd->id,
            'product_name' => $prd->name,
            'price'        => 150000,
            'quantity'     => 1,
            'subtotal'     => 150000,
        ]);

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-ARF-' . uniqid(),
            'resolution_type' => $resolution,
            'reason'          => 'Produk tidak sesuai.',
            'status'          => $returnStatus,
            'refund_amount'   => 150000,
        ]);

        ReturnItem::create([
            'return_id'     => $ret->id,
            'order_item_id' => $item->id,
            'quantity'      => 1,
        ]);

        return $ret;
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

    // ── API Refund: success path ─────────────────────────────────────────────

    public function test_admin_can_process_refund_via_api(): void
    {
        $this->fakeRefundSuccess();
        $ret = $this->makeReturn($this->customer());

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('success');

        $ret->refresh();
        $this->assertSame('selesai', $ret->status);
        $this->assertSame('api', $ret->refund_status);
        $this->assertNotNull($ret->refunded_at);

        $this->assertDatabaseHas('payments', ['order_id' => $ret->order_id, 'status' => 'refunded']);
    }

    public function test_refund_api_request_sends_return_amount_and_refund_key(): void
    {
        Http::fake([
            '*/refund' => Http::response(['status_code' => '200', 'transaction_status' => 'refund'], 200),
        ]);
        $ret = $this->makeReturn($this->customer());

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertRedirect(route('admin.returns.index'));

        Http::assertSent(function ($request) use ($ret) {
            return str_contains($request->url(), $ret->order->order_number . '/refund')
                && $request['refund_key'] === $ret->return_number
                && $request['amount'] == 150000;
        });
    }

    public function test_duplicate_refund_outcome_406_is_treated_as_success(): void
    {
        Http::fake([
            '*/refund' => Http::response([
                'status_code'    => '406',
                'status_message' => 'Duplicate refund ID',
            ], 406),
        ]);
        $ret = $this->makeReturn($this->customer());

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertSessionHas('success');

        $ret->refresh();
        $this->assertSame('selesai', $ret->status);
        $this->assertSame('api', $ret->refund_status);
        $this->assertDatabaseHas('payments', ['order_id' => $ret->order_id, 'status' => 'refunded']);
    }

    // ── API Refund: failure paths ────────────────────────────────────────────

    public function test_rejected_refund_keeps_return_unchanged(): void
    {
        Http::fake([
            '*/refund' => Http::response([
                'status_code'    => '414',
                'status_message' => 'Refund request is rejected due to invalid amount',
            ], 414),
        ]);
        $ret = $this->makeReturn($this->customer());

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('error');

        $ret->refresh();
        $this->assertSame('disetujui', $ret->status);
        $this->assertNull($ret->refund_status);
        $this->assertNull($ret->refunded_at);
        $this->assertDatabaseHas('payments', ['order_id' => $ret->order_id, 'status' => 'success']);
    }

    public function test_connection_error_keeps_return_unchanged_and_is_retryable(): void
    {
        Http::fake([
            '*/refund' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'),
        ]);
        $ret = $this->makeReturn($this->customer());

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertSessionHas('error');

        $this->assertSame('disetujui', $ret->fresh()->status);
        $this->assertNull($ret->fresh()->refund_status);
    }

    // ── Guards ───────────────────────────────────────────────────────────────

    public function test_cannot_process_refund_twice(): void
    {
        Http::fake([
            '*/refund' => Http::response(['status_code' => '200'], 200),
        ]);
        $ret = $this->makeReturn($this->customer());
        $ret->update(['status' => 'selesai', 'refund_status' => 'api', 'refunded_at' => now()]);

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame('api', $ret->fresh()->refund_status);
    }

    public function test_cannot_refund_tukar_barang_return(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'tukar_barang');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame('disetujui', $ret->fresh()->status);
    }

    public function test_cannot_refund_unapproved_return(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer(), 'diajukan');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame('diajukan', $ret->fresh()->status);
    }

    public function test_cannot_refund_when_payment_not_success(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'refund', 'pending');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    // ── Method Whitelist & Dashboard Sync Guards ─────────────────────────────

    public function test_api_refund_blocked_for_unsupported_payment_method(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'refund', 'success', 'bca_va');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('error');

        $error = session('error');
        $this->assertStringContainsString('tidak didukung Refund API', $error);
        $this->assertStringContainsString('Refund Manual', $error);
        $this->assertStringContainsString('bca_va', $error);

        Http::assertNothingSent();
        $ret->refresh();
        $this->assertNull($ret->refund_status);
        $this->assertSame('disetujui', $ret->status);
    }

    public function test_api_refund_blocked_when_payment_method_empty_displays_unknown(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'refund', 'success', null);

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('error');

        $error = session('error');
        $this->assertStringContainsString('Metode pembayaran "unknown" tidak didukung Refund API — gunakan tombol Refund Manual.', $error);

        Http::assertNothingSent();
        $ret->refresh();
        $this->assertNull($ret->refund_status);
    }

    public function test_api_refund_allowed_for_supported_method_smoke(): void
    {
        $this->fakeRefundSuccess();
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'refund', 'success', 'credit_card');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('success');

        $ret->refresh();
        $this->assertSame('selesai', $ret->status);
        $this->assertSame('api', $ret->refund_status);
        $this->assertNotNull($ret->refunded_at);
        $this->assertDatabaseHas('payments', ['order_id' => $ret->order_id, 'status' => 'refunded']);
    }

    public function test_dashboard_refund_then_approve_syncs_return(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'refund', 'refunded', 'bca_va');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('success');

        $this->assertStringContainsString('disinkronkan', session('success'));

        Http::assertNothingSent();
        $ret->refresh();
        $this->assertSame('selesai', $ret->status);
        $this->assertSame('api', $ret->refund_status);
        $this->assertNotNull($ret->refunded_at);
    }

    public function test_manual_refund_button_syncs_when_payment_already_refunded(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'refund', 'refunded', 'bca_va');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund_manual', $ret->id), [
                'manual_notes' => 'Disinkronkan karena sudah di-refund via Midtrans dashboard',
            ])
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('success');

        $this->assertStringContainsString('disinkronkan', session('success'));

        Http::assertNothingSent();
        $ret->refresh();
        $this->assertSame('selesai', $ret->status);
        $this->assertSame('api', $ret->refund_status);
        $this->assertNotNull($ret->refunded_at);
    }

    public function test_manual_refund_allowed_for_unsupported_api_payment_method(): void
    {
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'refund', 'success', 'bca_va');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund_manual', $ret->id), [
                'manual_notes' => 'Manual refund transfer untuk pesanan BCA VA.',
            ])
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('success');

        $ret->refresh();
        $this->assertSame('selesai', $ret->status);
        $this->assertSame('manual', $ret->refund_status);
        $this->assertNotNull($ret->refunded_at);
        $this->assertDatabaseHas('payments', ['order_id' => $ret->order_id, 'status' => 'refunded']);
    }

    public function test_cannot_sync_or_refund_when_return_already_refunded_even_if_payment_refunded(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer(), 'selesai', 'refund', 'refunded', 'credit_card');
        $ret->update(['refund_status' => 'api', 'refunded_at' => now()]);

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertSessionHas('error');

        $this->assertStringContainsString('sudah direfund', session('error'));
        Http::assertNothingSent();

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund_manual', $ret->id), [
                'manual_notes' => 'Notes test',
            ])
            ->assertSessionHas('error');

        $this->assertStringContainsString('sudah direfund', session('error'));
        Http::assertNothingSent();
    }

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_guest_cannot_process_refund(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer());

        $this->patch(route('admin.returns.refund', $ret->id))->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_customer_cannot_process_refund(): void
    {
        Http::fake();
        $ret = $this->makeReturn($this->customer());

        $this->actingAs($this->customer())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    // ── Manual refund ────────────────────────────────────────────────────────

    public function test_manual_refund_requires_notes(): void
    {
        $ret = $this->makeReturn($this->customer());

        $this->actingAs($this->admin())
            ->from(route('admin.returns.show', $ret->id))
            ->patch(route('admin.returns.refund_manual', $ret->id), [])
            ->assertSessionHasErrors('manual_notes');

        $ret->refresh();
        $this->assertNull($ret->refund_status);
        $this->assertSame('disetujui', $ret->status);
    }

    public function test_admin_can_mark_manual_refund_with_notes(): void
    {
        $ret = $this->makeReturn($this->customer());

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund_manual', $ret->id), [
                'manual_notes' => 'Transfer BCA 150000 ke pelanggan, ref TRX-9911.',
            ])
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('success');

        $ret->refresh();
        $this->assertSame('selesai', $ret->status);
        $this->assertSame('manual', $ret->refund_status);
        $this->assertNotNull($ret->refunded_at);
        $this->assertStringContainsString('TRX-9911', (string) $ret->admin_notes);

        // Money returned outside Midtrans API — payment still recorded as refunded
        $this->assertDatabaseHas('payments', ['order_id' => $ret->order_id, 'status' => 'refunded']);
    }

    public function test_customer_cannot_mark_manual_refund(): void
    {
        $ret = $this->makeReturn($this->customer());

        $this->actingAs($this->customer())
            ->patch(route('admin.returns.refund_manual', $ret->id), ['manual_notes' => 'x'])
            ->assertForbidden();

        $this->assertNull($ret->fresh()->refund_status);
    }

    // ── Detail page UI ───────────────────────────────────────────────────────

    public function test_show_page_displays_refund_buttons_for_eligible_return(): void
    {
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'refund');

        $this->actingAs($this->admin())
            ->get(route('admin.returns.show', $ret->id))
            ->assertOk()
            ->assertSee('Proses Refund')
            ->assertSee('Refund Manual');
    }

    public function test_show_page_hides_refund_buttons_for_tukar_barang(): void
    {
        $ret = $this->makeReturn($this->customer(), 'disetujui', 'tukar_barang');

        $this->actingAs($this->admin())
            ->get(route('admin.returns.show', $ret->id))
            ->assertOk()
            ->assertDontSee('Proses Refund');
    }

    public function test_show_page_hides_refund_buttons_and_shows_badge_after_refund(): void
    {
        $ret = $this->makeReturn($this->customer(), 'selesai', 'refund');
        $ret->update(['refund_status' => 'api', 'refunded_at' => now()]);

        $this->actingAs($this->admin())
            ->get(route('admin.returns.show', $ret->id))
            ->assertOk()
            ->assertDontSee('Proses Refund')
            ->assertSee('Refund API');
    }

    public function test_show_page_hides_refund_buttons_while_still_diajukan(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diajukan', 'refund');

        $this->actingAs($this->admin())
            ->get(route('admin.returns.show', $ret->id))
            ->assertOk()
            ->assertDontSee('Proses Refund');
    }

    public function test_index_shows_refund_badge_for_refunded_return(): void
    {
        $ret = $this->makeReturn($this->customer(), 'selesai', 'refund');
        $ret->update(['refund_status' => 'manual', 'refunded_at' => now()]);

        $this->actingAs($this->admin())
            ->get(route('admin.returns.index'))
            ->assertOk()
            ->assertSee('Refund Manual');
    }

    // ── Advance guard (Task 5) ───────────────────────────────────────────────

    public function test_refund_return_cannot_advance_to_selesai_before_refund(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diproses', 'refund');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.advance', $ret->id))
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('proses refund', session('error'));
        $this->assertSame('diproses', $ret->fresh()->status);
    }

    public function test_refund_return_can_advance_after_refund(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diproses', 'refund');
        $ret->update(['refund_status' => 'api', 'refunded_at' => now()]);

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.advance', $ret->id))
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('success');

        $this->assertSame('selesai', $ret->fresh()->status);
    }

    public function test_tukar_barang_return_can_advance_normally(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diproses', 'tukar_barang');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.advance', $ret->id))
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHas('success');

        $this->assertSame('selesai', $ret->fresh()->status);
    }
}
