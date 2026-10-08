<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderController — Riwayat & Detail Pesanan Pelanggan.
 */
class OrderController extends Controller
{
    /**
     * Tampilkan daftar pesanan milik pengguna yang sedang login,
     * diurutkan dari yang terbaru, dengan paginasi.
     */
    public function index()
    {
        $orders = Auth::user()
            ->orders()
            ->with(['items', 'payment'])
            ->latest()
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    /**
     * Tampilkan detail satu pesanan.
     * Menggunakan scoping pada relasi user untuk mencegah IDOR.
     *
     * @param  string  $orderNumber
     */
    public function show(string $orderNumber)
    {
        $order = Auth::user()
            ->orders()
            ->with(['items.product', 'items.variant', 'address', 'payment', 'shipment', 'voucher', 'returnRequest'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return view('orders.show', compact('order'));
    }

    /**
     * Batalkan pesanan jika statusnya masih memungkinkan.
     * Hanya pesanan berstatus 'pending' yang dapat dibatalkan oleh pelanggan.
     *
     * Menggunakan DB transaction + lockForUpdate untuk mencegah race condition
     * dengan webhook Midtrans yang bisa saja memproses pembayaran secara bersamaan.
     *
     * @param  string  $orderNumber
     */
    public function cancel(string $orderNumber)
    {
        // Ownership check — another user's order stays 404
        $order = Auth::user()
            ->orders()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $result = DB::transaction(function () use ($order) {
            // Re-fetch with a row lock to prevent concurrent writes
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            // Hanya izinkan pembatalan untuk pesanan yang belum diproses
            if ($locked->status !== 'pending') {
                return ['action' => 'not_pending'];
            }

            // Batalkan di sisi Midtrans sebelum mengubah database lokal
            $midtrans = app(MidtransService::class);
            $cancel   = $midtrans->cancelTransaction($locked->order_number);

            if (in_array($cancel['outcome'], ['cancelled', 'not_found'])) {
                $locked->update(['status' => 'dibatalkan']);

                if ($locked->payment) {
                    $locked->payment->update(['status' => 'failed']);
                }

                return ['action' => 'cancelled'];
            }

            // 'cannot_modify' or 'error' — do not change local data
            Log::warning('OrderController::cancel — Midtrans cancellation blocked', [
                'order_number' => $locked->order_number,
                'outcome'      => $cancel['outcome'],
                'status_code'  => $cancel['status_code'] ?? null,
                'message'      => $cancel['message'] ?? '',
            ]);

            return ['action' => 'blocked', 'outcome' => $cancel['outcome']];
        });

        if ($result['action'] === 'not_pending') {
            return redirect()->back()
                ->with('error', 'Pesanan tidak dapat dibatalkan pada status saat ini.');
        }

        if ($result['action'] === 'cancelled') {
            return redirect()->back()
                ->with('success', 'Pesanan #' . $order->order_number . ' berhasil dibatalkan.');
        }

        // blocked
        return redirect()->back()
            ->with('error', 'Pesanan belum bisa dibatalkan karena pembayaran sedang diproses. Muat ulang halaman beberapa saat lagi atau hubungi kami.');
    }

    /**
     * Kembalikan snap_token untuk melanjutkan pembayaran pesanan yang tertunda.
     *
     * Dipanggil via fetch() dari halaman pesanan saat tombol
     * "Bayar Sekarang" / "Ganti Metode Bayar" diklik. Response JSON:
     *   { "expired": false, "snap_token": "..." }  — token masih valid / berhasil digenerate
     *   { "expired": true, "message": "..." }      — token kedaluwarsa / pesanan tidak valid
     *
     * @param  string  $orderNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumePayment(string $orderNumber)
    {
        // Scoping melalui relasi user() sudah mencegah IDOR —
        // firstOrFail() akan 404 jika pesanan bukan milik user ini.
        $order = Auth::user()
            ->orders()
            ->with(['payment', 'items.product', 'items.variant', 'address'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        // Hanya pesanan berstatus 'pending' yang dapat dilanjutkan
        if ($order->status !== 'pending') {
            return response()->json([
                'expired' => true,
                'message' => 'Pesanan ini sudah tidak berstatus menunggu pembayaran.'
            ], 200);
        }

        $payment = $order->payment;

        // Jika payment ada dan belum expired serta memiliki snap_token
        if ($payment && !$payment->isExpired() && !empty($payment->snap_token)) {
            return response()->json([
                'expired'    => false,
                'snap_token' => $payment->snap_token,
            ], 200);
        }

        // Jika token sudah kedaluwarsa atau belum ada, generate Snap token baru
        try {
            $midtrans = app(\App\Services\MidtransService::class);
            $user = Auth::user();

            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'id'       => $item->variant_id ? $item->product_id . '-' . $item->variant_id : (string) $item->product_id,
                    'price'    => (int) $item->price,
                    'quantity' => $item->quantity,
                    'name'     => $item->variant_name 
                        ? substr($item->product_name . ' (' . $item->variant_name . ')', 0, 50) 
                        : substr($item->product_name, 0, 50),
                ];
            }

            if ($order->shipping_cost > 0) {
                $items[] = [
                    'id'       => 'SHIPPING',
                    'price'    => (int) $order->shipping_cost,
                    'quantity' => 1,
                    'name'     => 'Ongkos Kirim',
                ];
            }

            if ($order->voucher_discount > 0) {
                $items[] = [
                    'id'       => 'DISCOUNT',
                    'price'    => -(int) $order->voucher_discount,
                    'quantity' => 1,
                    'name'     => 'Diskon Voucher',
                ];
            }

            $snapData = $midtrans->createSnapToken([
                'id'    => $order->order_number,
                'total' => (int) $order->total_amount,
                'items' => $items,
            ], [
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? ($order->address?->phone ?? ''),
            ]);

            if ($payment) {
                $payment->update([
                    'snap_token' => $snapData['token'],
                    'expired_at' => now()->addHours(24),
                    'status'     => 'pending',
                ]);
            } else {
                $order->payment()->create([
                    'midtrans_order_id' => $order->order_number,
                    'amount'            => $order->total_amount,
                    'status'            => 'pending',
                    'snap_token'        => $snapData['token'],
                    'expired_at'        => now()->addHours(24),
                ]);
            }

            return response()->json([
                'expired'    => false,
                'snap_token' => $snapData['token'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['expired' => true, 'message' => 'Sesi pembayaran kedaluwarsa.'], 200);
        }
    }

    /**
     * Beli lagi (re-order) produk dari pesanan yang dibatalkan atau selesai.
     * Memasukkan produk ke keranjang belanja dan mengarahkan ke halaman checkout.
     *
     * @param  string  $orderNumber
     */
    public function reorder(string $orderNumber)
    {
        $order = Auth::user()
            ->orders()
            ->with(['items.product', 'items.variant'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $user = Auth::user();
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Akun Administrator tidak dapat berbelanja.');
        }

        $cart = $user->cart;
        $addedCount = 0;
        $outOfStockCount = 0;

        foreach ($order->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            // Periksa ketersediaan produk & varian
            if (!$product || !$product->is_active) {
                $outOfStockCount++;
                continue;
            }

            if ($item->variant_id && (!$variant || !$variant->is_active)) {
                $outOfStockCount++;
                continue;
            }

            $currentStock = $variant ? (int) $variant->stock : (int) $product->stock;
            if ($currentStock <= 0) {
                $outOfStockCount++;
                continue;
            }

            $qtyToAdd = min($item->quantity, $currentStock);

            $cart ??= $user->getOrCreateCart();

            $existingCartItem = $cart->items()
                ->where('product_id', $item->product_id)
                ->where('variant_id', $item->variant_id)
                ->first();

            if ($existingCartItem) {
                $newQty = min($existingCartItem->quantity + $qtyToAdd, $currentStock);
                $existingCartItem->update(['quantity' => $newQty]);
            } else {
                $cart->items()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'quantity'   => $qtyToAdd,
                ]);
            }

            $addedCount++;
        }

        if ($addedCount === 0) {
            return redirect()->back()
                ->with('error', 'Produk pada pesanan ini sedang habis atau tidak tersedia.');
        }

        $message = 'Produk dari pesanan #' . $order->order_number . ' telah dimasukkan ke keranjang belanja.';
        if ($outOfStockCount > 0) {
            $message .= ' Beberapa item mungkin dilewati karena stok habis.';
        }

        return redirect()->route('checkout.index')->with('success', $message);
    }

    /**
     * Konfirmasi penerimaan pesanan oleh pelanggan (dikirim → selesai).
     *
     * Hanya pesanan berstatus 'dikirim' yang dapat dikonfirmasi.
     * Menggunakan transaction + lockForUpdate untuk mencegah race condition.
     *
     * @param  string  $orderNumber
     */
    public function confirmReceived(string $orderNumber)
    {
        // Ownership check via user relation scoping — mencegah IDOR
        $order = Auth::user()
            ->orders()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $confirmed = DB::transaction(function () use ($order) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'dikirim') {
                return false;
            }

            $locked->update(['status' => 'selesai']);
            return true;
        });

        if (!$confirmed) {
            return redirect()
                ->route('orders.show', $orderNumber)
                ->with('error', 'Pesanan ini tidak dapat dikonfirmasi saat ini.');
        }

        return redirect()
            ->route('orders.show', $orderNumber)
            ->with('success', 'Terima kasih! Pesanan Anda telah dikonfirmasi diterima.');
    }
}
