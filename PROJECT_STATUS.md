# PROJECT STATUS — el Craft

*Generated: 2026-09-05 | Repository: athifabril11/elcraft | Environment: Local / Windows (Laragon)*

---

## 1. Tech Stack Summary

| Component | Technology / Package | Version / Configuration | Source Reference |
|---|---|---|---|
| **Framework** | Laravel Framework | `^13.8` (Laravel 13) | `composer.json` |
| **PHP** | PHP | `^8.3` (Runtime: `PHP 8.3.30`) | `composer.json`, PHPUnit runtime |
| **Frontend UI / Templating** | Laravel Blade Templates | Native Blade Component Architecture | `resources/views/` |
| **Frontend Interactivity** | Alpine.js | `^3.4.2` | `package.json` |
| **CSS Framework** | Tailwind CSS + Forms Plugin | `tailwindcss ^3.1.0`, `@tailwindcss/vite ^4.0.0`, `@tailwindcss/forms ^0.5.2` | `package.json` |
| **Build Tool / Asset Bundler**| Vite + Laravel Vite Plugin | `vite ^8.0.0`, `laravel-vite-plugin ^3.1` | `package.json` |
| **Database** | MySQL InnoDB (App / Local), SQLite In-Memory (Testing) | `DB_CONNECTION=mysql`, MySQL 8.4.3; testing uses `:memory:` | `.env`, `phpunit.xml` |
| **Session & Cache Storage** | Database Driver (App) / Array (Testing) | `SESSION_DRIVER=database`, `CACHE_STORE=database` | `.env`, `config/session.php` |
| **Queue Connection** | Database Driver (App) / Sync (Testing) | `QUEUE_CONNECTION=database` | `.env`, `phpunit.xml` |
| **Payment Gateway** | Midtrans Snap API | `PaymentService` & `MidtransWebhookController` (HMAC SHA512 signature, `lockForUpdate` idempotency guard) | `config/midtrans.php`, `app/Services/PaymentService.php` |
| **Shipping API** | RajaOngkir via Komerce API | `KomerceShippingService` (6 couriers whitelist, server-side cost re-verification, origin setting) | `config/rajaongkir.php`, `app/Services/KomerceShippingService.php` |
| **Authentication System** | Laravel Breeze + Laravel Socialite | `laravel/breeze ^2.4`, `laravel/socialite ^5.30` (Google OAuth 2.0) | `composer.json`, `routes/auth.php` |

---

## 2. Feature Inventory & Current Status

### 2.1 Authentication & Account Management
- **Storefront Login & Registration Modal**: AJAX-driven tabbed modal (Login / Register) with Alpine.js; endpoints for `/login` and `/register` redirect with flash flags (`show_login_modal`, `auth_modal_tab`).
- **Standard Auth**: `AuthenticatedSessionController` (login/logout) and `RegisteredUserController` supporting both JSON response (for AJAX modal) and standard redirects.
- **Google OAuth 2.0 (Socialite)**: `GoogleAuthController` (`/auth/google/redirect`, `/auth/google/callback`) handles returning Google users, links existing email accounts to avoid duplicates, auto-marks emails as verified, and rejects deactivated accounts.
- **Email Verification**: Laravel `MustVerifyEmail` contract on `User` model, enforced on checkout via `verified` middleware.
- **Password Reset & Update**: Standard Breeze tokenized password reset links (`PasswordResetLinkController`, `NewPasswordController`) and authenticated password update (`PasswordController`).
- **Profile Management**: `ProfileController@edit` and `ProfileController@update` for personal info updates.
- **Account Deletion & Anonymization**: `ProfileController@destroy` performs a hard-delete if the user has 0 orders; if existing orders exist, it performs a soft anonymization (`is_active = false`, random placeholder email, cleared personal data) to preserve database foreign key integrity. Password confirmation required for standard users; `"HAPUS"` text confirmation required for Google OAuth users.
- **Address Book Management**: `AddressController` (`/profile/addresses`) supporting CRUD for shipping addresses with atomic default address switching and auto-promotion of the next address if the default is deleted.

### 2.2 Storefront & Catalog
- **Home Page**: `HomeController@index` renders active categories, featured "New Arrivals", popular products (aggregated by total units sold from paid orders with fallback to latest), and active discounted products.
- **Product Catalog & Filters**: `ProductController@index` provides search, category filtering, price range filtering, sorting, and paginated results (`9` per page) preserving query parameters.
- **Product Detail**: `ProductController@show` eager-loads active variants, sort-ordered gallery images, approved reviews with reviewer information, and up to 4 related products from the same category.
- **Two-Tier SKU System**:
  - `products.sku`: Optional unique string per product (`varchar(100)`, unique index `products_sku_unique`).
  - `product_variants.sku`: Optional unique string per variant (`varchar(100)`, unique index `product_variants_sku_unique`).
  - Manual input supported for both tiers; fully validated and tested (`ProductSkuTest`, `ProductVariantSkuTest`).
- **Stock Synchronization**: `ProductVariantObserver` automatically recalculates and caches `products.stock = sum(variants.stock)` whenever variants change.

### 2.3 Cart & Wishlist
- **Shopping Cart**: `CartController` (`/cart`, `/cart/add`, `/cart/update`, `/cart/remove`, `/cart/count`) manages persistent database cart records (`Cart` and `CartItem` models) tied to `user_id`, validating stock against product/variant inventory on add/update.
- **Wishlist**: `WishlistController` (`/wishlist`, `/wishlist/toggle`, `/wishlist/{id}`, `/wishlist/count`) provides idempotent toggle operations and returns JSON responses for instant frontend badge and button state synchronization.

### 2.4 Checkout & Payment
- **Checkout Flow**: `CheckoutController@index` renders cart summary, default user addresses, and calculates total item weights.
- **Server-Side Shipping Calculation**: `ShippingController` and `KomerceShippingService` integrate with RajaOngkir/Komerce API (`/shipping/provinces`, `/shipping/cities/{id}`, `/shipping/cost`) with response caching (7 days for geographic data, 1 hour for rates).
- **Server-Side Verification on Snap Token Creation**: `CheckoutController@createSnapToken` recalculates total cart weight and re-verifies shipping cost from the shipping API server-side (`resolveVerifiedShippingCost`, disregarding client-submitted costs to prevent tampering), validates stock availability, applies voucher discounts, cancels prior abandoned pending orders for the user, creates an `Order` and `Payment` record, and returns a Midtrans Snap token.
- **Voucher System**: `VoucherController@apply` validates active date range, minimum purchase threshold, and total quota; supports fixed amount (`fixed`) and percentage (`percent`) discounts with maximum discount caps.
- **Concurrency & Idempotency**: `MidtransWebhookController@handle` uses `lockForUpdate()` and transactions to ensure repeated webhook notifications do not double-decrement stock or double-record voucher usages.
- **Post-Payment Callbacks**: `/checkout/finish` (clears active cart items), `/checkout/unfinish`, and `/checkout/error`.

### 2.5 Order Status Lifecycle (End-to-End)
Alur status: `pending → paid → diproses → dikirim → selesai` (atau `dibatalkan` di titik manapun).
- **Order History & Detail**: `OrderController@index` and `OrderController@show` scoped to authenticated user with resume payment support.
- **Admin Status Advance**: `Admin\OrderController@advanceStatus` allows moving orders: `paid → diproses → dikirim` with strict status transition validation and row locking.
- **Customer Confirm Received**: `OrderController@confirmReceived` allows customers to confirm received orders (`dikirim → selesai`) with idempotent lock-then-recheck guard.
- **Auto-Complete Scheduler**: Console command `orders:auto-complete` (`AutoCompleteDeliveredOrders`) automatically completes orders lingering in `dikirim` status past a configurable threshold. Registered in `bootstrap/app.php` scheduler (`$schedule->command('orders:auto-complete')->daily()`).
- **Auto-Complete Admin Setting**: `Admin\OrderAutoCompleteController` provides an admin interface to configure `order_auto_complete_days` (default 7 days) stored in `shop_settings`.

### 2.6 Shipping Management
- **Courier Whitelist**: Database-backed `couriers` table seeded with 6 active couriers (`jne`, `jnt`, `pos`, `tiki`, `lion`, `sap`).
- **Courier Admin Toggle**: `Admin\CourierController` allows enabling/disabling couriers; inactive couriers are rejected by shipping calculation and checkout validation.
- **Shipping Origin Setting**: `Admin\ShippingOriginController` allows dynamic configuration of the store origin city ID stored in `shop_settings`.
- **Shipping Discount Rules**: `Admin\ShippingDiscountController` manages `shipping_discount_rules` supporting free shipping and fixed discount amounts scoped to cities or provinces.

### 2.7 Reviews & Ratings
- **Review Submission**: `ReviewController@store` allows customers to submit 1-5 star ratings, comments, and optional photos for purchased products.
- **Review Policy**: `ReviewPolicy@writeReview` ensures only users who purchased the product in a delivered order (`status = 'selesai'`) can review each order item.
- **Rating Recalculation**: `ReviewObserver` listens to `Review` model events to automatically recalculate `rating_avg` and `rating_count` on the `Product` model.

### 2.8 Returns & Refund Module (Foundation)
- **Database Schema**:
  - `returns` table: 13 columns including `return_number`, `order_id`, `user_id`, `resolution_type` (`tukar_barang` / `refund`), `reason`, `refund_amount`, `evidence_photo_path`, `status` (`diajukan`, `disetujui`, `ditolak`, `diproses`, `selesai`), `admin_notes`, `resolved_at`.
  - `return_items` table: item-level returns tracking (`return_id`, `order_item_id`, `quantity`).
- **Models & Relationships**: `ReturnRequest` and `ReturnItem` models with complete `$fillable` and relationships to `Order` and `OrderItem`.
- **Unit Testing**: `ReturnRequestTest` (13 tests) verifying unique constraints, resolution types, refund calculations, and item relationships.
- **Current Limitation**: Frontend submission form and admin moderation controller/routes are planned (RF-42), currently existing as static mockup `resources/views/admin-mockups/returns.html`.

### 2.9 Admin Panel
- **Real Backend Implementations**:
  - `GET /admin/dashboard`: `Admin\DashboardController@index` (minimal view).
  - `GET /admin/orders`: `Admin\OrderController@index` (order list and detail).
  - `PATCH /admin/orders/{order}/advance-status`: `Admin\OrderController@advanceStatus` (status advance).
  - `GET/PATCH /admin/settings/couriers`: `Admin\CourierController`.
  - `GET/POST /admin/settings/shipping`: `Admin\ShippingOriginController`.
  - `GET/POST/PUT/DELETE /admin/settings/shipping-discounts`: `Admin\ShippingDiscountController`.
  - `GET/POST /admin/settings/order-auto-complete`: `Admin\OrderAutoCompleteController`.
- **Atelier Rose Gold Static Mockups** (`resources/views/admin-mockups/`):
  - 10 unified pages with fixed sidebar and luxury aesthetic: `dashboard.html`, `orders.html`, `products.html`, `categories.html`, `vouchers.html`, `reviews.html`, `banners.html`, `returns.html`, `shipping.html`, `auto-complete.html`.

---

## 3. Test Coverage Snapshot

### Test Suite Execution Summary
Executed via `php artisan test --testdox`:
- **Total Tests**: `228`
- **Total Assertions**: `687`
- **Pass Rate**: **100% (228 passed, 0 failed)**
- **Execution Time**: `~7.8s - 15.0s`

### Breakdown by Test File / Group

| Test Group / File | Namespace / Location | Test Count |
|---|---|:---:|
| **Account Deletion & Anonymization** | `Tests\Feature\Auth\AccountDeletionAnonymization` | 5 |
| **Authentication** | `Tests\Feature\Auth\Authentication` | 7 |
| **Auto-Complete Delivered Orders Command** | `Tests\Console\Commands\AutoCompleteDeliveredOrders` | 13 |
| **Checkout Workflow & Security** | `Tests\Feature\Checkout` | 19 |
| **Courier Admin Management** | `Tests\Feature\Admin\CourierAdmin` | 9 |
| **Courier Persistence** | `Tests\Feature\CourierPersistence` | 9 |
| **Email Verification** | `Tests\Feature\Auth\EmailVerification` | 3 |
| **Example (Feature)** | `Tests\Feature\Example` | 1 |
| **Example (Unit)** | `Tests\Unit\Example` | 1 |
| **Google Login (OAuth 2.0)** | `Tests\Feature\Auth\GoogleLogin` | 4 |
| **Order Auto-Complete Admin** | `Tests\Feature\Admin\OrderAutoCompleteAdmin` | 13 |
| **Order Status Admin** | `Tests\Feature\Admin\OrderStatusAdmin` | 12 |
| **Password Confirmation** | `Tests\Feature\Auth\PasswordConfirmation` | 3 |
| **Password Reset** | `Tests\Feature\Auth\PasswordReset` | 5 |
| **Password Update** | `Tests\Feature\Auth\PasswordUpdate` | 2 |
| **Payment Method Selection** | `Tests\Feature\PaymentMethodSelection` | 3 |
| **Product SKU (Two-Tier Unit)** | `Tests\Unit\ProductSku` | 5 |
| **Product Variant SKU (Two-Tier Unit)** | `Tests\Unit\ProductVariantSku` | 5 |
| **Profile Management** | `Tests\Feature\Profile` | 8 |
| **Registration** | `Tests\Feature\Auth\Registration` | 5 |
| **Resume Payment** | `Tests\Feature\ResumePayment` | 5 |
| **Return Request Foundation** | `Tests\Unit\ReturnRequest` | 13 |
| **Product Reviews** | `Tests\Feature\Review` | 5 |
| **Shipping API Integration & Discounts** | `Tests\Feature\Shipping` | 19 |
| **Shipping Discount Admin** | `Tests\Feature\Admin\ShippingDiscountAdmin` | 9 |
| **Shipping Origin Admin** | `Tests\Feature\Admin\ShippingOriginAdmin` | 10 |
| **Shipping Response Normalisation** | `Tests\Unit\ShippingResponseNormalisation` | 11 |
| **Webhook Idempotency** | `Tests\Feature\WebhookIdempotency` | 3 |
| **Wishlist** | `Tests\Feature\Wishlist` | 7 |
| **TOTAL** | | **228** |

---

## 4. Feature Status Matrix

| Feature Area | Status | Notes / Code Reference |
|---|---|---|
| **Auth & Account Management** | ✅ Done & tested | AJAX modal, Google OAuth, mandatory verification, account anonymization |
| **Product Catalog + Two-Tier SKU** | ✅ Done & tested | Manual SKU input on product & variant level, unique constraints, stock observer |
| **Cart & Wishlist** | ✅ Done & tested | Cart tied to stock and checkout; Wishlist independent with dedicated tests |
| **Checkout & Payment** | ✅ Done & tested | Midtrans Snap, voucher validation, server-side shipping cost verification |
| **Order Status Lifecycle** | ✅ Done & tested | Admin advance (`paid`→`diproses`→`dikirim`), customer confirm (`dikirim`→`selesai`), auto-complete scheduler |
| **Shipping** | ✅ Done & tested | 6 couriers whitelist, origin setting, city/province discount rules |
| **Reviews** | ✅ Done & tested | Gated on `selesai` status; rating auto-calculation via Observer |
| **Vouchers** | ✅ Done & tested | Percentage/fixed discounts, spend thresholds, quota enforcement |
| **Returns / Refund Module** | 🚧 Partial | Schema, models, and 13 unit tests done; customer form & admin controller planned |
| **Admin Panel** | 🚧 Partial | Settings & Order advance implemented in real Blade; 10 mockups ready for CRUD conversion |
| **Homepage Hero Banner CMS** | 📋 Planned | Front carousel currently static Blade template |
| **Kelola Metode Pembayaran** | 📋 Planned | Payment methods hardcoded in checkout validation |
| **Kelola Akun Customer** | 📋 Planned | Admin customer listing/toggle not started |
| **Kelola Akun Admin/Staff** | 📋 Planned | Multi-level role permissions not started |
| **Laporan / Export Keuangan** | 📋 Planned | Financial aggregation and export not started |

---

## 5. Upcoming Roadmap & Next Steps

1. **Returns Module UI & Controller (RF-42)**:
   - Implement customer return request submission form (with image upload and order item selection).
   - Implement admin return request moderation controller, approval/rejection, resolution selection (`tukar_barang` vs `refund`), and refund amount assignment.
2. **Admin CRUD Conversion to Blade (RF-44 & RF-46)**:
   - Convert `products.html` and `categories.html` mockups into real Blade templates and controllers, wiring the two-tier SKU inputs and variant stock controls.
   - Standardize all admin pages using the Atelier Rose Gold fixed sidebar layout.
3. **Admin Settings Theme Harmonization**:
   - Port the existing functional settings pages (`shipping`, `auto-complete`, `couriers`) to use the new Atelier Rose Gold design templates.
