<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * WishlistController — Kelola daftar keinginan (wishlist) pelanggan.
 *
 * Semua endpoint mengembalikan JSON agar kompatibel dengan AJAX dari storefront.
 */
class WishlistController extends Controller
{
    /**
     * Tampilkan halaman wishlist pelanggan.
     * Eager load product + gambar + kategori dalam satu kueri (N+1 prevention).
     */
    public function index()
    {
        $wishlists = Wishlist::where('user_id', Auth::id())
            ->with(['product' => function ($query) {
                $query->with(['images', 'category'])
                      ->withCount(['reviews as approved_reviews_count' => function ($q) {
                          $q->where('is_approved', true);
                      }]);
            }])
            ->latest()
            ->get();

        return view('wishlist.index', compact('wishlists'));
    }

    /**
     * Toggle wishlist — tambahkan jika belum ada, hapus jika sudah ada.
     * Idempotent: memanggil endpoint yang sama berulang kali menghasilkan state yang konsisten.
     *
     * POST /wishlist/toggle
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $userId    = Auth::id();
        $productId = $request->product_id;

        $existing = Wishlist::where('user_id', $userId)
                            ->where('product_id', $productId)
                            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
            $message    = 'Produk dihapus dari wishlist.';
        } else {
            Wishlist::create([
                'user_id'    => $userId,
                'product_id' => $productId,
            ]);
            $inWishlist = true;
            $message    = 'Produk ditambahkan ke wishlist.';
        }

        $count = Wishlist::where('user_id', $userId)->count();

        return response()->json([
            'success'     => true,
            'in_wishlist' => $inWishlist,
            'message'     => $message,
            'count'       => $count,
        ]);
    }

    /**
     * Hapus satu item dari wishlist (digunakan dari halaman wishlist).
     *
     * DELETE /wishlist/{wishlist}
     */
    public function destroy(Wishlist $wishlist): JsonResponse
    {
        // Pastikan item ini milik pengguna yang sedang login
        if ($wishlist->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $wishlist->delete();
        $count = Wishlist::where('user_id', Auth::id())->count();
        $ids   = Wishlist::where('user_id', Auth::id())->pluck('product_id');

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus dari wishlist.',
            'count'   => $count,
            'ids'     => $ids,
        ]);
    }

    /**
     * Kembalikan jumlah item wishlist untuk badge sinkronisasi.
     *
     * GET /wishlist/count
     */
    public function count(): JsonResponse
    {
        $count = Auth::check()
            ? Wishlist::where('user_id', Auth::id())->count()
            : 0;

        // Juga kembalikan daftar product_id untuk sinkronisasi state button
        $ids = Auth::check()
            ? Wishlist::where('user_id', Auth::id())->pluck('product_id')
            : collect();

        return response()->json([
            'count' => $count,
            'ids'   => $ids,
        ]);
    }
}
