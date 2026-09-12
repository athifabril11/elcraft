<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Admin\ShippingOriginController;
use App\Http\Controllers\Admin\ShippingDiscountController;
use App\Http\Controllers\Admin\CourierController;
use App\Http\Controllers\Admin\OrderAutoCompleteController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\GlobalSearchController as AdminGlobalSearchController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
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
    Route::patch('/orders/{orderNumber}/confirm-received', [OrderController::class, 'confirmReceived'])->name('orders.confirm-received');
    Route::get('/orders/{orderNumber}/resume-payment', [OrderController::class, 'resumePayment'])->name('orders.resume-payment');

    // Return submission routes
    Route::get('/orders/{orderNumber}/returns/create', [ReturnController::class, 'create'])->name('orders.returns.create');
    Route::post('/orders/{orderNumber}/returns', [ReturnController::class, 'store'])->name('orders.returns.store');
    Route::get('/orders/{orderNumber}/returns', [ReturnController::class, 'show'])->name('orders.returns.show');

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
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/global-search', [AdminGlobalSearchController::class, 'search'])->name('global-search');

        // Orders (list + detail JSON + status advance)
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}/detail', [AdminOrderController::class, 'detail'])->name('orders.detail');
        Route::patch('/orders/{order}/advance-status', [AdminOrderController::class, 'advanceStatus'])->name('orders.advance-status');

        // Categories Management
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::patch('/categories/{category}/toggle', [AdminCategoryController::class, 'toggle'])->name('categories.toggle');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        // Products Management
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/toggle', [AdminProductController::class, 'toggle'])->name('products.toggle');
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');

        // Returns
        Route::get('/returns', [AdminReturnController::class, 'index'])->name('returns.index');
        Route::patch('/returns/{return}/approve', [AdminReturnController::class, 'approve'])->name('returns.approve');
        Route::patch('/returns/{return}/reject', [AdminReturnController::class, 'reject'])->name('returns.reject');
        Route::patch('/returns/{return}/advance', [AdminReturnController::class, 'advanceStatus'])->name('returns.advance');

        // Reviews Moderation
        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::patch('/reviews/{review}/approve', [AdminReviewController::class, 'approve'])->name('reviews.approve');
        Route::patch('/reviews/{review}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
        Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

        // Reports & Analytics
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [AdminReportController::class, 'export'])->name('reports.export');

        // Customer Management
        Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
        Route::patch('/customers/{customer}/toggle-status', [AdminCustomerController::class, 'toggleStatus'])->name('customers.toggle-status');

        // Vouchers Management
        Route::get('/vouchers', [AdminVoucherController::class, 'index'])->name('vouchers.index');
        Route::post('/vouchers', [AdminVoucherController::class, 'store'])->name('vouchers.store');
        Route::put('/vouchers/{voucher}', [AdminVoucherController::class, 'update'])->name('vouchers.update');
        Route::delete('/vouchers/{voucher}', [AdminVoucherController::class, 'destroy'])->name('vouchers.destroy');
        Route::patch('/vouchers/{voucher}/toggle', [AdminVoucherController::class, 'toggle'])->name('vouchers.toggle');

        // Banners CMS
        Route::get('/banners', [AdminBannerController::class, 'index'])->name('banners.index');
        Route::post('/banners', [AdminBannerController::class, 'store'])->name('banners.store');
        Route::put('/banners/{banner}', [AdminBannerController::class, 'update'])->name('banners.update');
        Route::delete('/banners/{banner}', [AdminBannerController::class, 'destroy'])->name('banners.destroy');
        Route::patch('/banners/{banner}/toggle', [AdminBannerController::class, 'toggle'])->name('banners.toggle');

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

            // Auto-Selesai Pesanan
            Route::get('/order-auto-complete',  [OrderAutoCompleteController::class, 'index'])->name('order-auto-complete.index');
            Route::post('/order-auto-complete', [OrderAutoCompleteController::class, 'update'])->name('order-auto-complete.update');
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
