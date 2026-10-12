<?php

namespace App\Console\Commands;

use App\Mail\OrderCancelledMail;
use App\Mail\PaymentSuccessMail;
use App\Models\Order;
use App\Services\MidtransService;
use App\Services\PaymentSettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * AutoCancelExpiredOrders
 *
 * Automatically cancels 'pending' orders whose payment deadline has passed.
 * The deadline comes from Order::isPaymentExpired() — payment.expired_at
 * with a created_at+24h fallback — so it matches what the storefront
 * countdown badge and the resume-payment guard use.
 *
 * For each expired order:
 *   1. Short lockForUpdate txn to re-verify the order is still 'pending'.
 *   2. Midtrans Get Status API FIRST (OUTSIDE any DB transaction) — never
 *      call Cancel before knowing the real state: cancelling a credit-card
 *      transaction in 'capture' status would VOID the legitimate payment.
 *   3. Decide by the reported state:
 *      - settlement, or capture+fraud accept → mark paid via
 *        PaymentSettlementService + PaymentSuccessMail (never cancel).
 *      - capture with any other fraud_status → skip (await Midtrans decision).
 *      - pending → cancelTransaction; cancelled/not_found → local cancel,
 *        cannot_modify/error → skip and retry next run.
 *      - deny/expire/cancel/failure → local cancel directly (no cancel API).
 *      - body status_code 404 (transaction never existed) → local cancel.
 *      - refund/partial_refund → skip, needs manual handling.
 *      - API failure or unknown status → skip; NEVER cancel on uncertainty.
 *   4. Local cancel txn: re-verify 'pending' under lock, flip to
 *      'dibatalkan', mark payment 'failed', restore stock reservation,
 *      send OrderCancelledMail (event: payment_failed, reason: expire).
 *
 * Usage:
 *   php artisan orders:auto-cancel-expired
 *
 * Scheduled via bootstrap/app.php → withSchedule() → hourly().
 */
class AutoCancelExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-cancel-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel pending orders whose payment deadline has passed, restore stock, and notify the customer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Candidates: status pending AND payment deadline passed.
        // payment_expires_at is a virtual accessor (payment.expired_at or
        // created_at+24h), so filter in PHP after loading the payment.
        $candidates = Order::where('status', 'pending')
            ->with('payment')
            ->get()
            ->filter(fn (Order $order) => $order->isPaymentExpired());

        $cancelled = 0;
        $resolved  = 0;
        $skipped   = 0;

        foreach ($candidates as $candidate) {
            // 1. Short txn: re-verify status under lock, then release.
            $isPending = DB::transaction(function () use ($candidate) {
                $locked = Order::lockForUpdate()->findOrFail($candidate->id);
                return $locked->status === 'pending';
            });

            if (!$isPending) {
                continue;
            }

            // 2. Get Status API DULU, DI LUAR transaksi DB — jangan pernah
            //    memanggil Cancel sebelum tahu status asli: cancel pada
            //    transaksi kartu berstatus 'capture' akan MEMBATALKAN
            //    pembayaran yang sah (webhook-nya hilang, bukan gagal bayar).
            $midtrans        = app(MidtransService::class);
            $statusResult    = $midtrans->getTransactionStatus($candidate->order_number);
            $midtransStatus  = $statusResult['status'] ?? 'unknown';
            $bodyStatusCode  = (string) ($statusResult['midtrans_status_code'] ?? '');
            $fraudStatus     = $statusResult['fraud_status'] ?? null;

            $localCancel = false;

            if ($bodyStatusCode === '404') {
                // Transaksi tidak pernah ada di Midtrans (pelanggan tidak
                // memilih metode pembayaran) — batalkan lokal tanpa cancel API.
                $localCancel = true;
            } elseif (in_array($midtransStatus, ['settlement'], true)
                || ($midtransStatus === 'capture' && $fraudStatus === 'accept')) {
                // Pembayaran sah — tandai lunas via service bersama; JANGAN cancel.
                $paid = app(PaymentSettlementService::class)->markOrderPaid($candidate, [
                    'payment_type' => $statusResult['raw']['payment_type'] ?? null,
                ]);

                if ($paid) {
                    $resolved++;
                    Log::info('orders:auto-cancel-expired — pembayaran ditemukan lunas via Get Status API, ditandai paid', [
                        'order_number'    => $candidate->order_number,
                        'midtrans_status' => $midtransStatus,
                        'fraud_status'    => $fraudStatus,
                    ]);

                    // Email sukses pembayaran — sama seperti jalur webhook.
                    // Re-fetch order segar (load() hanya me-refresh relasi,
                    // atribut model tetap 'pending' di memori).
                    $paidOrder = Order::where('order_number', $candidate->order_number)
                        ->with(['items', 'address', 'payment', 'shipment', 'user'])
                        ->first();
                    if ($paidOrder && $paidOrder->user?->email) {
                        try {
                            Mail::to($paidOrder->user->email)->send(new PaymentSuccessMail($paidOrder));
                        } catch (\Throwable $e) {
                            Log::error('PaymentSuccessMail gagal terkirim (AutoCancelExpiredOrders late settlement)', [
                                'order_number' => $candidate->order_number,
                                'to_email'     => $paidOrder->user->email,
                                'error'        => $e->getMessage(),
                            ]);
                        }
                    }
                }
                continue;
            } elseif ($midtransStatus === 'capture') {
                // capture dengan fraud_status selain 'accept' (mis. 'challenge')
                // — jangan batalkan, jangan tandai lunas; tunggu keputusan
                // Midtrans (webhook berikutnya / review manual).
                $skipped++;
                Log::warning('orders:auto-cancel-expired — capture non-accept, skip', [
                    'order_number' => $candidate->order_number,
                    'fraud_status' => $fraudStatus,
                ]);
                continue;
            } elseif ($midtransStatus === 'pending') {
                // Masih menunggu pembayaran — minta cancel ke Midtrans.
                $cancel = $midtrans->cancelTransaction($candidate->order_number);

                if (in_array($cancel['outcome'] ?? 'error', ['cancelled', 'not_found'], true)) {
                    $localCancel = true;
                } else {
                    // cannot_modify/error — transaksi mungkin baru saja berubah;
                    // biarkan webhook / run berikutnya yang putuskan.
                    $skipped++;
                    Log::warning('orders:auto-cancel-expired — cancel ditolak Midtrans, coba lagi run berikutnya', [
                        'order_number' => $candidate->order_number,
                        'outcome'      => $cancel['outcome'] ?? 'error',
                        'status_code'  => $cancel['status_code'] ?? null,
                    ]);
                    continue;
                }
            } elseif (in_array($midtransStatus, ['deny', 'expire', 'cancel', 'failure'], true)) {
                // Transaksi sudah gagal/kedaluwarsa/dibatalkan di Midtrans —
                // batalkan lokal LANGSUNG tanpa cancel API.
                $localCancel = true;
            } elseif (in_array($midtransStatus, ['refund', 'partial_refund'], true)) {
                // Ada pergerakan dana yang tidak bisa ditangani otomatis —
                // butuh penanganan manual admin.
                $skipped++;
                Log::warning('orders:auto-cancel-expired — status refund membutuhkan penanganan manual, skip', [
                    'order_number'    => $candidate->order_number,
                    'midtrans_status' => $midtransStatus,
                ]);
                continue;
            } else {
                // API error / status tidak dikenal — JANGAN pernah batalkan
                // lokal pada keadaan yang tidak pasti.
                $skipped++;
                Log::warning('orders:auto-cancel-expired — Get Status API inconclusive, coba lagi run berikutnya', [
                    'order_number'    => $candidate->order_number,
                    'midtrans_status' => $midtransStatus,
                ]);
                continue;
            }

            if (!$localCancel) {
                continue;
            }

            // 3. Short txn: re-verify + flip status + restore stock.
            $result = DB::transaction(function () use ($candidate) {
                $locked = Order::lockForUpdate()->findOrFail($candidate->id);

                if ($locked->status !== 'pending') {
                    return false;
                }

                $locked->update(['status' => 'dibatalkan']);

                if ($locked->payment) {
                    $locked->payment->update(['status' => 'failed']);
                }

                // Pending = stock masih dipegang order (reserve-at-checkout).
                app(\App\Services\StockService::class)->restoreForOrder($locked);

                return true;
            });

            if (!$result) {
                continue;
            }

            $cancelled++;

            Log::info('orders:auto-cancel-expired — pesanan dibatalkan otomatis (batas waktu pembayaran habis)', [
                'order_number' => $candidate->order_number,
            ]);

            // 4. Email — failure must never break the command loop.
            $candidate->load('user');

            if ($candidate->user?->email) {
                try {
                    Mail::to($candidate->user->email)->send(
                        new OrderCancelledMail($candidate, 'payment_failed', 'expire')
                    );
                } catch (\Throwable $e) {
                    Log::error('OrderCancelledMail gagal terkirim (AutoCancelExpiredOrders)', [
                        'order_number' => $candidate->order_number,
                        'to_email'     => $candidate->user->email,
                        'error'        => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Auto-cancelled {$cancelled} expired order(s), {$resolved} late-settlement(s) marked paid, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
