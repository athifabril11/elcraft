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
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        $cart = Auth::user()->getOrCreateCart();

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
        $query = $cart->items()->where('product_id', $product->id);
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
            $existingItem->save();
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant ? $variant->id : null,
                'quantity' => $requestedQuantity,
            ]);
        }

        // Muat ulang items untuk menghitung total terbaru
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

        $cart->load('items');

        return response()->json([
            'success' => true,
            'message' => 'Produk dihapus dari keranjang belanja.',
            'cart_total' => $cart->getTotalPrice(),
            'cart_count' => $cart->getTotalItems(),
        ]);
    }

    /**
     * Mendapatkan total item di dalam keranjang belanja untuk lencana navigasi.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(): JsonResponse
    {
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
