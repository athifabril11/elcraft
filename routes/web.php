<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Arahkan halaman utama (/) ke HomeController
Route::get('/', [HomeController::class, 'index'])->name('home');

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MidtransWebhookController;

// Halaman Daftar Produk
Route::get('/products', [ProductController::class, 'index'])->name('products.index');

// Halaman Detail Produk
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

use App\Http\Controllers\CartController;

// Alur Checkout & Pembayaran (Dilindungi Auth)
Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/snap-token', [CheckoutController::class, 'createSnapToken'])->name('checkout.token');
    Route::get('/checkout/finish', [CheckoutController::class, 'finish'])->name('checkout.finish');
    Route::get('/checkout/unfinish', [CheckoutController::class, 'unfinish'])->name('checkout.unfinish');
    Route::get('/checkout/error', [CheckoutController::class, 'error'])->name('checkout.error');

    // Alur Keranjang Belanja (Dilindungi Auth)
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
    Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');

    // Halaman Profil Pengguna
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Endpoint hitung item keranjang belanja (Publik / Tanpa Auth)
Route::get('/cart/count', [CartController::class, 'count'])->name('cart.count');

// Webhook Midtrans (Dikecualikan dari CSRF di bootstrap/app.php)
Route::post('/midtrans/webhook', [MidtransWebhookController::class, 'handle'])->name('midtrans.webhook');

// Halaman dashboard dinamis & placeholder dashboard admin
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('home');
    })->name('dashboard');

    Route::get('/admin/dashboard', function () {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
        return '<h1>Admin Dashboard (Placeholder)</h1><p>Selamat datang di Dashboard Admin el Craft.</p>';
    })->name('admin.dashboard');
});

// Route bawaan Breeze (Login/Register)
require __DIR__.'/auth.php';