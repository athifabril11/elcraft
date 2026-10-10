<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReturnStatusMail;
use App\Models\ReturnRequest;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Admin\ReturnController - Pengelolaan pengajuan retur pelanggan.
 *
 * Pipeline: diajukan -> disetujui -> diproses -> selesai
 *                     -> ditolak
 *
 * approve()       - diajukan  -> disetujui
 * reject()        - diajukan  -> ditolak (wajib admin_notes)
 * advanceStatus() - disetujui -> diproses -> selesai
 */
class ReturnController extends Controller
{
    private const STATUSES = ['diajukan', 'disetujui', 'diproses', 'selesai', 'ditolak'];

    /**
     * List return requests with optional status filter and per-status counts.
     */
    public function index(Request $request)
    {
        $filter = $request->query('status');

        $query = ReturnRequest::with(['order', 'user', 'items.orderItem'])->latest();

        if ($filter && in_array($filter, self::STATUSES, true)) {
            $query->where('status', $filter);
        }

        $returns = $query->paginate(20)->withQueryString();

        // Per-status counts for the filter tabs
        $counts = ReturnRequest::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.returns.index', compact('returns', 'counts', 'filter'));
    }

    /**
     * Tampilkan detail lengkap pengajuan retur pelanggan.
     */
    public function show(ReturnRequest $return)
    {
        $return->loadMissing([
            'order.items.product',
            'order.items.variant',
            'order.user',
            'order.address',
            'order.payment',
            'user',
            'items.orderItem.product',
            'items.orderItem.variant',
        ]);

        return view('admin.returns.show', compact('return'));
    }

    /**
     * Approve a diajukan return.
     * refund_amount required only when resolution_type is 'refund'.
     */
    public function approve(Request $request, ReturnRequest $return): RedirectResponse
    {
        $return->loadMissing('order');

        $request->validate([
            'admin_notes'   => 'nullable|string|max:2000',
            'refund_amount' => $return->resolution_type === 'refund'
                ? ['required', 'numeric', 'min:0', function ($attribute, $value, $fail) use ($return) {
                    $orderTotal = $return->order->total_amount ?? 0;
                    if ($value > $orderTotal) {
                        $fail('Nominal refund tidak boleh melebihi total pesanan (Rp ' . number_format($orderTotal, 0, ',', '.') . ').');
                    }
                }]
                : 'nullable|numeric|min:0',
        ]);

        $result = DB::transaction(function () use ($return, $request) {
            $locked = ReturnRequest::lockForUpdate()->findOrFail($return->id);

            if ($locked->status !== 'diajukan') {
                return null;
            }

            $locked->update([
                'status'        => 'disetujui',
                'refund_amount' => $request->input('refund_amount'),
                'admin_notes'   => $request->input('admin_notes'),
                'resolved_at'   => now(),
            ]);

            return $locked;
        });

        if ($result === null) {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Retur ini tidak dalam status Diajukan dan tidak dapat disetujui.');
        }

        $this->sendReturnStatusEmail($return, 'approved');

        return redirect()->route('admin.returns.index')
            ->with('success', 'Retur #' . $return->return_number . ' telah disetujui.');
    }

    /**
     * Reject a diajukan return.
     * admin_notes is required so the reason can be communicated.
     */
    public function reject(Request $request, ReturnRequest $return): RedirectResponse
    {
        $request->validate([
            'admin_notes' => 'required|string|min:5|max:2000',
        ]);

        $result = DB::transaction(function () use ($return, $request) {
            $locked = ReturnRequest::lockForUpdate()->findOrFail($return->id);

            if ($locked->status !== 'diajukan') {
                return null;
            }

            $locked->update([
                'status'      => 'ditolak',
                'admin_notes' => $request->admin_notes,
                'resolved_at' => now(),
            ]);

            return $locked;
        });

        if ($result === null) {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Retur ini tidak dalam status Diajukan dan tidak dapat ditolak.');
        }

        $this->sendReturnStatusEmail($return, 'rejected');

        return redirect()->route('admin.returns.index')
            ->with('success', 'Retur #' . $return->return_number . ' telah ditolak.');
    }

    /**
     * Advance status through the post-approval pipeline:
     *   disetujui -> diproses -> selesai
     *
     * diajukan/ditolak are blocked here (must use approve/reject first).
     * Uses DB transaction with row lock to prevent races.
     */
    public function advanceStatus(ReturnRequest $return): RedirectResponse
    {
        $map = [
            'disetujui' => 'diproses',
            'diproses'  => 'selesai',
        ];

        $next = DB::transaction(function () use ($return, $map) {
            $locked = ReturnRequest::lockForUpdate()->findOrFail($return->id);

            if (!array_key_exists($locked->status, $map)) {
                return null;
            }

            $next = $map[$locked->status];

            if ($locked->resolution_type === 'refund' && $locked->refund_status === null && $next === 'selesai') {
                return 'refund_pending';
            }

            $locked->update(['status' => $next]);
            return $next;
        });

        if ($next === 'refund_pending') {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Retur ini berjenis refund — proses refund (API atau manual) selebih dulu sebelum menandai selesai.');
        }

        if ($next === null) {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Status retur ini tidak dapat dimajukan dari sini.');
        }

        $labels = ['diproses' => 'Diproses', 'selesai' => 'Selesai'];
        $label  = $labels[$next] ?? $next;

        return redirect()->route('admin.returns.index')
            ->with('success', 'Retur #' . $return->return_number . ' diperbarui ke status "' . $label . '".');
    }

    /**
     * Eksekusi refund via Midtrans Refund API untuk retur yang sudah disetujui.
     *
     * Alur: guard → panggil API (di luar transaksi DB) → persist singkat
     * dengan lock. Gagal API tidak mengubah status retur sehingga bisa dicoba ulang.
     */
    public function refund(ReturnRequest $return, RefundService $refunds): RedirectResponse
    {
        $result = $refunds->processApiRefund($return);

        if ($result['success']) {
            $this->sendReturnStatusEmail($return, 'refund_completed');
        }

        return redirect()->route('admin.returns.index')->with(
            $result['success'] ? 'success' : 'error',
            'Retur #' . $return->return_number . ': ' . $result['message']
        );
    }

    /**
     * Tandai refund yang dieksekusi di luar API (dashboard Midtrans / transfer bank).
     * Wajib mencantumkan catatan bukti pengembalian dana.
     */
    public function refundManual(Request $request, ReturnRequest $return, RefundService $refunds): RedirectResponse
    {
        $request->validate([
            'manual_notes' => 'required|string|min:5|max:2000',
        ]);

        $result = $refunds->markManual($return, $request->input('manual_notes'));

        if ($result['success']) {
            $this->sendReturnStatusEmail($return, 'refund_completed');
        }

        return redirect()->route('admin.returns.index')->with(
            $result['success'] ? 'success' : 'error',
            'Retur #' . $return->return_number . ': ' . $result['message']
        );
    }

    /**
     * Kirim email notifikasi status retur ke pelanggan.
     * Kegagalan SMTP tidak boleh merusak alur admin — dicatat sebagai error log.
     */
    private function sendReturnStatusEmail(ReturnRequest $return, string $event): void
    {
        $return->loadMissing('user');

        if (!$return->user?->email) {
            return;
        }

        try {
            Mail::to($return->user->email)->send(new ReturnStatusMail($return, $event));
        } catch (\Throwable $e) {
            Log::error('ReturnStatusMail gagal terkirim', [
                'return_number' => $return->return_number,
                'event'         => $event,
                'to_email'      => $return->user->email,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}