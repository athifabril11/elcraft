<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * RefundWebhookTest
 *
 * Covers the Midtrans 'refund' transaction_status notification branch:
 *   - payment -> refunded
 *   - eligible return synced (refund_status=api, status=selesai)
 *   - order status and stock are NEVER touched by a refund
 *   - idempotent across duplicate notifications
 */
class RefundWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function mockSignatureValid(): void
    {
        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);
    }

    private function postRefundWebhook(string $orderNumber): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $orderNumber,
            'transaction_status' => 'refund',
            'status_code'        => '200',
            'gross_amount'       => '150000.00',
            'signature_key'      => 'dummy_signature',
            'payment_type'       => 'gopay',
        ]);
    }

    /** @return array{0: User, 1: Product, 2: Order} */
    private function makePaidOrderWithStock(): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Anting', 'slug' => 'anting-rwh-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Anting Mutiara',
            'slug'        => 'anting-mutiara-rwh-' . uniqid(),
            'price'       => 75000,
            'stock'       => 8,
            'weight'      => 5,
            'is_active'   => true,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000009',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40135',
            'full_address'   => 'Jl. Webhook No. 9',
        ]);

        $order = Order::create([
            'order_number' => 'ELCRAFT-RWH-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 150000,
            'status'       => 'selesai',
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 75000,
            'quantity'     => 2,
            'subtotal'     => 150000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 150000,
            'status'            => 'success',
        ]);

        return [$user, $product, $order];
    }

    public function test_refund_notification_marks_payment_refunded(): void
    {
        $this->mockSignatureValid();
        [, , $order] = $this->makePaidOrderWithStock();

        $this->postRefundWebhook($order->order_number)->assertOk();

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
    }

    public function test_refund_notification_never_changes_order_status_or_stock(): void
    {
        $this->mockSignatureValid();
        [, $product, $order] = $this->makePaidOrderWithStock();

        $this->postRefundWebhook($order->order_number)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'selesai']);
        $product->refresh();
        $this->assertSame(8, $product->stock, 'Refund must never touch stock');
    }

    public function test_refund_notification_syncs_eligible_return(): void
    {
        $this->mockSignatureValid();
        [$user, , $order] = $this->makePaidOrderWithStock();

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-RWH-' . uniqid(),
            'resolution_type' => 'refund',
            'reason'          => 'Cacat',
            'status'          => 'disetujui',
            'refund_amount'   => 150000,
        ]);

        $this->postRefundWebhook($order->order_number)->assertOk();

        $ret->refresh();
        $this->assertSame('selesai', $ret->status);
        $this->assertSame('api', $ret->refund_status);
        $this->assertNotNull($ret->refunded_at);
    }

    public function test_refund_notification_does_not_touch_unapproved_return(): void
    {
        $this->mockSignatureValid();
        [$user, , $order] = $this->makePaidOrderWithStock();

        // Return still in dispute — admin has not approved a refund yet.
        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-RWH2-' . uniqid(),
            'resolution_type' => 'refund',
            'reason'          => 'Cacat',
            'status'          => 'diajukan',
            'refund_amount'   => 150000,
        ]);

        $this->postRefundWebhook($order->order_number)->assertOk();

        $ret->refresh();
        $this->assertSame('diajukan', $ret->status);
        $this->assertNull($ret->refund_status);

        // Payment is still refunded — money did move (e.g. dashboard refund)
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
    }

    /**
     * Exchange returns ('tukar_barang') never set refund_status, so they would
     * otherwise qualify for the refund webhook sync. No refund was owed for an
     * exchange — the webhook must NOT close the return nor write a refund entry.
     */
    public function test_refund_notification_does_not_close_tukar_barang_return(): void
    {
        $this->mockSignatureValid();
        [$user, , $order] = $this->makePaidOrderWithStock();

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-RWH-XCH-' . uniqid(),
            'resolution_type' => 'tukar_barang',
            'reason'          => 'Ukuran tidak pas',
            'status'          => 'disetujui',
            'refund_amount'   => 150000,
        ]);

        $this->postRefundWebhook($order->order_number)->assertOk();

        $ret->refresh();
        $this->assertSame('disetujui', $ret->status, 'Exchange return must stay open');
        $this->assertNull($ret->refund_status, 'No refund ledger entry for an exchange');
        $this->assertNull($ret->refunded_at);

        // Money did move (dashboard refund) — payment still recorded as refunded.
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
    }

    public function test_duplicate_refund_notification_is_idempotent(): void
    {
        $this->mockSignatureValid();
        [$user, , $order] = $this->makePaidOrderWithStock();

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-RWH3-' . uniqid(),
            'resolution_type' => 'refund',
            'reason'          => 'Cacat',
            'status'          => 'disetujui',
            'refund_amount'   => 150000,
        ]);

        $this->postRefundWebhook($order->order_number)->assertOk();
        $firstRefundedAt = $ret->fresh()->refunded_at;

        $this->postRefundWebhook($order->order_number)->assertOk();

        $ret->refresh();
        $this->assertSame('selesai', $ret->status);
        $this->assertSame('api', $ret->refund_status);
        $this->assertEquals($firstRefundedAt, $ret->refunded_at);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'selesai']);
    }

    // ── Late settlement (bayar-telat setelah cancel) ─────────────────────────

    /**
     * Settlement valid tiba untuk order yang sudah 'dibatalkan'
     * (race: pelanggan bayar di detik cancel). Dana sudah tertangkap —
     * sistem harus auto-refund dengan refund_key AUTO-{order_number},
     * tanpa mengubah status order dan tanpa menyentuh stok.
     */
    public function test_late_settlement_for_cancelled_order_triggers_auto_refund(): void
    {
        [$user, $product, $order] = $this->makePaidOrderWithStock();
        $order->update(['status' => 'dibatalkan']);
        Payment::where('order_id', $order->id)->update(['status' => 'failed']);

        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $mock->shouldReceive('refundTransaction')
            ->once()
            ->withArgs(fn ($orderId, $amount, $reason, $refundKey) =>
                $orderId === $order->order_number
                && $amount == 150000
                && $refundKey === 'AUTO-' . $order->order_number
            )
            ->andReturn(['outcome' => 'refunded', 'status_code' => 200, 'message' => '']);
        $this->app->instance(MidtransService::class, $mock);

        $this->postSettlement($order->order_number)->assertOk();

        // Order must not be resurrected, stock untouched, money refunded.
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'dibatalkan']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
        $product->refresh();
        $this->assertSame(8, $product->stock, 'Stock must never be touched by late settlement');
    }

    public function test_late_settlement_auto_refund_failure_keeps_payment_for_manual_handling(): void
    {
        [$user, $product, $order] = $this->makePaidOrderWithStock();
        $order->update(['status' => 'dibatalkan']);
        Payment::where('order_id', $order->id)->update(['status' => 'failed']);

        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $mock->shouldReceive('refundTransaction')->once()->andReturn([
            'outcome'     => 'error',
            'status_code' => null,
            'message'     => 'timed out',
        ]);
        $this->app->instance(MidtransService::class, $mock);

        $this->postSettlement($order->order_number)->assertOk();

        // Money was captured but refund failed: payment must show 'success'
        // (dana tertangkap, menunggu refund manual admin), order stays dibatalkan.
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'dibatalkan']);
        $product->refresh();
        $this->assertSame(8, $product->stock, 'Stock must never be touched by late settlement');
    }

    public function test_duplicate_late_settlement_calls_auto_refund_only_once(): void
    {
        [$user, $product, $order] = $this->makePaidOrderWithStock();
        $order->update(['status' => 'dibatalkan']);
        Payment::where('order_id', $order->id)->update(['status' => 'failed']);

        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $mock->shouldReceive('refundTransaction')->once()->andReturn([
            'outcome'     => 'refunded',
            'status_code' => 200,
            'message'     => '',
        ]);
        $this->app->instance(MidtransService::class, $mock);

        $this->postSettlement($order->order_number)->assertOk();
        $this->postSettlement($order->order_number)->assertOk();

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'dibatalkan']);
    }

    private function postSettlement(string $orderNumber): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $orderNumber,
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '150000.00',
            'signature_key'      => 'dummy_signature',
            'payment_type'       => 'qris',
            'fraud_status'       => 'accept',
        ]);
    }
}
