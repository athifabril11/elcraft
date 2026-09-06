<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ShopSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ReturnController - Pengajuan Retur oleh Pelanggan.
 *
 * Flow: GET create -> POST store -> GET show (halaman konfirmasi)
 *
 * Eligibility rules (enforced in both create & store):
 *   - Order must belong to the authenticated user (IDOR prevention via relation scoping)
 *   - Order status must be 'dikirim' or 'selesai'
 *   - No existing ReturnRequest for the order (unique per order)
 */
class ReturnController extends Controller
{
    /** Statuses that permit a return request. */
    private const ELIGIBLE_STATUSES = ['dikirim', 'selesai'];

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Load the order (ownership-scoped) and run eligibility checks.
     * Returns the order on success, or a RedirectResponse on failure.
     *
     * @param  string  $orderNumber
     * @return \App\Models\Order|\Illuminate\Http\RedirectResponse
     */
    private function resolveEligibleOrder(string $orderNumber): Order|\Illuminate\Http\RedirectResponse
    {
        // Ownership check — scoping through user relation prevents IDOR.
        // firstOrFail() returns 404 if the order doesn't belong to this user.
        $order = Auth::user()
            ->orders()
            ->with(['items', 'returnRequest'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if (!in_array($order->status, self::ELIGIBLE_STATUSES, true)) {
            return redirect()
                ->route('orders.show', $orderNumber)
                ->with('error', 'Retur hanya dapat diajukan untuk pesanan yang sedang dikirim atau sudah diterima.');
        }

        if ($order->returnRequest !== null) {
            return redirect()
                ->route('orders.returns.show', $orderNumber)
                ->with('error', 'Pengajuan retur untuk pesanan ini sudah ada.');
        }

        return $order;
    }

    /**
     * Generate a unique return number in the format RET-{Ymd}-{4 alphanumeric}.
     * Retries until no collision is found (mirrors CheckoutController approach
     * of building a unique order_id with time-based + random components).
     */
    private function generateReturnNumber(): string
    {
        do {
            $candidate = 'RET-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        } while (ReturnRequest::where('return_number', $candidate)->exists());

        return $candidate;
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    /**
     * Tampilkan form pengajuan retur.
     *
     * @param  string  $orderNumber
     */
    public function create(string $orderNumber)
    {
        $result = $this->resolveEligibleOrder($orderNumber);

        if ($result instanceof \Illuminate\Http\RedirectResponse) {
            return $result;
        }

        $order = $result;

        return view('orders.returns.create', compact('order'));
    }

    /**
     * Proses form dan buat ReturnRequest + ReturnItem rows.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string                    $orderNumber
     */
    public function store(Request $request, string $orderNumber)
    {
        // Re-run ownership + eligibility checks (never trust the form wasn't tampered with)
        $result = $this->resolveEligibleOrder($orderNumber);

        if ($result instanceof \Illuminate\Http\RedirectResponse) {
            return $result;
        }

        $order = $result;

        // ── Validation ───────────────────────────────────────────────────────

        $request->validate([
            'resolution_type' => 'required|string|in:tukar_barang,refund',
            'reason'          => 'required|string|max:1000',
            'evidence_photo'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'items'           => 'required|array|min:1',
            'items.*'         => 'required|integer|min:1',
        ]);

        // Build a lookup of order items by their ID for cross-checking quantities
        $orderItemsById = $order->items->keyBy('id');

        $selectedItems  = $request->input('items', []);

        $validatedItems = [];
        foreach ($selectedItems as $orderItemId => $requestedQty) {
            $requestedQty = (int) $requestedQty;

            if (!isset($orderItemsById[$orderItemId])) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Item pesanan tidak valid.');
            }

            $originalQty = (int) $orderItemsById[$orderItemId]->quantity;

            if ($requestedQty < 1 || $requestedQty > $originalQty) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Jumlah retur untuk "' . $orderItemsById[$orderItemId]->product_name . '" tidak valid (harus antara 1 dan ' . $originalQty . ').');
            }

            $validatedItems[$orderItemId] = $requestedQty;
        }

        if (empty($validatedItems)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Pilih minimal satu produk untuk diretur.');
        }

        // ── File Upload ──────────────────────────────────────────────────────
        // Same disk + convention as ReviewController:
        //   $request->file('image')->store('reviews', 'public')
        $evidencePath = null;
        if ($request->hasFile('evidence_photo')) {
            $evidencePath = $request->file('evidence_photo')->store('returns', 'public');
        }

        // ── DB Transaction ───────────────────────────────────────────────────

        $returnRequest = DB::transaction(function () use ($order, $request, $evidencePath, $validatedItems) {
            $returnRequest = ReturnRequest::create([
                'order_id'            => $order->id,
                'user_id'             => Auth::id(),
                'return_number'       => $this->generateReturnNumber(),
                'resolution_type'     => $request->resolution_type,
                'reason'              => $request->reason,
                'evidence_photo_path' => $evidencePath,
                'status'              => 'diajukan',
            ]);

            foreach ($validatedItems as $orderItemId => $qty) {
                $returnRequest->items()->create([
                    'return_id'     => $returnRequest->id,
                    'order_item_id' => $orderItemId,
                    'quantity'      => $qty,
                ]);
            }

            return $returnRequest;
        });

        return redirect()
            ->route('orders.returns.show', $orderNumber)
            ->with('success', 'Pengajuan retur berhasil dikirim. Nomor retur Anda: ' . $returnRequest->return_number);
    }

    /**
     * Tampilkan halaman konfirmasi / detail retur.
     *
     * @param  string  $orderNumber
     */
    public function show(string $orderNumber)
    {
        // Ownership check via user relation scoping
        $order = Auth::user()
            ->orders()
            ->with(['returnRequest.items.orderItem'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $returnRequest = $order->returnRequest;

        if (!$returnRequest) {
            return redirect()
                ->route('orders.show', $orderNumber)
                ->with('error', 'Belum ada pengajuan retur untuk pesanan ini.');
        }

        // Build WhatsApp link — admin number from ShopSetting (never hardcoded).
        // Strip all non-digit characters first, then require at least 10 digits so
        // that placeholder values like '62XXXXXXXXXX' (which contain letters and
        // collapse to only '62') do not produce a broken wa.me link.
        $adminWhatsapp = ShopSetting::get('admin_whatsapp_number', '');
        $digitsOnly    = preg_replace('/\D/', '', $adminWhatsapp);

        $waMessage = sprintf(
            'Halo Admin el Craft, saya ingin mengajukan retur untuk pesanan %s. Nomor retur: %s. Alasan: %s. Mohon diproses, terima kasih.',
            $order->order_number,
            $returnRequest->return_number,
            $returnRequest->reason,
        );

        $waLink = (strlen($digitsOnly) >= 10)
            ? 'https://wa.me/' . $digitsOnly . '?text=' . urlencode($waMessage)
            : null;

        return view('orders.returns.show', compact('order', 'returnRequest', 'waLink', 'waMessage'));
    }
}

