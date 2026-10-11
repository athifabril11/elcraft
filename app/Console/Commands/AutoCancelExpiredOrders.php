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
 * For each expired order the command mirrors OrderController::cancel:
 *   1. Short lockForUpdate txn to re-verify the order is still 'pending'.
 *   2. Midtrans Core API cancel OUTSIDE any DB transaction.
 *   3. On 'cancelled'/'not_found': lock again, flip status to 'dibatalkan',
 *      mark payment 'failed', restore the checkout stock reservation.
 *   4. Send OrderCancelledMail (event: payment_failed, reason: expire).
 * On 'cannot_modify'/'error' the order is left untouched so the next run
 * (or the webhook) can settle it — never force-cancel a Midtrans race.
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

            // 2. Midtrans cancel OUTSIDE any DB transaction.
            $midtrans = app(MidtransService::class);
            $cancel = $midtrans->cancelTransaction($candidate->order_number);

            if (!in_array($cancel['outcome'] ?? 'error', ['cancelled', 'not_found'], true)) {
                // Midtrans returned cannot_modify (412) or error —
                // before skipping, call Get Status API to see actual transaction state.
                $statusResult = $midtrans->getTransactionStatus($candidate->order_number);
                $midtransStatus = $statusResult['status'] ?? 'unknown';

                if (in_array($midtransStatus, ['settlement'], true)) {
                    // Payment actually succeeded — mark as paid via the shared
                    // settlement service (same path as the webhook: lock +
                    // idempoten + rekam pemakaian voucher).
                    $paid = app(PaymentSettlementService::class)->markOrderPaid($candidate, [
                        'payment_type' => $statusResult['raw']['payment_type'] ?? null,
                    ]);

                    if ($paid) {
                        $resolved++;
                        Log::info('orders:auto-cancel-expired — late settlement found via Get Status API, marked paid', [
                            'order_number'    => $candidate->order_number,
                            'midtrans_status' => $midtransStatus,
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
                        continue;
                    }
                } elseif (in_array($midtransStatus, ['deny', 'expire', 'cancel'], true)) {
                    // Payment failed/expired/cancelled — proceed with local cancel
                    // (fall through to existing cancel logic below)
                } else {
                    // Unknown or error — skip and retry next run
                    $skipped++;
                    Log::warning('orders:auto-cancel-expired — Get Status API inconclusive, coba lagi run berikutnya', [
                        'order_number' => $candidate->order_number,
                        'midtrans_status' => $midtransStatus,
                        'cancel_outcome' => $cancel['outcome'] ?? 'error',
                    ]);
                    continue;
                }
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
