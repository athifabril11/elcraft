<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin\ReviewController — Moderasi ulasan produk dari pelanggan.
 *
 * Status logical:
 *   is_approved = null  → pending (belum dimoderasi)
 *   is_approved = true  → disetujui
 *   is_approved = false → ditolak
 */
class ReviewController extends Controller
{
    private const FILTERS = ['all', 'pending', 'approved', 'rejected'];

    /**
     * List reviews with optional status filter and per-status counts.
     */
    public function index(Request $request)
    {
        $filter = $request->query('status', 'pending');

        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'pending';
        }

        $query = Review::with(['user', 'product'])->latest('created_at');

        match ($filter) {
            'pending'  => $query->whereNull('is_approved'),
            'approved' => $query->where('is_approved', true),
            'rejected' => $query->where('is_approved', false),
            default    => null, // 'all' — no extra scope
        };

        $reviews = $query->paginate(20)->withQueryString();

        $counts = [
            'all'      => Review::count(),
            'pending'  => Review::whereNull('is_approved')->count(),
            'approved' => Review::where('is_approved', true)->count(),
            'rejected' => Review::where('is_approved', false)->count(),
        ];

        return view('admin.reviews.index', compact('reviews', 'counts', 'filter'));
    }

    /**
     * Approve a pending review.
     */
    public function approve(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => true]);

        return redirect()->route('admin.reviews.index', ['status' => request()->query('status', 'pending')])
            ->with('success', 'Ulasan dari "' . ($review->user?->name ?? 'Anonim') . '" telah disetujui.');
    }

    /**
     * Reject (disapprove) a review.
     */
    public function reject(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => false]);

        return redirect()->route('admin.reviews.index', ['status' => request()->query('status', 'pending')])
            ->with('success', 'Ulasan dari "' . ($review->user?->name ?? 'Anonim') . '" telah ditolak.');
    }

    /**
     * Permanently delete a review.
     */
    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return redirect()->route('admin.reviews.index', ['status' => request()->query('status', 'all')])
            ->with('success', 'Ulasan berhasil dihapus.');
    }
}
