<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentSettlementService — satu-satunya jalur "tandai order lunas".
 *
 * Dipakai oleh dua pemicu yang sebelumnya duplikasi:
 *   1. MidtransWebhookController::markAsPaid (settlement / capture+accept)
 *   2. AutoCancelExpiredOrders (rekonkonsiliasi Get-Status API -> settlement)
 *
 * Setiap pemanggilan = satu transaksi DB atomik + lock baris order,
 * jadi webhook ganda atau rekonkonsiliasi bersamaan tidak double-proses
 * (idempoten) dan rekaman pemakaian voucher selalu ikut tercatat.
 */
class PaymentSettlementService
{
    /**
     * Tandai order 'pending' menjadi 'paid' secara atomik.
     *
     * @param  Order  $order    Order target (di-lock ulang di dalam transaksi)
     * @param  array  $context  Konteksopsional: 'payment_type' => string|null,
     *                                        'response_data' => string (JSON mentah notifikasi)
     * @return bool             true jika status benar-benar berubah ke 'paid'
     */
    public function markOrderPaid(Order $order, array $context = []): bool
    {
        $paid = DB::transaction(function () use ($order, $context) {
            $locked = Order::lockForUpdate()->firstWhere('id', $order->id);

            if (!$locked) {
                Log::warning("PaymentSettlementService: order id {$order->id} tidak ditemukan, lewati");
                return false;
            }

            if ($locked->status === 'paid') {
                Log::info("PaymentSettlementService: order {$locked->order_number} sudah paid, lewati (idempoten)");
                return false;
            }

            if ($locked->status !== 'pending') {
                Log::warning("PaymentSettlementService: order {$locked->order_number} berstatus '{$locked->status}', penyelesaian pembayaran ditolak");
                return false;
            }

            $locked->update(['status' => 'paid']);

            if ($locked->payment) {
                $paymentUpdate = [
                    'status'  => 'success',
                    'paid_at' => now(),
                ];
                if (array_key_exists('payment_type', $context)) {
                    $paymentUpdate['payment_method'] = $context['payment_type'];
                    $paymentUpdate['payment_type']   = $context['payment_type'];
                }
                if (array_key_exists('response_data', $context)) {
                    $paymentUpdate['response_data'] = $context['response_data'];
                }
                $locked->payment->update($paymentUpdate);
            }

            if (!$locked->shipment) {
                $locked->shipment()->create([
                    'courier'       => $locked->courier ?? 'Manual',
                    'service'       => $locked->courier_service ?? 'Reguler',
                    'shipping_cost' => $locked->shipping_cost,
                ]);
            }

            // Stok TIDAK disentuh — sudah di-reserve saat checkout
            // (StockService::reserveForCart dalam transaksi pembuatan order).

            $this->recordVoucherUsage($locked);

            return true;
        });

        return $paid;
    }

    /**
     * Catat pemakaian voucher order (dengan lock & re-validasi kuota).
     *
     * Kuota = batas keras: jika sudah habis saat settlement (race condition),
     * pesanan TETAP paid (dana sudah tertangkap) tapi tanpa VoucherUsage dan
     * diberi catatan peringatan untuk review admin — used_count tidak
     * pernah melebihi quota.
     */
    private function recordVoucherUsage(Order $order): void
    {
        if (!$order->voucher_id) {
            return;
        }

        $voucher = Voucher::where('id', $order->voucher_id)->lockForUpdate()->first();

        if ($voucher && $voucher->used_count < $voucher->quota) {
            VoucherUsage::firstOrCreate(
                [
                    'voucher_id' => $voucher->id,
                    'user_id'    => $order->user_id,
                    'order_id'   => $order->id,
                ],
                [
                    'discount_applied' => $order->voucher_discount,
                ]
            );
            $voucher->increment('used_count');
        } else {
            Log::warning("PaymentSettlementService: Kuota voucher '{$voucher?->code}' habis saat settlement pesanan {$order->order_number}. Selisih diskon diserap toko / ditandai untuk review admin.");

            $warningNote = '[PERINGATAN SISTEM: Kuota voucher ' . ($voucher?->code ?? '') . ' telah habis saat pembayaran dikonfirmasi. Periksa transaksi ini]';
            $order->update([
                'notes' => trim(($order->notes ? $order->notes . ' | ' : '') . $warningNote),
            ]);
        }
    }
}
