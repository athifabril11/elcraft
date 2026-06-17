<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Ambil semua kategori aktif untuk filter sidebar
        $categories = Category::where('is_active', true)->get();

        // Inisialisasi query dengan relasi category dan images
        $query = Product::where('is_active', true)
            ->with(['category', 'images']);

        // 1. Filter Pencarian Nama
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // 2. Filter Kategori
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // 3. Filter Rentang Harga
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // 4. Pengurutan (Sorting)
        $sortBy = $request->input('sort_by', 'latest');
        switch ($sortBy) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'popular':
                $query->orderBy('is_featured', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Paginate hasil query dengan query string params dipertahankan
        $products = $query->paginate(9)->withQueryString();

        return view('products.index', compact('products', 'categories'));
    }

    public function show(string $slug)
    {
        // Cari produk aktif berdasarkan slug, eager-load semua relasi yang dibutuhkan
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'category',
                'images' => fn($q) => $q->orderBy('sort_order'),
                'variants'  => fn($q) => $q->where('is_active', true),
                'reviews'   => fn($q) => $q->where('is_approved', true)->with('user')->latest('id')->take(10),
            ])
            ->firstOrFail();

        // Produk terkait dari kategori yang sama (max 4, exclude produk ini sendiri)
        $relatedProducts = Product::where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['category', 'images'])
            ->inRandomOrder()
            ->take(4)
            ->get();

        return view('products.show', compact('product', 'relatedProducts'));
    }
}
