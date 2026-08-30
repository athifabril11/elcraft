# PROJECT STATUS — el Craft

*Generated: 2026-08-24 | Repository: athifabril11/elcraft | Environment: Local / Windows*

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
| **Database** | SQLite (Default / Local / Testing), MySQL (Configured) | `DB_CONNECTION=sqlite`, database/database.sqlite; testing uses `:memory:` | `.env.example`, `phpunit.xml` |
| **Session & Cache Storage** | Database Driver (App) / Array (Testing) | `SESSION_DRIVER=database`, `CACHE_STORE=database` | `.env.example`, `config/session.php` |
| **Queue Connection** | Database Driver (App) / Sync (Testing) | `QUEUE_CONNECTION=database` | `.env.example`, `phpunit.xml` |
| **Payment Gateway** | Midtrans Snap API | `MidtransService` (Sandbox & Production key support, HMAC SHA512 signature verification) | `config/midtrans.php`, `app/Services/MidtransService.php` |
| **Shipping API** | RajaOngkir via Komerce API | `ShippingService` (`RAJAONGKIR_BASE_URL`, origin city ID: `152` / Kudus) | `config/rajaongkir.php`, `app/Services/ShippingService.php` |
| **Authentication System** | Laravel Breeze + Laravel Socialite | `laravel/breeze ^2.4`, `laravel/socialite ^5.30` (Google OAuth 2.0) | `composer.json`, `routes/auth.php` |

---

## 2. Feature Inventory

### Authentication & Account
- **Storefront Login & Registration Modal**: AJAX-driven tabbed modal (Login / Register) with Alpine.js state management; fallback endpoints for `/login` and `/register` redirect to home with flash flags (`show_login_modal`, `auth_modal_tab`).
- **Standard Auth**: `AuthenticatedSessionController` (login/logout) and `RegisteredUserController` supporting both JSON response (for AJAX modal) and standard redirects.
- **Google OAuth 2.0 (Socialite)**: `GoogleController` (`/auth/google/redirect`, `/auth/google/callback`) handles returning Google users, links existing email accounts to avoid duplicates, auto-marks emails as verified, and rejects deactivated accounts.
- **Email Verification**: Laravel `MustVerifyEmail` contract on `User` model, enforced on sensitive storefront routes (`/checkout`, `/cart`, `/wishlist`, `/orders`, `/profile`) via `verified` middleware.
- **Password Reset & Update**: Standard Breeze tokenized password reset links (`PasswordResetLinkController`, `NewPasswordController`) and authenticated password update (`PasswordController`).
- **Profile Management**: `ProfileController@edit` and `ProfileController@update` for name, email, and phone updates (email changes trigger re-verification).
- **Account Deletion & Anonymization**: `ProfileController@destroy` performs a hard-delete if the user has 0 orders; if existing orders exist, it performs a soft anonymization (`is_active = false`, random placeholder email, cleared personal data) to preserve database foreign key integrity. Password confirmation required for standard users; `"HAPUS"` text confirmation required for Google OAuth users.
- **Address Book Management**: `AddressController` (`/profile/addresses`) supporting CRUD for shipping addresses with atomic default address switching and auto-promotion of the next address if the default is deleted.

### Storefront & Catalog
- **Home Page**: `HomeController@index` renders active categories, featured "New Arrivals", popular products (aggregated by total units sold from paid orders with fallback to latest), and active discounted products.
- **Product Catalog & Filters**: `ProductController@index` provides search by title/description, category filtering by slug, min/max price range filtering, sorting (price low-to-high, high-to-low, popular/featured, latest), and paginated results (`9` per page) preserving query parameters.
- **Product Detail**: `ProductController@show` eager-loads active variants, sort-ordered gallery images, approved reviews with reviewer information, and up to 4 related products from the same category.

### Cart & Wishlist
- **Shopping Cart**: `CartController` (`/cart`, `/cart/add`, `/cart/update`, `/cart/remove`, `/cart/count`) manages persistent database cart records (`Cart` and `CartItem` models) tied to `user_id`, validating stock against product/variant inventory on add/update.
- **Wishlist**: `WishlistController` (`/wishlist`, `/wishlist/toggle`, `/wishlist/{id}`, `/wishlist/count`) provides idempotent toggle operations and returns JSON responses for instant frontend badge and button state synchronization.

### Checkout & Payment
- **Checkout Flow**: `CheckoutController@index` renders cart summary, default user addresses, and calculates total item weights.
- **Server-Side Shipping Calculation**: `ShippingController` and `ShippingService` integrate with RajaOngkir/Komerce API (`/shipping/provinces`, `/shipping/cities/{id}`, `/shipping/cost`) with response caching (7 days for geographic data, 1 hour for rates).
- **Server-Side Verification on Snap Token Creation**: `CheckoutController@createSnapToken` recalculates total cart weight and re-verifies shipping cost from the shipping API server-side (disregarding client-submitted costs to prevent tampering), validates stock availability, applies voucher discounts, cancels prior abandoned pending orders for the user, creates an `Order` and `Payment` record, and returns a Midtrans Snap token.
- **Voucher System**: `VoucherController@apply` validates active date range, minimum purchase threshold, and total quota; supports fixed amount (`fixed`) and percentage (`percent`) discounts with maximum discount caps.
- **Midtrans Webhook Handler**: `MidtransWebhookController@handle` verifies HMAC SHA512 signature, handles `capture`/`settlement` (marks order as `paid`, creates shipment record, decrements stock, records voucher usage), handles `pending`, and handles `deny`/`expire`/`cancel` (marks as `dibatalkan`, restores stock if previously deducted).
- **Post-Payment Callbacks**: `/checkout/finish` (clears active cart items), `/checkout/unfinish`, and `/checkout/error`.

### Order Management
- **Order History**: `OrderController@index` displays paginated orders (`10` per page) scoped to the authenticated user.
- **Order Details**: `OrderController@show` displays order items, shipping details, applied voucher, and payment status (scoped to `Auth::user()` to prevent IDOR).
- **Order Cancellation**: `OrderController@cancel` allows customers to cancel orders that are still in `pending` status.
- **Resume Payment**: `OrderController@resumePayment` returns the existing Midtrans Snap token if within the 24-hour TTL window, allowing users to finish pending payments from the order detail page without re-checking out.

### Reviews & Ratings
- **Review Submission**: `ReviewController@store` allows customers to submit 1-5 star ratings, comments, and optional photos for purchased products.
- **Review Policy**: `ReviewPolicy@writeReview` ensures only users who purchased the product in a delivered order (`status = 'selesai'`) can review each order item, preventing duplicate reviews for the same purchase item.
- **Rating Recalculation**: `ReviewObserver` listens to `Review` model events (`created`, `updated`, `deleted`) to automatically recalculate `rating_avg` and `rating_count` on the `Product` model and invalidate fragment cache keys (`product:{id}:rating`).

### Admin Panel
- **Current Status**: **Scaffolded / Placeholder Only**.
- In `routes/web.php`, the route `/admin/dashboard` is a basic closure checking `auth()->user()->role === 'admin'` and returning a static placeholder string (`<h1>Admin Dashboard (Placeholder)</h1>`).
- There are no dedicated Admin controllers, Blade templates, or moderation screens for products, orders, vouchers, or review approvals in the codebase yet.

### Guest-Access Handling
- **Click-Intercept & Modal-Flash Pattern**:
  - Custom middleware `RedirectGuestsWithModal` (`app/Http/Middleware/RedirectGuestsWithModal.php`, aliased as `guest.modal`) intercepts unauthenticated GET requests to protected routes (`/cart`, `/wishlist`, `/profile`, `/profile/addresses`, `/orders`, `/checkout`).
  - Instead of redirecting to a standalone `/login` page, it flashes `show_login_modal = true` into the session and redirects the guest back to their previous page (or `/`).
  - Storefront layout (`resources/views/layouts/storefront.blade.php`) evaluates `$errors->any()` or `session('show_login_modal')` via Alpine.js to immediately display the auth modal over the current page.

---

## 3. Test Coverage Snapshot

### Test Suite Execution Summary
Executed via `php vendor/bin/phpunit --testdox`:
- **Total Tests**: `111`
- **Total Assertions**: `406`
- **Status**: **100% PASSED (OK)**

### Breakdown by Test File / Group

| Test Group / File | Namespace / Location | Test Count |
|---|---|:---:|
| **Account Deletion & Anonymization** | `Tests\Feature\AccountDeletionAnonymization` | 5 |
| **Address & Checkout UI** | `Tests\Feature\AddressAndCheckoutUI` | 5 |
| **Authentication** | `Tests\Feature\Auth\Authentication` | 7 |
| **Checkout Workflow & Security** | `Tests\Feature\Checkout` | 12 |
| **Checkout Email Verification** | `Tests\Feature\Auth\CheckoutEmailVerification` | 9 |
| **Courier Persistence** | `Tests\Feature\CourierPersistence` | 2 |
| **Email Verification** | `Tests\Feature\Auth\EmailVerification` | 3 |
| **Example (Feature)** | `Tests\Feature\Example` | 1 |
| **Example (Unit)** | `Tests\Unit\Example` | 1 |
| **Google Login (OAuth)** | `Tests\Feature\Auth\GoogleLogin` | 4 |
| **Password Confirmation** | `Tests\Feature\Auth\PasswordConfirmation` | 3 |
| **Password Reset** | `Tests\Feature\Auth\PasswordReset` | 5 |
| **Password Update** | `Tests\Feature\Auth\PasswordUpdate` | 2 |
| **Payment Method Selection** | `Tests\Feature\PaymentMethodSelection` | 3 |
| **Profile Management** | `Tests\Feature\Profile` | 8 |
| **Registration** | `Tests\Feature\Auth\Registration` | 5 |
| **Resume Payment** | `Tests\Feature\ResumePayment` | 5 |
| **Product Reviews** | `Tests\Feature\Review` | 5 |
| **Shipping API Integration** | `Tests\Feature\Shipping` | 10 |
| **Shipping Response Normalisation** | `Tests\Unit\ShippingResponseNormalisation` | 9 |
| **Wishlist** | `Tests\Feature\Wishlist` | 7 |
| **TOTAL** | | **111** |

---

## 4. Known Pending Items & TODOs

### 1. Code Comments (TODO / FIXME)
- **`app/Http/Controllers/MidtransWebhookController.php` (Line 58)**:
  ```php
  // TODO: Ganti dengan model Order yang sebenarnya setelah dibuat
  ```
  *Note: The actual `Order` model lookup and transaction logic is already implemented below this comment; the comment itself is residual documentation debt.*

### 2. Scaffolded / Unwired Features
- **Admin Panel Backend & UI**:
  - `routes/web.php` route `/admin/dashboard` returns inline static HTML.
  - No admin controllers exist for:
    - Product management (CRUD, image upload, variant stock management).
    - Order status updates (e.g. moving orders from `paid` → `diproses` → `dikirim` with tracking numbers → `selesai`).
    - Review approval moderation (setting `is_approved = true` to trigger `ReviewObserver` and display reviews publicly).
    - Voucher management (creating coupon codes, discount rules, quotas).
- **Courier Tracking**:
  - The `Shipment` model exists and tracks `tracking_number`, `status`, and `shipped_at`, but there is no integration with a courier tracking API or admin tracking update UI.

### 3. Hardcoded / Candidate Configurable Values
- **Courier Whitelist**:
  - Hardcoded to `['jne', 'pos', 'tiki', 'jnt']` in `app/Services/ShippingService.php:128`, `app/Http/Controllers/ShippingController.php:91`, and `app/Http/Controllers/CheckoutController.php:104`.
- **Payment Method Whitelist**:
  - Hardcoded list in `CheckoutController@createSnapToken` validation (`credit_card,bca_va,bni_va,bri_va,other_va,gopay,shopeepay,qris,indomaret,alfamart`).
- **Shipping Fallback Address Defaults**:
  - In `CheckoutController.php` lines 176–179, creating an address during guest-like checkout defaults province to `'Jawa Barat'` and district to `'Kecamatan'` if not explicitly collected.
- **Minimum Weight Clamp**:
  - Hardcoded to minimum `100` grams (`max(100, $totalWeight)`) in `CheckoutController.php:73, 137`.
- **Snap Token Expiry**:
  - Hardcoded to `now()->addHours(24)` in `CheckoutController.php:310` and checked in `Payment::isExpired()`.
- **Default Origin City**:
  - Fallback origin city ID is `152` (Kudus) in `ShippingService.php:34` and `256` (Jember) in `config/rajaongkir.php:6`.

---

## 5. Environment & Configuration Notes

### 1. Dev Server & Process Management
- Development workflow can be run via `composer dev` (which invokes `npx concurrently` to run `php artisan serve`, `php artisan queue:listen`, `php artisan pail`, and `npm run dev`) or standard `php artisan serve --port=8000` alongside Vite.

### 2. Session & CSRF Configuration
- **Database Sessions**: The application defaults to `SESSION_DRIVER=database` in `.env.example`. Ensure the `sessions` table is migrated when setting up new environments.
- **Session Serialization**: Set to `'serialization' => 'json'` in `config/session.php`.
- **CSRF Token Exemptions**: Configured in `bootstrap/app.php` for:
  - `midtrans/webhook` (allows incoming payment callbacks from Midtrans servers).
  - `shipping/cost` (allows AJAX shipping calculations).

### 3. Proxy & JSON Handling
- **Trusted Proxies**: `bootstrap/app.php` configures `$middleware->trustProxies(at: '*')` to support reverse proxies, tunnels (e.g., ngrok), and load balancers.
- **API Exception Rendering**: Configured with `$exceptions->shouldRenderJsonWhen(fn ($request) => $request->expectsJson())` to ensure AJAX/API calls receive JSON error payloads instead of HTML exception pages.

### 4. Shipping API Schema (RajaOngkir via Komerce)
- The integration uses the modern Komerce API structure (`https://rajaongkir.komerce.id/api/v1` or starter endpoint):
  - Requires form-data (not JSON) for `/calculate/domestic-cost`.
  - Service normalization in `ShippingController` automatically handles stripping English day suffixes (`"2-3 days"`) to prevent UI rendering glitches like `"2-3 days hari"`.
