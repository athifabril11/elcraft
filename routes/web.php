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
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Admin\ShippingOriginController;
use App\Http\Controllers\Admin\ShippingDiscountController;
use App\Http\Controllers\Admin\CourierController;
use Illuminate\Support\Facades\Route;

// Arahkan halaman utama (/) ke HomeController
Route::get('/', [HomeController::class, 'index'])->name('home');

// Halaman Daftar Produk
Route::get('/products', [ProductController::class, 'index'])->name('products.index');

// Halaman Detail Produk
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

Route::middleware(['guest.modal', 'verified'])->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/addresses', [AddressController::class, 'index'])->name('profile.addresses');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::get('/checkout/finish', [CheckoutController::class, 'finish'])->name('checkout.finish');
    Route::get('/checkout/unfinish', [CheckoutController::class, 'unfinish'])->name('checkout.unfinish');
    Route::get('/checkout/error', [CheckoutController::class, 'error'])->name('checkout.error');
});

Route::middleware('auth')->group(function () {
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
    Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');

    Route::middleware('verified')->group(function () {
        Route::post('/checkout/snap-token', [CheckoutController::class, 'createSnapToken'])->name('checkout.token');
        Route::post('/checkout/voucher/apply', [VoucherController::class, 'apply'])->name('voucher.apply');
    });

    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('profile/addresses')->name('profile.addresses.')->group(function () {
        Route::post('/', [AddressController::class, 'store'])->name('store');
        Route::put('/{address}', [AddressController::class, 'update'])->name('update');
        Route::delete('/{address}', [AddressController::class, 'destroy'])->name('destroy');
        Route::post('/{address}/default', [AddressController::class, 'setDefault'])->name('setDefault');
    });

    Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{orderNumber}/resume-payment', [OrderController::class, 'resumePayment'])->name('orders.resume-payment');

    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::delete('/wishlist/{wishlist}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/dashboard', function () {
        if (auth()->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('profile.edit');
    })->name('dashboard');

    // Admin Routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/shipping',  [ShippingOriginController::class, 'index'])->name('shipping.index');
            Route::post('/shipping', [ShippingOriginController::class, 'update'])->name('shipping.update');

            // Aturan Diskon Ongkir
            Route::get('/shipping-discounts', [ShippingDiscountController::class, 'index'])->name('shipping-discounts.index');
            Route::post('/shipping-discounts', [ShippingDiscountController::class, 'store'])->name('shipping-discounts.store');
            Route::put('/shipping-discounts/{shippingDiscount}', [ShippingDiscountController::class, 'update'])->name('shipping-discounts.update');
            Route::delete('/shipping-discounts/{shippingDiscount}', [ShippingDiscountController::class, 'destroy'])->name('shipping-discounts.destroy');
            Route::post('/shipping-discounts/{shippingDiscount}/toggle', [ShippingDiscountController::class, 'toggle'])->name('shipping-discounts.toggle');

            // Pengaturan Kurir
            Route::get('/couriers', [CourierController::class, 'index'])->name('couriers.index');
            Route::patch('/couriers/{courier}', [CourierController::class, 'toggle'])->name('couriers.toggle');
        });
    });
});

Route::get('/cart/count', [CartController::class, 'count'])->name('cart.count');

Route::prefix('shipping')->name('shipping.')->group(function () {
    Route::get('/provinces',           [ShippingController::class, 'getProvinces'])->name('provinces');
    Route::get('/cities/{provinceId}', [ShippingController::class, 'getCities'])->name('cities');
    Route::post('/cost',               [ShippingController::class, 'calculateCost'])->name('cost');
});

Route::get('/wishlist/count', [WishlistController::class, 'count'])->name('wishlist.count');

Route::post('/midtrans/webhook', [MidtransWebhookController::class, 'handle'])->name('midtrans.webhook');

require __DIR__.'/auth.php';
