<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use App\Services\PaymentSettlementService;
use App\Services\StockService;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Mail\OrderCancelledMail;
use App\Mail\PaymentSuccessMail;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * MidtransWebhookController — Penanganan notifikasi pembayaran asinkron
 *
 * Midtrans mengirimkan notifikasi HTTP POST ke endpoint ini setiap kali
 * status transaksi berubah (berhasil, gagal, pending, dll.).
 *
 * PENTING: Endpoint ini TIDAK memerlukan autentikasi sesi pengguna,
 * tetapi HARUS memverifikasi signature untuk mencegah pemalsuan data.
 */
class MidtransWebhookController extends Controller
{
    public function __construct(private readonly MidtransService $midtrans) {}

    /**
     * Tangani notifikasi webhook dari Midtrans.
     *
     * Endpoint: POST /midtrans/webhook
     * Dikecualikan dari CSRF verification (lihat bootstrap/app.php atau VerifyCsrfToken middleware).
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $notification = $request->all();

        // ── 1. Verifikasi Signature ──────────────────────────────────
        // Langkah WAJIB — jangan proses jika signature tidak valid
        if (!$this->midtrans->verifySignature($notification)) {
            Log::warning('Midtrans webhook: signature tidak valid', [
                'order_id' => $notification['order_id'] ?? 'unknown',
                'ip'       => $request->ip(),
            ]);

            return response()->json(['message' => 'Signature tidak valid'], 403);
        }

        // ── 2. Ambil Data Transaksi ──────────────────────────────────
        $orderId           = $notification['order_id'];
        $transactionStatus = $notification['transaction_status'];
        $fraudStatus       = $notification['fraud_status'] ?? null;
        $paymentType       = $notification['payment_type'] ?? null;

        Log::info('Midtrans webhook diterima', compact('orderId', 'transactionStatus', 'paymentType'));

        // ── 3. Perbarui Status Pesanan ───────────────────────────────
        match (true) {
            // Pembayaran berhasil (kartu kredit atau e-wallet)
            $transactionStatus === 'capture' && $fraudStatus === 'accept' => $this->markAsPaid($orderId, $notification),

            // Pembayaran berhasil (transfer bank, QRIS, minimarket)
            $transactionStatus === 'settlement' => $this->markAsPaid($orderId, $notification),

            // Pembayaran dalam proses / menunggu
            in_array($transactionStatus, ['pending']) => $this->markAsPending($orderId, $notification),

            // Pembayaran ditolak atau kedaluwarsa
            in_array($transactionStatus, ['deny', 'expire', 'cancel']) => $this->markAsFailed($orderId, $transactionStatus),

            // Pengembalian dana (refund API sukses / refund via Midtrans dashboard)
            $transactionStatus === 'refund' => $this->markAsRefunded($orderId, $notification),

            // Status tidak dikenal — catat untuk investigasi (allowlist: tanpa signature_key)
            default => Log::warning('Midtrans webhook: status tidak dikenal', $this->logContext($notification)),
        };

        return response()->json(['message' => 'OK'], 200);
    }

    // ── Helper Methods ───────────────────────────────────────────────

    /**
     * Allowlist field notifikasi untuk konteks log — signature_key (dan
     * payload apa pun di luar daftar putar) tidak pernah masuk log.
     */
    private function logContext(array $notification): array
    {
        return Arr::only($notification, [
            'order_id',
            'transaction_status',
            'payment_type',
            'status_code',
            'fraud_status',
        ]);
    }

    private function markAsPaid(string $orderId, array $notification): void
    {
        // ── Semua keputusan di bawah lock (C2) ────────────────────────────────
        // Tanpa pre-read status tanpa lock: race "pending → dibatalkan" antara
        // load dan lock tidak lagi membuat settlement hilang senyap —
        // settleOrder() mengembalikan hasil tegas (RESULT_*) dan method ini
        // bertindak sesuai. Semua jalur TETAP membalas HTTP 200 (handle()).
        $order = Order::where('order_number', $orderId)->first();

        $result = $order
            ? app(PaymentSettlementService::class)->settleOrder($order, [
                'payment_type'  => $notification['payment_type'] ?? null,
                'response_data' => json_encode(Arr::except($notification, ['signature_key'])),
                'gross_amount'  => $notification['gross_amount'] ?? null,
            ])
            : PaymentSettlementService::RESULT_ORDER_MISSING;

        switch ($result) {
            case PaymentSettlementService::RESULT_PAID:
                Log::info("Pesanan {$orderId} telah lunas", ['payment_type' => $notification['payment_type'] ?? null]);

                // ── Kirim email konfirmasi pembayaran sukses / invoice kepada pelanggan ──
                $paidOrder = Order::where('order_number', $orderId)
                    ->with(['items', 'address', 'payment', 'shipment', 'user'])
                    ->first();

                if ($paidOrder && $paidOrder->user?->email) {
                    try {
                        Mail::to($paidOrder->user->email)->send(new PaymentSuccessMail($paidOrder));
                        Log::info("Email konfirmasi pembayaran berhasil dikirim ke {$paidOrder->user->email} untuk pesanan {$orderId}");
                    } catch (\Throwable $e) {
                        Log::error("Midtrans webhook: Gagal mengirim email konfirmasi pembayaran untuk pesanan {$orderId}: " . $e->getMessage());
                    }
                }
                return;

            case PaymentSettlementService::RESULT_ORDER_CANCELLED:
                // ── Bayar-telat (late settlement) ─────────────────────────────
                // Settlement valid tiba setelah order 'dibatalkan' (race: pelanggan
                // bayar di detik cancel). Dana sudah tertangkap di Midtrans — jangan
                // bangkitkan order, tapi auto-refund uangnya. Panggilan API dilakukan
                // DI LUAR transaksi DB.
                Log::critical('Midtrans webhook: settlement untuk order yang sudah dibatalkan — menjalankan auto-refund', [
                    'order_number' => $orderId,
                ]);
                $this->refundLateSettlement($orderId, $notification);
                return;

            case PaymentSettlementService::RESULT_ALREADY_PAID:
            case PaymentSettlementService::RESULT_AMOUNT_MISMATCH:
                // Idempoten / marker mismatch sudah dicatat service — diam (200).
                return;

            default:
                // RESULT_ORDER_MISSING / RESULT_NOT_PENDING — jangan diam senyap.
                Log::warning('Midtrans webhook: settlement ditolak oleh PaymentSettlementService', [
                    'order_number' => $orderId,
                    'result'       => $result,
                ]);
        }
    }

    /**
     * Settlement telat untuk order yang sudah 'dibatalkan'.
     *
     * Dana pelanggan sudah terpotong (settlement valid, signature lolos) tetapi
     * order tidak boleh dibangkitkan. Kebijakan: catat dana sebagai tertangkap
     * (payment → success), lalu auto-refund penuh dengan refund_key idempoten
     * AUTO-{order_number}. Jika refund API gagal, payment dibiarkan 'success'
     * supaya admin melihat ada dana tertangkap yang harus direfund manual.
     */
    private function refundLateSettlement(string $orderId, array $notification): void
    {
        Log::critical('Midtrans webhook: settlement diterima untuk order yang sudah dibatalkan — menjalankan auto-refund', [
            'order_id'     => $orderId,
            'gross_amount' => $notification['gross_amount'] ?? null,
        ]);

        $order = Order::where('order_number', $orderId)->first();
        if (!$order) {
            Log::warning("Midtrans webhook: order {$orderId} not found for refundLateSettlement");
            return;
        }

        // Idempotensi: refund sudah tercatat dari pengiriman webhook sebelumnya.
        $payment = Payment::where('order_id', $order->id)->first();
        if ($payment && $payment->status === 'refunded') {
            Log::info("Midtrans webhook: late settlement untuk order {$orderId} sudah direfund sebelumnya — skip");
            return;
        }

        // Dana memang tertangkap — catat dulu sebelum meminta refund.
        Payment::where('order_id', $order->id)->update(['status' => 'success']);

        $outcome = $this->midtrans->refundTransaction(
            $orderId,
            (float) $order->total_amount,
            'Settlement telat untuk order yang sudah dibatalkan',
            'AUTO-' . $orderId
        );

        if (in_array($outcome['outcome'], ['refunded', 'duplicate'], true)) {
            Payment::where('order_id', $order->id)->update(['status' => 'refunded']);
            Log::critical('Auto-refund settlement telat BERHASIL', [
                'order_id' => $orderId,
                'outcome'  => $outcome['outcome'],
            ]);
        } else {
            Log::critical('Auto-refund settlement telat GAGAL — ada dana tertangkap yang harus direfund manual oleh admin', [
                'order_id'    => $orderId,
                'outcome'     => $outcome['outcome'],
                'status_code' => $outcome['status_code'],
                'message'     => $outcome['message'],
            ]);
        }
    }

    /**
     * Notifikasi refund dari Midtrans (hasil Refund API atau refund manual
     * lewat Midtrans dashboard). Midtrans mengirim 2 notifikasi refund
     * (tanpa dan dengan bank_confirmed_at) — method ini idempotent.
     *
     * Status order DAN stok TIDAK diubah — refund bukan pembatalan pesanan.
     */
    private function markAsRefunded(string $orderId, array $notification): void
    {
        DB::transaction(function () use ($orderId, $notification) {
            $order = Order::where('order_number', $orderId)->lockForUpdate()->first();

            if (!$order) {
                Log::warning("Midtrans webhook: order {$orderId} not found for markAsRefunded");
                return;
            }

            Payment::where('order_id', $order->id)->update(['status' => 'refunded']);

            // Sinkronkan retur refund yang memenuhi syarat (disetujui/diproses, belum direfund).
            // Retur 'tukar_barang' tidak punya kewajiban refund — jangan ditutup di sini.
            // Retur yang masih 'diajukan' tidak disentuh — uang memang bergerak
            // (mis. refund dari dashboard Midtrans), tapi keputusan retur tetap admin.
            $return = ReturnRequest::where('order_id', $order->id)->lockForUpdate()->first();
            if ($return
                && $return->refund_status === null
                && $return->resolution_type === 'refund'
                && in_array($return->status, ['disetujui', 'diproses'], true)
            ) {
                $return->update([
                    'status'        => 'selesai',
                    'refund_status' => 'api',
                    'refunded_at'   => now(),
                ]);
            }

            Log::info("Midtrans webhook: refund dikonfirmasi untuk order {$orderId}", [
                'refund_key' => $notification['refund_key'] ?? null,
            ]);
        });
    }

    private function markAsPending(string $orderId, array $notification = []): void
    {
        DB::transaction(function () use ($orderId, $notification) {
            // Guard whitelist + idempotency lock: baca status di dalam transaksi
            // dengan lockForUpdate agar tidak ada jendela TOCTOU — settlement yang
            // commit di antara pembacaan dan penulisan tidak bisa tertimpa kembali
            // ke 'pending'. Hanya pesanan dengan status 'pending' yang boleh menerima
            // notifikasi pending; status lain ('paid', 'diproses', 'dikirim',
            // 'selesai', 'dibatalkan') dilewati.
            $order = Order::where('order_number', $orderId)->lockForUpdate()->first();

            if (!$order) {
                Log::warning("Midtrans webhook: order {$orderId} not found for markAsPending");
                return;
            }

            if ($order->status !== 'pending') {
                Log::warning("Midtrans webhook: order {$orderId} memiliki status '{$order->status}', notifikasi pending diabaikan", [
                    'order_number' => $orderId,
                    'status'       => $order->status,
                ]);
                return;
            }

            Log::info("Pesanan {$orderId} menunggu pembayaran", $this->logContext($notification));

            $order->update(['status' => 'pending']);
            if ($order->payment) {
                $paymentUpdates = [
                    'status' => 'pending',
                ];

                if (!empty($notification['payment_type'])) {
                    $paymentUpdates['payment_method'] = $notification['payment_type'];
                    $paymentUpdates['payment_type']   = $notification['payment_type'];
                }

                if (!empty($notification)) {
                    $paymentUpdates['response_data'] = json_encode(Arr::except($notification, ['signature_key']));
                }

                if (!empty($notification['expiry_time'])) {
                    try {
                        $paymentUpdates['expired_at'] = \Carbon\Carbon::parse($notification['expiry_time']);
                    } catch (\Exception $e) {
                        Log::warning("Gagal parse expiry_time dari webhook: {$notification['expiry_time']}");
                    }
                }

                $order->payment->update($paymentUpdates);
            }
        });
    }

    private function markAsFailed(string $orderId, string $reason): void
    {
        $cancelled = DB::transaction(function () use ($orderId, $reason) {
            // Guard whitelist + idempotency lock: baca status di dalam transaksi
            // dengan lockForUpdate agar keputusan pembatalan tidak balapan dengan
            // webhook lain. Pesanan 'pending' menerima semua alasan (deny/expire/
            // cancel); pesanan 'paid' HANYA menerima 'cancel' (pembatalan merchant)
            // — 'deny'/'expire' yang telat tiba setelah settlement tidak boleh
            // membatalkan pembayaran yang sudah tertangkap dan memulihkan stok
            // (selisih uang). Status lain ('diproses', 'dikirim', 'selesai',
            // 'dibatalkan') dilewati — order DAN payment tidak tersentuh, karena
            // stok tidak akan dipulihkan untuk status tersebut.
            $order = Order::where('order_number', $orderId)->lockForUpdate()->first();

            if (!$order) {
                Log::warning("Midtrans webhook: order {$orderId} not found for markAsFailed");
                return null;
            }

            $allowed = $order->status === 'pending'
                || ($order->status === 'paid' && $reason === 'cancel');

            if (!$allowed) {
                Log::warning("Midtrans webhook: order {$orderId} memiliki status '{$order->status}' (alasan '{$reason}'), pembatalan ditolak", [
                    'order_number' => $orderId,
                    'status'       => $order->status,
                    'reason'       => $reason,
                ]);
                return null;
            }

            Log::warning("Pesanan {$orderId} gagal/dibatalkan", ['reason' => $reason]);

            $oldStatus = $order->status;
            $order->update(['status' => 'dibatalkan']);
            if ($order->payment) {
                $order->payment->update(['status' => 'failed']);
            }

            // Pulihkan reservasi stok yang dibuat saat checkout — berlaku untuk
            // pembatalan 'pending' (reservasi aktif) dan 'paid' (reservasi masih
            // dipegang order). Untuk status lain guard di atas sudah menolak.
            app(StockService::class)->restoreForOrder($order);

            // Kembalikan kuota voucher — hanya jika tercatat (settlement paid).
            // Logika pindah ke PaymentSettlementService::releaseVoucherUsage
            // (dipakai bersama oleh admin cancel-paid); perilaku tidak berubah:
            // tanpa rekaman VoucherUsage, used_count tidak pernah dikurangi.
            if ($oldStatus === 'paid') {
                app(PaymentSettlementService::class)->releaseVoucherUsage($order);
            }

            return $order;
        });

        if ($cancelled) {
            $this->sendCancelledEmail($cancelled, 'payment_failed', $reason);
        }
    }

    /**
     * Kirim email notifikasi pembatalan ke pelanggan (DI LUAR transaksi DB).
     * Kegagalan SMTP tidak boleh menggagalkan webhook — dicatat sebagai error log.
     */
    private function sendCancelledEmail(Order $order, string $event, ?string $reason = null): void
    {
        $order->loadMissing('user');

        if (!$order->user?->email) {
            return;
        }

        try {
            Mail::to($order->user->email)->send(new OrderCancelledMail($order, $event, $reason));
        } catch (\Throwable $e) {
            Log::error('OrderCancelledMail gagal terkirim (webhook)', [
                'order_number' => $order->order_number,
                'event'        => $event,
                'reason'       => $reason,
                'to_email'     => $order->user->email,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
