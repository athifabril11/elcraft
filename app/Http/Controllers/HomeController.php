<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        // Ambil semua kategori yang aktif
        $categories = Category::where('is_active', true)->get();

        // Ambil produk yang aktif & featured (unggulan) — New Arrivals
        $featuredProducts = Product::where('is_active', true)
                                   ->where('is_featured', true)
                                   ->with(['category', 'variants', 'images'])
                                   ->get();

        // ── Produk Terpopuler ─────────────────────────────────────────────
        // Primary: order by total units sold from completed orders
        $paidStatuses = ['paid', 'diproses', 'dikirim', 'selesai'];

        $popularProducts = Product::where('products.is_active', true)
            ->with(['category', 'variants', 'images'])
            ->leftJoin('order_items', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('orders', function ($join) use ($paidStatuses) {
                $join->on('orders.id', '=', 'order_items.order_id')
                     ->whereIn('orders.status', $paidStatuses);
            })
            ->select('products.*', DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_sold'))
            ->groupBy('products.id')
            ->orderByDesc('total_sold')
            ->limit(8)
            ->get();

        // Fallback: if no paid orders exist yet, use latest active products
        if ($popularProducts->every(fn($p) => $p->total_sold == 0)) {
            $popularProducts = Product::where('is_active', true)
                ->with(['category', 'variants', 'images'])
                ->latest()
                ->limit(8)
                ->get();
        }

        // ── Sedang Diskon ─────────────────────────────────────────────────
        // Mirror the exact logic of isDiscountActive() in SQL:
        //   discount_type != 'none'
        //   AND discount_start IS NOT NULL AND discount_end IS NOT NULL
        //   AND discount_start <= today AND discount_end >= today
        $today = now()->toDateString();

        $discountedProducts = Product::where('is_active', true)
            ->where('discount_type', '!=', 'none')
            ->whereNotNull('discount_start')
            ->whereNotNull('discount_end')
            ->where('discount_start', '<=', $today)
            ->where('discount_end', '>=', $today)
            ->with(['category', 'variants', 'images'])
            ->limit(8)
            ->get();

        // ── Banner Promo Carousel ─────────────────────────────────────────
        $banners = Banner::active()->orderBy('sort_order', 'asc')->orderByDesc('created_at')->get();

        // Kirim data ke view
        return view('home', compact('categories', 'featuredProducts', 'popularProducts', 'discountedProducts', 'banners'));
    }
}