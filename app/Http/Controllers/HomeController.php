<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        // Ambil semua kategori yang aktif
        $categories = Category::where('is_active', true)->get();

        // Ambil produk yang aktif & featured (unggulan)
        $featuredProducts = Product::where('is_active', true)
                                   ->where('is_featured', true)
                                   ->with('category')
                                   ->get();

        // Kirim data ke view
        return view('home', compact('categories', 'featuredProducts'));
    }
}