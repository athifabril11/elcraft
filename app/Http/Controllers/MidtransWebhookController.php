<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
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
        
        $order = Order::where('order_number', $orderId)->first();
        if ($order && $order->status !== 'paid') {
            DB::transaction(function () use ($order, $notification) {
                // Update order status
                $order->update(['status' => 'paid']);

                // Update payment details
                if ($order->payment) {
                    $order->payment->update([
                        'status' => 'success',
                        'payment_method' => $notification['payment_type'] ?? null,
                        'payment_type' => $notification['payment_type'] ?? null,
                        'paid_at' => now(),
                        'response_data' => json_encode($notification),
                    ]);
                }

                // Create shipment record
                if (!$order->shipment) {
                    $order->shipment()->create([
                        'courier' => 'Manual',
                        'service' => 'Reguler',
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
            });
        }
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
