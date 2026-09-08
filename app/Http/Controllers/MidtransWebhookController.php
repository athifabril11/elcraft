<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($orderId, $notification) {
            // ── Idempotency lock ────────────────────────────────────────────
            // lockForUpdate() acquires a row-level exclusive lock (SELECT ... FOR UPDATE).
            // If two webhook deliveries arrive simultaneously, the second one blocks here
            // until the first transaction commits. After the lock is acquired, we re-check
            // status — the second delivery will now see status='paid' and return early,
            // preventing any duplicate stock decrements, payment updates, or shipment rows.
            $order = Order::where('order_number', $orderId)->lockForUpdate()->first();

            if (!$order) {
                Log::warning("Midtrans webhook: order {$orderId} not found for markAsPaid");
                return;
            }

            if ($order->status === 'paid') {
                Log::info("Midtrans webhook: order {$orderId} already marked paid, skipping duplicate notification (idempotency guard)");
                return;
            }

            // Guard whitelist: Hanya pesanan dengan status 'pending' yang boleh diproses ke 'paid'.
            // Mencegah membangkitkan pesanan yang sudah dibatalkan ('dibatalkan') atau
            // menduplikasi pemotongan stok pada pesanan yang sudah berjalan ('diproses', 'dikirim', 'selesai').
            if (!in_array($order->status, ['pending'])) {
                Log::warning("Midtrans webhook: order {$orderId} memiliki status '{$order->status}', penyelesaian pembayaran ditolak");
                return;
            }

            // Update order status
            $order->update(['status' => 'paid']);

            // Update payment details
            if ($order->payment) {
                $order->payment->update([
                    'status'         => 'success',
                    'payment_method' => $notification['payment_type'] ?? null,
                    'payment_type'   => $notification['payment_type'] ?? null,
                    'paid_at'        => now(),
                    'response_data'  => json_encode($notification),
                ]);
            }

            // Create shipment record
            if (!$order->shipment) {
                $order->shipment()->create([
                    'courier'       => $order->courier ?? 'Manual',
                    'service'       => $order->courier_service ?? 'Reguler',
                    'shipping_cost' => $order->shipping_cost,
                ]);
            }

            // Decrement product variant/product stock
            foreach ($order->items as $item) {
                if ($item->variant_id) {
                    $variant = ProductVariant::find($item->variant_id);
                    if ($variant) {
                        $variant->decrement('stock', $item->quantity);
                    }
                } else {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->decrement('stock', $item->quantity);
                    }
                }
            }

            // Catat penggunaan voucher jika ada (dengan lock & re-validasi kuota)
            if ($order->voucher_id) {
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
                    // Kuota voucher telah habis oleh pesanan lain yang diselesaikan terlebih dahulu (race condition).
                    // Dana pelanggan di Midtrans sudah terpotong sehingga status pesanan tetap 'paid',
                    // namun counter voucher tidak dinaikkan (used_count <= quota) dan pesanan
                    // diberi catatan peringatan agar ditinjau oleh administrator toko.
                    Log::warning("Midtrans webhook: Kuota voucher '{$voucher?->code}' habis saat settlement pesanan {$order->order_number}. Selisih diskon diserap toko / ditandai untuk review admin.");

                    $warningNote = '[PERINGATAN SISTEM: Kuota voucher ' . ($voucher?->code ?? '') . ' telah habis saat pembayaran dikonfirmasi. Periksa transaksi ini]';
                    $order->update([
                        'notes' => trim(($order->notes ? $order->notes . ' | ' : '') . $warningNote),
                    ]);
                }
            }
        });
    }

    private function markAsPending(string $orderId): void
    {
        Log::info("Pesanan {$orderId} menunggu pembayaran");
        
        $order = Order::where('order_number', $orderId)->first();
        if ($order) {
            $order->update(['status' => 'pending']);
            if ($order->payment) {
                $order->payment->update(['status' => 'pending']);
            }
        }
    }

    private function markAsFailed(string $orderId, string $reason): void
    {
        Log::warning("Pesanan {$orderId} gagal/dibatalkan", ['reason' => $reason]);
        
        $order = Order::where('order_number', $orderId)->first();
        if ($order) {
            DB::transaction(function () use ($order) {
                $oldStatus = $order->status;
                $order->update(['status' => 'dibatalkan']);
                if ($order->payment) {
                    $order->payment->update(['status' => 'failed']);
                }

                // If the order was already paid, restore stock
                if ($oldStatus === 'paid') {
                    foreach ($order->items as $item) {
                        if ($item->variant_id) {
                            $variant = ProductVariant::find($item->variant_id);
                            if ($variant) {
                                $variant->increment('stock', $item->quantity);
                            }
                        } else {
                            $product = Product::find($item->product_id);
                            if ($product) {
                                $product->increment('stock', $item->quantity);
                            }
                        }
                    }
                }
            });
        }
    }
}
