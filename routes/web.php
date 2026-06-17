<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Arahkan halaman utama (/) ke HomeController
Route::get('/', [HomeController::class, 'index'])->name('home');

// Halaman Daftar Produk
Route::get('/products', [ProductController::class, 'index'])->name('products.index');

// Halaman Detail Produk
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

// Route bawaan Breeze (Login/Register)
require __DIR__.'/auth.php';