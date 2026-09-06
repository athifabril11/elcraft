<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ReturnAdminTest
 *
 * Tests admin access control and full lifecycle for the return management module.
 *
 * Routes under test:
 *   GET   /admin/returns                       admin.returns.index
 *   PATCH /admin/returns/{id}/approve          admin.returns.approve
 *   PATCH /admin/returns/{id}/reject           admin.returns.reject
 *   PATCH /admin/returns/{id}/advance          admin.returns.advance
 */
class ReturnAdminTest extends TestCase
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

    private function makeOrder(User $user, string $status = 'selesai'): Order
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
            'order_number' => 'ORD-RADMTEST-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 150000,
            'status'       => $status,
        ]);
    }

    private function makeOrderItem(Order $order): OrderItem
    {
        $cat = Category::create(['name' => 'Gelang', 'slug' => 'gelang-' . uniqid(), 'is_active' => true]);
        $prd = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Gelang Emas',
            'slug'        => 'gelang-emas-' . uniqid(),
            'price'       => 150000,
            'stock'       => 10,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        return OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $prd->id,
            'product_name' => $prd->name,
            'price'        => $prd->price,
            'quantity'     => 2,
            'subtotal'     => 300000,
        ]);
    }

    /**
     * Create a ReturnRequest with one ReturnItem.
     */
    private function makeReturn(User $user, string $status = 'diajukan', string $resolution = 'refund'): ReturnRequest
    {
        $order = $this->makeOrder($user);
        $item  = $this->makeOrderItem($order);

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'resolution_type' => $resolution,
            'reason'          => 'Produk cacat saat diterima.',
            'status'          => $status,
        ]);

        ReturnItem::create([
            'return_id'     => $ret->id,
            'order_item_id' => $item->id,
            'quantity'      => 1,
        ]);

        return $ret;
    }

    // ── Access Control: Index ────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_returns_index(): void
    {
        $this->get('/admin/returns')->assertRedirect('/login');
    }

    public function test_non_admin_gets_403_on_returns_index(): void
    {
        $this->actingAs($this->customer())
             ->get('/admin/returns')
             ->assertForbidden();
    }

    // ── Access Control: Approve ──────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_approve(): void
    {
        $ret = $this->makeReturn($this->customer());
        $this->patch("/admin/returns/{$ret->id}/approve")->assertRedirect('/login');
    }

    public function test_non_admin_gets_403_on_approve(): void
    {
        $ret = $this->makeReturn($this->customer());
        $this->actingAs($this->customer())
             ->patch("/admin/returns/{$ret->id}/approve")
             ->assertForbidden();
    }

    // ── Access Control: Reject ───────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_reject(): void
    {
        $ret = $this->makeReturn($this->customer());
        $this->patch("/admin/returns/{$ret->id}/reject")->assertRedirect('/login');
    }

    public function test_non_admin_gets_403_on_reject(): void
    {
        $ret = $this->makeReturn($this->customer());
        $this->actingAs($this->customer())
             ->patch("/admin/returns/{$ret->id}/reject")
             ->assertForbidden();
    }

    // ── Access Control: Advance ──────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_advance(): void
    {
        $ret = $this->makeReturn($this->customer(), 'disetujui');
        $this->patch("/admin/returns/{$ret->id}/advance")->assertRedirect('/login');
    }

    public function test_non_admin_gets_403_on_advance(): void
    {
        $ret = $this->makeReturn($this->customer(), 'disetujui');
        $this->actingAs($this->customer())
             ->patch("/admin/returns/{$ret->id}/advance")
             ->assertForbidden();
    }

    // ── Index View ───────────────────────────────────────────────────────────

    public function test_admin_can_view_returns_index(): void
    {
        $cust = $this->customer();
        $ret  = $this->makeReturn($cust);

        $this->actingAs($this->admin())
             ->get('/admin/returns')
             ->assertOk()
             ->assertViewIs('admin.returns.index')
             ->assertViewHas('returns')
             ->assertSee($ret->return_number);
    }

    public function test_returns_index_shows_all_returns_without_filter(): void
    {
        $cust = $this->customer();
        $r1   = $this->makeReturn($cust, 'diajukan');
        $r2   = $this->makeReturn($cust, 'disetujui');
        $r3   = $this->makeReturn($cust, 'selesai');

        $this->actingAs($this->admin())
             ->get('/admin/returns')
             ->assertOk()
             ->assertSee($r1->return_number)
             ->assertSee($r2->return_number)
             ->assertSee($r3->return_number);
    }

    public function test_status_filter_shows_only_matching_returns(): void
    {
        $cust = $this->customer();
        $r1   = $this->makeReturn($cust, 'diajukan');
        $r2   = $this->makeReturn($cust, 'selesai');

        $response = $this->actingAs($this->admin())
             ->get('/admin/returns?status=diajukan')
             ->assertOk();

        $response->assertSee($r1->return_number);
        $response->assertDontSee($r2->return_number);
    }

    // ── Approve: refund type ─────────────────────────────────────────────────

    public function test_admin_can_approve_refund_return_with_refund_amount(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diajukan', 'refund');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/approve", ['refund_amount' => 150000])
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('success');

        $fresh = $ret->fresh();
        $this->assertEquals('disetujui', $fresh->status);
        $this->assertEquals(150000, $fresh->refund_amount);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_approving_refund_return_without_refund_amount_fails_validation(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diajukan', 'refund');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/approve", [])
             ->assertRedirect()
             ->assertSessionHasErrors(['refund_amount']);

        $this->assertEquals('diajukan', $ret->fresh()->status);
    }

    public function test_refund_amount_exceeding_order_total_fails_validation(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diajukan', 'refund');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/approve", ['refund_amount' => 999999])
             ->assertRedirect()
             ->assertSessionHasErrors(['refund_amount']);

        $this->assertEquals('diajukan', $ret->fresh()->status);
    }

    public function test_refund_amount_exactly_equal_to_order_total_is_accepted(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diajukan', 'refund');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/approve", ['refund_amount' => 150000])
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('success');

        $fresh = $ret->fresh();
        $this->assertEquals('disetujui', $fresh->status);
        $this->assertEquals(150000, $fresh->refund_amount);
    }

    // ── Approve: tukar_barang type ───────────────────────────────────────────

    public function test_admin_can_approve_tukar_barang_return_without_refund_amount(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diajukan', 'tukar_barang');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/approve", ['admin_notes' => 'Barang pengganti segera dikirim.'])
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('success');

        $fresh = $ret->fresh();
        $this->assertEquals('disetujui', $fresh->status);
        $this->assertNull($fresh->refund_amount);
        $this->assertNotNull($fresh->resolved_at);
    }

    // ── Reject ───────────────────────────────────────────────────────────────

    public function test_admin_can_reject_return_with_admin_notes(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diajukan');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/reject", ['admin_notes' => 'Foto bukti tidak memadai.'])
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('success');

        $fresh = $ret->fresh();
        $this->assertEquals('ditolak', $fresh->status);
        $this->assertEquals('Foto bukti tidak memadai.', $fresh->admin_notes);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_rejecting_without_admin_notes_fails_validation(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diajukan');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/reject", [])
             ->assertRedirect()
             ->assertSessionHasErrors(['admin_notes']);

        $this->assertEquals('diajukan', $ret->fresh()->status);
    }

    // ── Guard: cannot approve/reject non-diajukan ────────────────────────────

    public function test_admin_cannot_approve_already_disetujui_return(): void
    {
        $ret = $this->makeReturn($this->customer(), 'disetujui');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/approve", ['refund_amount' => 50000])
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('error');

        $this->assertEquals('disetujui', $ret->fresh()->status);
    }

    public function test_admin_cannot_reject_already_selesai_return(): void
    {
        $ret = $this->makeReturn($this->customer(), 'selesai');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/reject", ['admin_notes' => 'Terlambat ditolak.'])
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('error');

        $this->assertEquals('selesai', $ret->fresh()->status);
    }

    // ── Advance: disetujui -> diproses ───────────────────────────────────────

    public function test_admin_can_advance_disetujui_to_diproses(): void
    {
        $ret = $this->makeReturn($this->customer(), 'disetujui');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/advance")
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('success');

        $this->assertEquals('diproses', $ret->fresh()->status);
    }

    // ── Advance: diproses -> selesai ─────────────────────────────────────────

    public function test_admin_can_advance_diproses_to_selesai(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diproses');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/advance")
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('success');

        $this->assertEquals('selesai', $ret->fresh()->status);
    }

    // ── Block: cannot skip stages ────────────────────────────────────────────

    public function test_admin_cannot_advance_diajukan_via_advance_route(): void
    {
        $ret = $this->makeReturn($this->customer(), 'diajukan');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/advance")
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('error');

        $this->assertEquals('diajukan', $ret->fresh()->status);
    }

    public function test_admin_cannot_advance_ditolak_via_advance_route(): void
    {
        $ret = $this->makeReturn($this->customer(), 'ditolak');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/advance")
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('error');

        $this->assertEquals('ditolak', $ret->fresh()->status);
    }

    public function test_admin_cannot_advance_selesai_further(): void
    {
        $ret = $this->makeReturn($this->customer(), 'selesai');

        $this->actingAs($this->admin())
             ->patch("/admin/returns/{$ret->id}/advance")
             ->assertRedirect(route('admin.returns.index'))
             ->assertSessionHas('error');

        $this->assertEquals('selesai', $ret->fresh()->status);
    }

    // ── Full sequential pipeline ─────────────────────────────────────────────

    public function test_full_pipeline_diajukan_disetujui_diproses_selesai(): void
    {
        $admin = $this->admin();
        $ret   = $this->makeReturn($this->customer(), 'diajukan', 'tukar_barang');

        // 1. Approve
        $this->actingAs($admin)
             ->patch("/admin/returns/{$ret->id}/approve", ['admin_notes' => 'OK.'])
             ->assertSessionHas('success');
        $this->assertEquals('disetujui', $ret->fresh()->status);

        // 2. Advance to diproses
        $this->actingAs($admin)
             ->patch("/admin/returns/{$ret->id}/advance")
             ->assertSessionHas('success');
        $this->assertEquals('diproses', $ret->fresh()->status);

        // 3. Advance to selesai
        $this->actingAs($admin)
             ->patch("/admin/returns/{$ret->id}/advance")
             ->assertSessionHas('success');
        $this->assertEquals('selesai', $ret->fresh()->status);

        // 4. Cannot advance further
        $this->actingAs($admin)
             ->patch("/admin/returns/{$ret->id}/advance")
             ->assertSessionHas('error');
        $this->assertEquals('selesai', $ret->fresh()->status);
    }
}