<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Admin\OrderController — Manajemen pesanan & pembaruan status di panel admin.
 *
 * Scope: list all orders (paginated, with status filter & search),
 * serve order detail as JSON, and advance status through the
 * paid → diproses → dikirim pipeline (the last step requires a tracking number
 * and triggers an email notification to the customer).
 */
class OrderController extends Controller
{
    private const STATUSES = ['pending', 'paid', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];

    /**
     * List all orders, newest first, with optional status filter and search.
     */
    public function index(Request $request)
    {
        $filter = $request->query('status');
        $search = trim((string) $request->query('q', ''));
        $paymentCaptured = $request->query('payment_captured') == '1';

        $query = Order::with(['user', 'items', 'address', 'payment'])->latest();

        if ($paymentCaptured) {
            $query->where('status', 'dibatalkan')
                  ->whereHas('payment', fn ($q) => $q->where('status', 'success'));
        } elseif ($filter && in_array($filter, self::STATUSES, true)) {
            $query->where('status', $filter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->paginate(15)->withQueryString();

        // Per-status counts for filter tabs
        $counts = Order::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $capturedCount = Order::where('status', 'dibatalkan')
            ->whereHas('payment', fn ($q) => $q->where('status', 'success'))
            ->count();

        return view('admin.orders.index', compact('orders', 'counts', 'filter', 'search', 'paymentCaptured', 'capturedCount'));
    }

    /**
     * Return order detail as JSON for the Alpine.js modal.
     * Includes customer/address info, items, payment, shipment, and courier data.
     */
    public function detail(Order $order): JsonResponse
    {
        $order->loadMissing(['address', 'items', 'payment', 'shipment', 'user']);

        $address = $order->address;
        $payment = $order->payment;
        $shipment = $order->shipment;

        return response()->json([
            'order_number' => $order->order_number,
            'status'       => $order->status,
            'created_at'   => $order->created_at->format('d M Y, H:i'),
            'total_amount' => $order->total_amount,

            'customer' => $address ? [
                'recipient_name' => $address->recipient_name,
                'phone'          => $address->phone,
                'full_address'   => $address->full_address,
                'district'       => $address->district,
                'city'           => $address->city,
                'province'       => $address->province,
                'postal_code'    => $address->postal_code,
            ] : null,

            'courier'         => $order->courier,
            'courier_service' => $order->courier_service,
            'shipping_cost'   => $order->shipping_cost,
            'formatted_courier' => $order->formatted_courier,

            'payment' => $payment ? [
                'formatted_method' => $payment->formatted_method,
                'status'           => $payment->status,
            ] : null,

            'items' => $order->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity'     => $item->quantity,
                'price'        => $item->price,
                'subtotal'     => $item->subtotal,
            ])->values()->all(),

            'shipment' => $shipment ? [
                'tracking_number' => $shipment->tracking_number,
                'shipped_at'      => $shipment->shipped_at?->format('d M Y, H:i'),
            ] : null,
        ]);
    }

    /**
     * Advance an order's status one step forward through the pipeline:
     *   paid → diproses → dikirim
     *
     * The transition to 'dikirim' requires a non-empty tracking_number in the request.
     * Validation is performed before opening the transaction to keep the closure clean.
     *
     * Uses a DB transaction with a row-level lock to prevent races.
     * After advancing to 'dikirim', creates/updates a Shipment record and sends an
     * email notification to the customer. Mail failures are caught and logged so they
     * never roll back the status update.
     */
    public function advanceStatus(Request $request, Order $order): RedirectResponse
    {
        // Validate tracking number BEFORE opening the transaction,
        // but only when the order is currently 'diproses' (the transition that needs it).
        // We do a lightweight re-read of status to decide whether validation is required,
        // which is safe here because the authoritative check is still inside the locked transaction.
        $currentStatus = Order::where('id', $order->id)->value('status');

        if ($currentStatus === 'diproses') {
            $request->validate([
                'tracking_number' => ['required', 'string', 'max:100'],
            ], [
                'tracking_number.required' => 'Nomor resi wajib diisi sebelum menandai pesanan sebagai dikirim.',
                'tracking_number.max'      => 'Nomor resi maksimal 100 karakter.',
            ]);
        }

        $statusLabels = [
            'diproses' => 'Sedang Diproses',
            'dikirim'  => 'Sedang Dikirim',
        ];

        $next = DB::transaction(function () use ($request, $order) {
            // Re-read the row inside the transaction with a lock
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            $map = [
                'paid'     => 'diproses',
                'diproses' => 'dikirim',
            ];

            if (!array_key_exists($locked->status, $map)) {
                return null; // signal rejection
            }

            $nextStatus = $map[$locked->status];

            // Create/update the Shipment record when advancing to 'dikirim'
            if ($nextStatus === 'dikirim') {
                Shipment::updateOrCreate(
                    ['order_id' => $locked->id],
                    [
                        'courier'         => $locked->courier ?? '',
                        'service'         => $locked->courier_service ?? '',
                        'tracking_number' => $request->input('tracking_number'),
                        'shipping_cost'   => $locked->shipping_cost ?? 0,
                        'shipped_at'      => now(),
                    ]
                );
            }

            $locked->update(['status' => $nextStatus]);

            return $nextStatus;
        });

        if ($next === null) {
            return redirect()
                ->route('admin.orders.index')
                ->with('error', 'Status pesanan ini tidak bisa dimajukan dari halaman ini.');
        }

        $label = $statusLabels[$next] ?? $next;

        // Send shipment email notification when order advances to 'dikirim'
        $emailNote = '';
        if ($next === 'dikirim') {
            $order->load(['address', 'items', 'shipment', 'user']);

            if ($order->user?->email) {
                try {
                    Mail::to($order->user->email)->send(new OrderShippedMail($order));
                    $emailNote = ' Email resi telah dikirim ke pelanggan.';
                } catch (\Throwable $e) {
                    Log::error('OrderShippedMail gagal terkirim', [
                        'order_id'    => $order->id,
                        'order_number' => $order->order_number,
                        'to_email'    => $order->user->email,
                        'error'       => $e->getMessage(),
                    ]);
                    $emailNote = ' Namun email notifikasi resi gagal terkirim.';
                }
            }
        }

        return redirect()
            ->route('admin.orders.index')
            ->with('success', "Pesanan #{$order->order_number} berhasil diperbarui ke status \"{$label}\".{$emailNote}");
    }
}
