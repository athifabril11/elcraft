<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class CartController
 *
 * Kontroler untuk mengelola aksi terkait keranjang belanja (Cart & CartItem).
 */
class CartController extends Controller
{
    /**
     * Menampilkan halaman utama keranjang belanja.
     * Hanya dapat diakses oleh pengguna yang sudah login (dilindungi oleh middleware guest.modal).
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        if (Auth::user()?->role === 'admin') {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Akun Administrator tidak memiliki keranjang belanja. Silakan gunakan akun Pelanggan untuk berbelanja.');
        }

        $cart = Auth::user()->getOrCreateCart();
        $cart->load(['allItems.product.category', 'allItems.product.images', 'allItems.variant']);

        return view('cart.index', compact('cart'));
    }

    /**
     * Menambahkan item produk atau varian ke dalam keranjang belanja.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request): JsonResponse
    {
        if (Auth::user()?->role === 'admin') {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Akun Administrator tidak dapat berbelanja atau menambahkan produk ke keranjang. Silakan gunakan akun Pelanggan.',
            ], 403);
        }
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);
        $variant = $request->variant_id ? ProductVariant::findOrFail($request->variant_id) : null;

        // Validasi stok yang tersedia
        $stock = $variant ? $variant->stock : $product->stock;

        $cart = Auth::user()->getOrCreateCart();

        // Cari apakah item dengan konfigurasi produk & varian yang sama sudah ada di keranjang
        $query = $cart->allItems()->where('product_id', $product->id);
        if ($variant) {
            $query->where('variant_id', $variant->id);
        } else {
            $query->whereNull('variant_id');
        }
        $existingItem = $query->first();

        $requestedQuantity = (int) $request->input('quantity', 1);
        $totalQuantity = $existingItem ? ($existingItem->quantity + $requestedQuantity) : $requestedQuantity;

        // Cek batasan stok
        if ($totalQuantity > $stock) {
            return response()->json([
                'success' => false,
                'message' => 'Stok produk tidak mencukupi.',
            ], 422);
        }

        // Simpan ke database
        if ($existingItem) {
            $existingItem->quantity = $totalQuantity;
            if (\Illuminate\Support\Facades\Schema::hasColumn('cart_items', 'is_selected')) {
                $existingItem->is_selected = true;
            }
            $existingItem->save();
        } else {
            $createData = [
                'product_id' => $product->id,
                'variant_id' => $variant ? $variant->id : null,
                'quantity' => $requestedQuantity,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('cart_items', 'is_selected')) {
                $createData['is_selected'] = true;
            }
            $cart->allItems()->create($createData);
        }

        // Muat ulang items untuk menghitung total terbaru
        $cart->unsetRelation('items');
        $cart->load('items');

        return response()->json([
            'success' => true,
            'message' => '"' . $product->name . '" berhasil ditambahkan ke keranjang belanja.',
            'cart_count' => $cart->getTotalItems(),
            'cart_total' => $cart->getTotalPrice(),
        ]);
    }

    /**
     * Memperbarui kuantitas item di dalam keranjang belanja.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        if (Auth::user()?->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Administrator tidak dapat berbelanja.',
            ], 403);
        }

        $request->validate([
            'item_id' => 'required|exists:cart_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $item = CartItem::findOrFail($request->item_id);
        $cart = Auth::user()->getOrCreateCart();

        // Pastikan item keranjang belanja dimiliki oleh pengguna yang sedang login
        if ($item->cart_id !== $cart->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses tidak sah.',
            ], 403);
        }

        // Validasi ketersediaan stok
        $stock = $item->variant ? $item->variant->stock : $item->product->stock;
        $newQuantity = (int) $request->quantity;

        if ($newQuantity > $stock) {
            return response()->json([
                'success' => false,
                'message' => 'Stok produk tidak mencukupi.',
            ], 422);
        }

        $item->quantity = $newQuantity;
        $item->save();

        $cart->unsetRelation('items');
        $cart->load('items');

        return response()->json([
            'success' => true,
            'message' => 'Keranjang belanja berhasil diperbarui.',
            'item_subtotal' => $item->getSubtotal(),
            'cart_total' => $cart->getTotalPrice(),
            'cart_count' => $cart->getTotalItems(),
        ]);
    }

    /**
     * Menghapus item dari keranjang belanja.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove(Request $request): JsonResponse
    {
        if (Auth::user()?->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Administrator tidak dapat berbelanja.',
            ], 403);
        }

        $request->validate([
            'item_id' => 'required|exists:cart_items,id',
        ]);

        $item = CartItem::findOrFail($request->item_id);
        $cart = Auth::user()->getOrCreateCart();

        // Pastikan item dimiliki oleh pengguna yang sedang login
        if ($item->cart_id !== $cart->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses tidak sah.',
            ], 403);
        }

        $item->delete();

        $cart->unsetRelation('items');
        $cart->load('items');

        return response()->json([
            'success' => true,
            'message' => 'Produk dihapus dari keranjang belanja.',
            'cart_total' => $cart->getTotalPrice(),
            'cart_count' => $cart->getTotalItems(),
        ]);
    }

    /**
     * "Beli Sekarang" — menandai item yang dibeli sebagai terpilih (checked)
     * dan mematikan checklist untuk item lain di keranjang tanpa menghapusnya.
     * Item lain tetap tersimpan di keranjang untuk transaksi berikutnya.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function buyNow(Request $request): JsonResponse
    {
        if (Auth::user()?->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Administrator tidak dapat berbelanja.',
            ], 403);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);
        $variant = $request->variant_id ? ProductVariant::findOrFail($request->variant_id) : null;

        // Validasi stok
        $stock = $variant ? $variant->stock : $product->stock;
        $requestedQuantity = (int) $request->input('quantity', 1);

        if ($requestedQuantity > $stock) {
            return response()->json([
                'success' => false,
                'message' => 'Stok produk tidak mencukupi.',
            ], 422);
        }

        $cart = Auth::user()->getOrCreateCart();

        // 1. Matikan checklist untuk seluruh item yang sudah ada di keranjang (tetap tersimpan di database)
        if (\Illuminate\Support\Facades\Schema::hasColumn('cart_items', 'is_selected')) {
            $cart->allItems()->update(['is_selected' => false]);
        }

        // 2. Tambahkan atau perbarui item Beli Sekarang dengan status terpilih (is_selected = true)
        $query = $cart->allItems()->where('product_id', $product->id);
        if ($variant) {
            $query->where('variant_id', $variant->id);
        } else {
            $query->whereNull('variant_id');
        }
        $existingItem = $query->first();

        $targetItemId = null;
        if ($existingItem) {
            $existingItem->quantity = $requestedQuantity;
            if (\Illuminate\Support\Facades\Schema::hasColumn('cart_items', 'is_selected')) {
                $existingItem->is_selected = true;
            }
            $existingItem->save();
            $targetItemId = $existingItem->id;
        } else {
            $createData = [
                'product_id' => $product->id,
                'variant_id' => $variant ? $variant->id : null,
                'quantity'   => $requestedQuantity,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('cart_items', 'is_selected')) {
                $createData['is_selected'] = true;
            }
            $newItem = $cart->allItems()->create($createData);
            $targetItemId = $newItem->id;
        }

        // Simpan status pilihan item tunggal di session sebagai fallback
        session(['selected_cart_item_ids' => [$targetItemId]]);

        // Arahkan ke halaman keranjang terlebih dahulu agar pengguna dapat melihat checklist
        return response()->json([
            'success'  => true,
            'redirect' => route('cart.index'),
        ]);
    }

    /**
     * Mengubah status centang (checklist) untuk 1 item di keranjang.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleSelect(Request $request): JsonResponse
    {
        if (Auth::user()?->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Administrator tidak dapat berbelanja.',
            ], 403);
        }

        $request->validate([
            'item_id'     => 'required|exists:cart_items,id',
            'is_selected' => 'required|boolean',
        ]);

        $cart = Auth::user()->getOrCreateCart();
        $item = $cart->allItems()->findOrFail($request->item_id);

        if (\Illuminate\Support\Facades\Schema::hasColumn('cart_items', 'is_selected')) {
            $item->is_selected = $request->boolean('is_selected');
            $item->save();
        }

        // Sinkronisasi session fallback
        $selectedIds = session('selected_cart_item_ids');
        if ($selectedIds === null) {
            $selectedIds = $cart->allItems()->pluck('id')->toArray();
        }
        if ($request->boolean('is_selected')) {
            $selectedIds[] = $item->id;
            $selectedIds = array_values(array_unique($selectedIds));
        } else {
            $selectedIds = array_values(array_diff($selectedIds, [$item->id]));
        }
        session(['selected_cart_item_ids' => $selectedIds]);

        $cart->unsetRelation('items');
        $cart->load('items');

        return response()->json([
            'success'        => true,
            'is_selected'    => (bool) $item->is_selected,
            'cart_total'     => $cart->getTotalPrice(),
            'selected_count' => $cart->items()->count(),
        ]);
    }

    /**
     * Mengubah status centang (checklist) untuk seluruh item di keranjang (Pilih Semua).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleSelectAll(Request $request): JsonResponse
    {
        if (Auth::user()?->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Administrator tidak dapat berbelanja.',
            ], 403);
        }

        $request->validate([
            'is_selected' => 'required|boolean',
        ]);

        $cart = Auth::user()->getOrCreateCart();
        $isSelected = $request->boolean('is_selected');

        if (\Illuminate\Support\Facades\Schema::hasColumn('cart_items', 'is_selected')) {
            $cart->allItems()->update(['is_selected' => $isSelected]);
        }

        // Sinkronisasi session fallback
        if ($isSelected) {
            session(['selected_cart_item_ids' => $cart->allItems()->pluck('id')->toArray()]);
        } else {
            session(['selected_cart_item_ids' => []]);
        }

        $cart->unsetRelation('items');
        $cart->load('items');

        return response()->json([
            'success'        => true,
            'cart_total'     => $cart->getTotalPrice(),
            'selected_count' => $cart->items()->count(),
        ]);
    }

    /**
     * Mendapatkan total item di dalam keranjang belanja untuk lencana navigasi.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(): JsonResponse
    {
        // Akun Admin tidak memiliki keranjang belanja — kembalikan 0 untuk lencana navbar.
        if (Auth::user()?->role === 'admin') {
            return response()->json(['count' => 0]);
        }

        if (Auth::check()) {
            $cart = Auth::user()->cart;
            $count = $cart ? $cart->getTotalItems() : 0;
        } else {
            $count = 0;
        }

        return response()->json([
            'count' => $count,
        ]);
    }
}
