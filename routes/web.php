<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShippingController;
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

// ─────────────────────────────────────────────────────────────────────────────
// HALAMAN YANG DAPAT DILIHAT TAMU (dengan modal login otomatis jika tamu)
// Middleware 'guest.modal': jika tamu, redirect balik + flash show_login_modal
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('guest.modal')->group(function () {
    // Keranjang Belanja
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');

    // Profil Pengguna
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // Buku Alamat
    Route::get('/profile/addresses', [AddressController::class, 'index'])->name('profile.addresses');

    // Riwayat & Detail Pesanan
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show'])->name('orders.show');

    // Checkout (memerlukan email terverifikasi)
    Route::middleware('verified')->group(function () {
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::get('/checkout/finish', [CheckoutController::class, 'finish'])->name('checkout.finish');
        Route::get('/checkout/unfinish', [CheckoutController::class, 'unfinish'])->name('checkout.unfinish');
        Route::get('/checkout/error', [CheckoutController::class, 'error'])->name('checkout.error');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// AKSI YANG MEMERLUKAN AUTH PENUH (POST/PATCH/DELETE)
// Tamu mendapat 302 → login (default Laravel behavior untuk non-GET)
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    // Alur Keranjang Belanja
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
    Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');

    // Checkout (aksi buat token & voucher — memerlukan verifikasi email)
    Route::middleware('verified')->group(function () {
        Route::post('/checkout/snap-token', [CheckoutController::class, 'createSnapToken'])->name('checkout.token');
        Route::post('/checkout/voucher/apply', [VoucherController::class, 'apply'])->name('voucher.apply');
    });

    // Profil
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Buku Alamat (aksi tulis)
    Route::prefix('profile/addresses')->name('profile.addresses.')->group(function () {
        Route::post('/', [AddressController::class, 'store'])->name('store');
        Route::put('/{address}', [AddressController::class, 'update'])->name('update');
        Route::delete('/{address}', [AddressController::class, 'destroy'])->name('destroy');
        Route::post('/{address}/default', [AddressController::class, 'setDefault'])->name('setDefault');
    });

    // Pesanan (aksi tulis)
    Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{orderNumber}/resume-payment', [OrderController::class, 'resumePayment'])->name('orders.resume-payment');

    // Ulasan Produk
    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    // Wishlist (aksi tulis)
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::delete('/wishlist/{wishlist}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    // Dashboard (redirect sesuai peran)
    Route::get('/dashboard', function () {
        if (auth()->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('profile.edit');
    })->name('dashboard');

    Route::get('/admin/dashboard', function () {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
        return '<h1>Admin Dashboard (Placeholder)</h1><p>Selamat datang di Dashboard Admin el Craft.</p>';
    })->name('admin.dashboard');
});

// Endpoint hitung item keranjang belanja (Publik / Tanpa Auth)
Route::get('/cart/count', [CartController::class, 'count'])->name('cart.count');

// Endpoint RajaOngkir (Publik — data lokasi & biaya kirim)
Route::prefix('shipping')->name('shipping.')->group(function () {
    Route::get('/provinces',           [ShippingController::class, 'getProvinces'])->name('provinces');
    Route::get('/cities/{provinceId}', [ShippingController::class, 'getCities'])->name('cities');
    Route::post('/cost',               [ShippingController::class, 'calculateCost'])->name('cost');
});

// Endpoint hitung item wishlist (Publik — mengembalikan 0 jika tamu)
Route::get('/wishlist/count', [WishlistController::class, 'count'])->name('wishlist.count');

// Webhook Midtrans (Dikecualikan dari CSRF di bootstrap/app.php)
Route::post('/midtrans/webhook', [MidtransWebhookController::class, 'handle'])->name('midtrans.webhook');

// Route bawaan Breeze (Login/Register)
require __DIR__.'/auth.php';