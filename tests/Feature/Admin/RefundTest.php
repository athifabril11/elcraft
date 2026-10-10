<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * RefundTest
 *
 * Covers the admin refund feature:
 *   - refund execution via Midtrans Refund API (admin.returns.refund)
 *   - manual refund marking (admin.returns.refund-manual)
 *   - Midtrans 'refund' webhook notification handling
 */
class RefundTest extends TestCase
{
    use RefreshDatabase;

    // ── Schema ───────────────────────────────────────────────────────────────

    public function test_returns_table_has_refund_status_and_refunded_at_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('returns', 'refund_status'),
            'returns.refund_status column must exist');
        $this->assertTrue(Schema::hasColumn('returns', 'refunded_at'),
            'returns.refunded_at column must exist');
    }

    public function test_payments_status_accepts_refunded_value(): void
    {
        $this->assertTrue(Schema::hasColumn('payments', 'status'),
            'payments.status column must exist');

        // The enum/check constraint must allow 'refunded'.
        // Creating a Payment with status='refunded' fails if the enum was not extended.
        $user = \App\Models\User::factory()->create();
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
        $order = \App\Models\Order::create([
            'order_number' => 'ORD-REFSCHEMA-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 10000,
            'total_amount' => 10000,
            'status'       => 'selesai',
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 10000,
            'status'            => 'refunded',
        ]);

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
    }

    public function test_return_request_can_be_created_with_refund_status(): void
    {
        $user = \App\Models\User::factory()->create();
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
        $order = \App\Models\Order::create([
            'order_number' => 'ORD-REFMODEL-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 10000,
            'total_amount' => 10000,
            'status'       => 'selesai',
        ]);

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-SCHEMA-' . uniqid(),
            'resolution_type' => 'refund',
            'reason'          => 'Cacat',
            'status'          => 'disetujui',
            'refund_amount'   => 5000,
            'refund_status'   => 'api',
        ]);

        $this->assertDatabaseHas('returns', [
            'id'           => $ret->id,
            'refund_status' => 'api',
        ]);
        $this->assertNull($ret->refunded_at);
    }

    // ── RefundService Edge Cases ─────────────────────────────────────────────

    private function createReturnForService(
        string $returnStatus = 'disetujui',
        string $paymentStatus = 'success',
        ?string $paymentMethod = 'credit_card',
        ?string $paymentType = null
    ): ReturnRequest {
        $user = User::factory()->create();
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
            'order_number' => 'ORD-REFSRV-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 50000,
            'total_amount' => 50000,
            'status'       => 'selesai',
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 50000,
            'status'            => $paymentStatus,
            'payment_method'    => $paymentMethod,
            'payment_type'      => $paymentType,
        ]);

        $cat = Category::create(['name' => 'Kategori ' . uniqid(), 'slug' => 'cat-' . uniqid(), 'is_active' => true]);
        $prd = Product::create([
            'category_id' => $cat->id,
            'name'        => 'Produk ' . uniqid(),
            'slug'        => 'prd-' . uniqid(),
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $orderItem = $order->items()->create([
            'product_id'   => $prd->id,
            'product_name' => $prd->name,
            'price'        => 50000,
            'quantity'     => 1,
            'subtotal'     => 50000,
        ]);

        $return = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-REFSRV-' . uniqid(),
            'resolution_type' => 'refund',
            'reason'          => 'Barang rusak.',
            'status'          => $returnStatus,
            'refund_amount'   => 50000,
        ]);

        ReturnItem::create([
            'return_id'     => $return->id,
            'order_item_id' => $orderItem->id,
            'quantity'      => 1,
        ]);

        return $return;
    }

    public function test_service_blocks_unsupported_method_and_falls_back_to_payment_type(): void
    {
        $service = app(RefundService::class);
        $return = $this->createReturnForService('disetujui', 'success', null, 'echannel');

        $result = $service->processApiRefund($return);

        $this->assertFalse($result['success']);
        $this->assertSame('Metode pembayaran "echannel" tidak didukung Refund API — gunakan tombol Refund Manual.', $result['message']);
    }

    public function test_service_syncs_dashboard_refund_without_http(): void
    {
        Http::fake();
        $service = app(RefundService::class);
        $return = $this->createReturnForService('disetujui', 'refunded', 'bca_va');

        $result = $service->processApiRefund($return);

        $this->assertTrue($result['success']);
        $this->assertSame('Pembayaran sudah direfund melalui Midtrans — status retur disinkronkan.', $result['message']);
        Http::assertNothingSent();

        $return->refresh();
        $this->assertSame('selesai', $return->status);
        $this->assertSame('api', $return->refund_status);
        $this->assertNotNull($return->refunded_at);
    }

    /**
     * Refund benar-benar dicatat sebagai 'manual' — catatan admin diberi
     * penanda [REFUND MANUAL] agar terlihat di riwayat retur.
     */
    public function test_manual_refund_notes_are_prefixed_with_refund_manual_marker(): void
    {
        $service = app(RefundService::class);
        $return = $this->createReturnForService('disetujui', 'success');

        $result = $service->markManual($return, 'Transfer BCA 50000 ke pelanggan, ref TRX-111.');

        $this->assertTrue($result['success']);

        $return->refresh();
        $this->assertSame('manual', $return->refund_status);
        $this->assertStringContainsString('[REFUND MANUAL]', (string) $return->admin_notes);
        $this->assertStringContainsString('TRX-111', (string) $return->admin_notes);
    }

    /**
     * Sync dashboard (payment sudah 'refunded' di Midtrans) menyimpan
     * refund_status='api' — catatan admin TIDAK boleh diberi prefix
     * [REFUND MANUAL] karena refund-nya bukan manual.
     */
    public function test_dashboard_synced_refund_notes_are_not_prefixed_as_manual(): void
    {
        $service = app(RefundService::class);
        $return = $this->createReturnForService('disetujui', 'refunded', 'bca_va');

        $result = $service->markManual($return, 'Disinkronkan dari dashboard Midtrans');

        $this->assertTrue($result['success']);

        $return->refresh();
        $this->assertSame('api', $return->refund_status);
        $this->assertSame('Disinkronkan dari dashboard Midtrans', $return->admin_notes);
        $this->assertStringNotContainsString('[REFUND MANUAL]', (string) $return->admin_notes);
    }
}
