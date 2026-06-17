<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        $paymentType       = $notification['payment_type'];

        Log::info('Midtrans webhook diterima', compact('orderId', 'transactionStatus', 'paymentType'));

        // ── 3. Perbarui Status Pesanan ───────────────────────────────
        // TODO: Ganti dengan model Order yang sebenarnya setelah dibuat
        // $order = Order::where('order_number', $orderId)->firstOrFail();

        match (true) {
            // Pembayaran berhasil (kartu kredit atau e-wallet)
            $transactionStatus === 'capture' && $fraudStatus === 'accept' => $this->markAsPaid($orderId, $notification),

            // Pembayaran berhasil (transfer bank, QRIS, minimarket)
            $transactionStatus === 'settlement' => $this->markAsPaid($orderId, $notification),

            // Pembayaran dalam proses / menunggu
            in_array($transactionStatus, ['pending']) => $this->markAsPending($orderId),

            // Pembayaran ditolak atau kedaluwarsa
            in_array($transactionStatus, ['deny', 'expire', 'cancel']) => $this->markAsFailed($orderId, $transactionStatus),

            // Status tidak dikenal — catat untuk investigasi
            default => Log::warning('Midtrans webhook: status tidak dikenal', $notification),
        };

        return response()->json(['message' => 'OK'], 200);
    }

    // ── Helper Methods ───────────────────────────────────────────────

    private function markAsPaid(string $orderId, array $notification): void
    {
        Log::info("Pesanan {$orderId} telah lunas", ['payment_type' => $notification['payment_type']]);
        // TODO: Order::where('order_number', $orderId)->update(['status' => 'paid']);
        // TODO: Kirim email konfirmasi pesanan ke pelanggan
    }

    private function markAsPending(string $orderId): void
    {
        Log::info("Pesanan {$orderId} menunggu pembayaran");
        // TODO: Order::where('order_number', $orderId)->update(['status' => 'pending']);
    }

    private function markAsFailed(string $orderId, string $reason): void
    {
        Log::warning("Pesanan {$orderId} gagal/dibatalkan", ['reason' => $reason]);
        // TODO: Order::where('order_number', $orderId)->update(['status' => 'failed']);
        // TODO: Kembalikan stok produk
    }
}
