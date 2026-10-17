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
     * Substring khas marker idempoten di orders.notes ketika nominal
     * notifikasi Midtrans tidak sama dengan total pesanan.
     */
    public const AMOUNT_MISMATCH_MARKER = 'PERINGATAN SISTEM: Nominal pembayaran dari Midtrans';

    /**
     * Tandai order 'pending' menjadi 'paid' secara atomik.
     *
     * @param  Order  $order    Order target (di-lock ulang di dalam transaksi)
     * @param  array  $context  Konteks opsional: 'payment_type' => string|null,
     *                                        'response_data' => string (JSON mentah notifikasi),
     *                                        'gross_amount' => string|float|int (nominal dari Midtrans)
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

            // ── Verifikasi nominal (di bawah lock, setelah semua guard) ──
            // gross_amount dari Midtrans harus sama dengan total pesanan.
            // Selisih → JANGAN ubah apa pun (order/payment/stok/voucher/email),
            // catat kritis + marker idempoten di orders.notes, kembalikan false.
            $expectedAmount = (int) round((float) $locked->total_amount);
            $receivedAmount = isset($context['gross_amount']) && $context['gross_amount'] !== ''
                ? (int) round((float) $context['gross_amount'])
                : null;

            if ($receivedAmount === null) {
                // Tanpa gross_amount perilaku tetap seperti semula — hanya terlihat di log.
                Log::warning("PaymentSettlementService: amount not verified untuk pesanan {$locked->order_number} — gross_amount tidak ada di konteks");
            } elseif ($expectedAmount !== $receivedAmount) {
                Log::critical('PaymentSettlementService: nominal pembayaran Midtrans tidak sama dengan total pesanan', [
                    'order_number'    => $locked->order_number,
                    'expected_amount' => $expectedAmount,
                    'received_amount' => $receivedAmount,
                ]);

                $marker = self::AMOUNT_MISMATCH_MARKER
                    . ' (Rp ' . number_format($receivedAmount, 0, ',', '.') . ')'
                    . ' tidak sama dengan total pesanan (Rp ' . number_format($expectedAmount, 0, ',', '.') . ')'
                    . '. Periksa di dashboard Midtrans.';

                $notes = (string) $locked->notes;
                if (!str_contains($notes, self::AMOUNT_MISMATCH_MARKER)) {
                    $locked->update([
                        'notes' => trim(($notes !== '' ? $notes . ' | ' : '') . $marker),
                    ]);
                }

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

    /**
     * Lepas pemakaian voucher sebuah order saat order dibatalkan.
     *
     * HARUS dipanggil di dalam transaksi DB. Tidak pernah berbuat apa pun
     * jika: (a) order tidak punya voucher_id, atau (b) tidak ada baris
     * VoucherUsage untuk order ini (kuota habis saat settlement / tidak
     * pernah tercatat) — used_count tidak pernah dikurangi tanpa rekaman.
     * Jika ada: usage dihapus dan used_count dikurangi tepat 1, tidak
     * pernah di bawah 0.
     *
     * Dipakai oleh:
     *   - MidtransWebhookController::markAsFailed (cancel dari paid)
     *   - Admin\OrderController::cancelPaid
     */
    public function releaseVoucherUsage(Order $order): void
    {
        if (!$order->voucher_id) {
            return;
        }

        $usage = VoucherUsage::where('order_id', $order->id)->first();

        if (!$usage) {
            return;
        }

        $usage->delete();

        Voucher::where('id', $order->voucher_id)
            ->where('used_count', '>', 0)
            ->decrement('used_count');
    }
}
