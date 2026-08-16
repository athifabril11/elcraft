# Product Requirements Document (PRD)
# el Craft — Sistem E-Commerce Aksesoris Wanita

**Versi:** 1.1  
**Tanggal:** 12 Agustus 2026  
**Status:** Diverifikasi — seluruh klaim status dikonfirmasi terhadap source code aktual  
**Stack:** Laravel 13 · PHP 8.3 · Tailwind CSS · Alpine.js · MySQL · Midtrans Snap · RajaOngkir Komerce API

---

## 1. Gambaran Umum (Overview)

### 1.1 Nama Produk
**el Craft** — Platform e-commerce aksesoris wanita premium.

### 1.2 Konteks Bisnis

el Craft adalah platform e-commerce *business-to-consumer* (B2C) yang dibangun untuk klien UMKM nyata yang bergerak di bidang penjualan aksesoris wanita premium (gelang, kalung, cincin, anting, bros, aksesoris rambut) di Indonesia. Sebelum platform ini, penjualan dilakukan sepenuhnya melalui WhatsApp, Instagram DM, dan marketplace pihak ketiga — tanpa sistem manajemen pesanan terpusat, tanpa data pelanggan yang dapat dikelola sendiri, dan tanpa kontrol margin karena biaya komisi marketplace.

### 1.3 Tujuan Sistem

| Tujuan | Indikator Keberhasilan |
|--------|------------------------|
| Migrasi penjualan dari marketplace/WA ke platform sendiri | lebih dari 30% pesanan masuk melalui platform dalam 3 bulan pertama |
| Manajemen produk mandiri tanpa developer | Admin dapat menambah/edit produk, harga, diskon, dan stok secara mandiri |
| Pembayaran otomatis tanpa transfer manual | Semua transaksi melalui Midtrans Snap (QRIS, VA, Kartu Kredit) |
| Manajemen pesanan terpusat | Admin dapat melihat, memproses, dan memperbarui status semua pesanan dari satu dashboard |
| Reputasi produk terukur | Sistem ulasan dengan moderasi membangun kepercayaan pembeli baru |

### 1.4 Teknologi

| Komponen | Teknologi |
|----------|-----------|
| Backend Framework | Laravel 13 (PHP 8.3) |
| Frontend | Blade + Tailwind CSS + Alpine.js + Vanilla JS |
| Database | MySQL (via Laragon lokal; produksi: VPS) |
| Autentikasi | Laravel Breeze (session-based) |
| Pembayaran | Midtrans Snap (Sandbox — production keys belum diisi) |
| Ongkos Kirim | RajaOngkir via Komerce API (`rajaongkir.komerce.id/api/v1`) |
| Email | Belum diimplementasikan (tidak ada app/Mail/ atau app/Notifications/) |
| Queue | Tabel `jobs` ada; worker belum dijalankan |
| Cache | Laravel Cache — digunakan untuk data provinsi/kota (7 hari) dan ongkir (1 jam) |

> **Koreksi dari draft sebelumnya:** Draft PRD v1.0 menyebutkan "RajaOngkir Starter" — ini salah. File `.env` dan `ShippingService.php` mengonfirmasi API yang digunakan adalah **Komerce API** (`https://rajaongkir.komerce.id/api/v1`), bukan API legacy `api.rajaongkir.com/starter`. Ini penting karena Komerce API mendukung lebih banyak kurir daripada Starter Plan legacy, meskipun codebase saat ini masih membatasi secara hardcode ke 4 kurir.

---

## 2. Aktor

| Aktor | Deskripsi | Autentikasi |
|-------|-----------|-------------|
| **Tamu (Guest)** | Pengunjung yang belum login. Dapat melihat produk, kategori, homepage. Tidak dapat checkout atau wishlist. | Tidak diperlukan |
| **Pelanggan (Customer)** | Pengguna terdaftar dengan role = customer. Dapat melakukan seluruh alur belanja. | Email + Password (Breeze) |
| **Admin** | Pengguna dengan role = admin. Saat ini hanya bisa login dan melihat placeholder `/admin/dashboard`. | Email + Password (Breeze) — redirect ke /admin/dashboard |

> **Catatan Kode:** Kolom `users.role` adalah enum `['customer', 'admin']` dengan default `'customer'`. Kolom `users.is_active` (boolean, default `true`) tersedia di skema database namun **tidak di-enforce** — pengguna dengan `is_active = false` saat ini masih dapat login.

---

## 3. Matriks Status Fitur

### 3.1 Fitur Pelanggan (Customer-Facing)

| No | Fitur | Status | Bukti Kode |
|----|-------|--------|------------|
| C1 | Registrasi akun | ✅ Selesai | `Auth/RegisteredUserController.php` + `auth/register.blade.php` |
| C2 | Login / Logout | ✅ Selesai | `Auth/AuthenticatedSessionController.php` + `auth/login.blade.php` |
| C3 | Lupa password (reset via email) | ✅ Selesai | `Auth/PasswordResetLinkController.php` + `Auth/NewPasswordController.php` |
| C4 | Edit profil (nama, email, password) | ✅ Selesai | `ProfileController.php` + `profile/edit.blade.php` + 3 partials |
| C5 | Manajemen buku alamat | ✅ Selesai | `AddressController.php` (CRUD + setDefault) + `profile/addresses.blade.php` + `AddressPolicy` |
| C6 | Browse produk + filter (kategori, harga, nama, sort) | ✅ Selesai | `ProductController::index()` — 4 filter + 4 sort options + paginate(9) |
| C7 | Halaman detail produk + varian + produk terkait | ✅ Selesai | `ProductController::show()` — reviews (10 terbaru), related (4 random same category) |
| C8 | Keranjang belanja (tambah, update qty, hapus) | ✅ Selesai | `CartController` — stok validasi, variant-aware, 1 cart per user |
| C9 | Checkout dengan pemilihan kurir dan layanan | ✅ Selesai | `CheckoutController` — Alpine.js frontend, validasi stok server-side |
| C10 | Kalkulasi ongkos kirim real-time (RajaOngkir Komerce) | ✅ Selesai | `ShippingService` → `ShippingController` — province → city → cost |
| C11 | Penerapan voucher diskon saat checkout | ✅ Selesai | `VoucherController::apply()` — error message spesifik per case |
| C12 | Pembayaran via Midtrans Snap | ✅ Selesai | `MidtransService::createSnapToken()` — 10 metode pembayaran diaktifkan |
| C13 | Halaman konfirmasi setelah bayar | ✅ Selesai | `CheckoutController::finish()` — bersihkan cart items |
| C14 | Riwayat pesanan + detail pesanan | ✅ Selesai | `OrderController::index()` + `show()` — IDOR-protected |
| C15 | Pembatalan pesanan mandiri | ✅ Selesai | `OrderController::cancel()` — hanya status `pending` |
| C16 | Wishlist (toggle, daftar, hapus, count) | ✅ Selesai | `WishlistController` — AJAX toggle + `/wishlist` page + badge count |
| C17 | Ulasan produk (rating + komentar + foto) | ✅ Selesai | `ReviewController::store()` — terikat `order_item_id`, Gate-protected |
| C18 | Homepage: New Arrivals | ✅ Selesai | `HomeController` — `is_featured = true` products |
| C19 | Homepage: Produk Terpopuler | ✅ Selesai | `HomeController` — ranking by total sold; fallback to latest |
| C20 | Homepage: Sedang Diskon | ✅ Selesai | `HomeController` — filter discount aktif by tanggal |
| C21 | Login Google OAuth | ❌ Belum Dimulai | Kolom `google_id` ada di DB; Socialite TIDAK terinstall; tidak ada logic OAuth |
| C22 | Verifikasi email | ⚠️ Parsial | Controller-controller ada (Breeze default); `MustVerifyEmail` di-**comment** pada `User.php` line 5 — fitur **tidak aktif** |
| C23 | Notifikasi email (pesanan, pengiriman) | ❌ Belum Dimulai | **Tidak ada** direktori `app/Mail/` atau `app/Notifications/`. Nol class Mailable atau Notification. |

### 3.2 Fitur Admin (Back-Office)

| No | Fitur | Status | Bukti Kode |
|----|-------|--------|------------|
| A1 | Dashboard Admin | ❌ Belum Dimulai | Route `/admin/dashboard` ada tapi hanya return string HTML statis (1 baris `<h1>`) |
| A2 | Manajemen Produk (CRUD + varian + diskon) | ❌ Belum Dimulai | Tidak ada controller, view, atau route admin untuk produk |
| A3 | Manajemen Kategori (CRUD) | ❌ Belum Dimulai | Tidak ada controller, view, atau route admin untuk kategori |
| A4 | Manajemen Banner | ❌ Belum Dimulai | Tidak ada tabel `banners` di database |
| A5 | Manajemen Pesanan (list, detail, update status) | ❌ Belum Dimulai | Tidak ada controller admin — pesanan hanya bisa dilihat sisi pelanggan |
| A6 | Lihat Status Pembayaran | ❌ Belum Dimulai | Data tersimpan di tabel `payments`, tanpa UI admin |
| A7 | Manajemen Voucher | ❌ Belum Dimulai | Tabel + model + apply logic ada; **tidak ada** UI admin untuk CRUD voucher |
| A8 | Moderasi Ulasan | ❌ Belum Dimulai | `is_approved = false` default; tidak ada UI admin untuk approve/reject |
| A9 | Manajemen Pengguna | ❌ Belum Dimulai | Tidak ada UI admin; `is_active` tidak di-enforce |
| A10 | Manajemen Retur/Refund | ❌ Belum Dimulai | **Tidak ada** `ReturnController` — dikonfirmasi tidak pernah ada di codebase |

> **Catatan:** Tidak ada direktori `resources/views/admin/` dan tidak ada `public/admin/`. Admin panel belum ada sama sekali.

---

## 4. Kebutuhan Fungsional — Pelanggan

### 4.1 Modul Autentikasi

- **REQ-C-AUTH-01:** Sistem harus mengizinkan pengguna baru mendaftar menggunakan nama, email, dan password. Password di-hash menggunakan Bcrypt.
- **REQ-C-AUTH-02:** Sistem harus mengizinkan pengguna login menggunakan email dan password terdaftar.
- **REQ-C-AUTH-03:** Sistem harus menyediakan fitur reset password melalui link email yang dikirim ke alamat terdaftar.
- **REQ-C-AUTH-04:** Sistem harus mengizinkan pengguna logout dari sesi aktif.
- **REQ-C-AUTH-05:** Semua halaman yang memerlukan data pengguna (keranjang, checkout, pesanan, profil, wishlist) harus dilindungi middleware `auth` dan mengarahkan tamu ke halaman login.

### 4.2 Modul Profil dan Alamat

- **REQ-C-PROFILE-01:** Pengguna dapat mengubah nama, email, dan password. *(Catatan: upload avatar belum diimplementasikan — form hanya handle `name` dan `email` via `ProfileUpdateRequest`.)*
- **REQ-C-PROFILE-02:** Pengguna dapat menambah, mengubah, menghapus, dan menentukan alamat pengiriman utama (default).
- **REQ-C-PROFILE-03:** Setiap alamat menyimpan: nama penerima, telepon, alamat lengkap, provinsi, province_id, kota, city_id, kecamatan, kode pos, label, is_default.

### 4.3 Modul Produk dan Pencarian

- **REQ-C-PRODUCT-01:** Sistem menampilkan semua produk aktif (is_active = true) dengan paginasi 9 produk per halaman.
- **REQ-C-PRODUCT-02:** Pelanggan dapat memfilter produk berdasarkan: kategori (slug), nama (pencarian teks), rentang harga (min/max).
- **REQ-C-PRODUCT-03:** Pelanggan dapat mengurutkan produk berdasarkan: terbaru (created_at DESC), harga rendah ke tinggi, harga tinggi ke rendah, atau popularitas (is_featured DESC).
- **REQ-C-PRODUCT-04:** Halaman detail produk menampilkan: semua gambar (diurutkan sort_order), varian aktif, ulasan yang sudah disetujui (max 10 terbaru), dan produk terkait (kategori sama, max 4, acak).
- **REQ-C-PRODUCT-05:** Jika produk memiliki diskon aktif, sistem menampilkan harga coret dan harga final.
- **REQ-C-PRODUCT-06:** Rating rata-rata dan jumlah ulasan ditampilkan pada halaman detail produk (kolom `rating_avg` dan `rating_count`).

### 4.4 Modul Keranjang Belanja

- **REQ-C-CART-01:** Setiap pengguna terdaftar memiliki tepat satu keranjang (carts.user_id unik). Keranjang dibuat otomatis saat pertama kali dibutuhkan.
- **REQ-C-CART-02:** Pelanggan dapat menambahkan produk (dengan atau tanpa varian) ke keranjang. Kombinasi (cart_id, product_id, variant_id) bersifat unik — penambahan produk yang sama akan menambah kuantitas.
- **REQ-C-CART-03:** Pelanggan dapat mengubah kuantitas item atau menghapus item dari keranjang.
- **REQ-C-CART-04:** Jumlah item di keranjang (badge) ditampilkan di navbar — diperbarui via AJAX (`/cart/count`).
- **REQ-C-CART-05:** Harga satuan yang digunakan adalah `final_price` (sudah memperhitungkan diskon produk yang aktif).

### 4.5 Modul Checkout dan Pembayaran

- **REQ-C-CHECKOUT-01:** Checkout hanya tersedia untuk pengguna yang sudah login dan memiliki keranjang tidak kosong.
- **REQ-C-CHECKOUT-02:** Pelanggan mengisi: nama penerima, nomor telepon, alamat lengkap, provinsi, kota/kabupaten (dari dropdown data RajaOngkir Komerce), kode pos, memilih kurir (JNE/J&T/POS/TIKI) dan layanan, serta catatan (opsional).
- **REQ-C-CHECKOUT-03:** Sistem menghitung ongkos kirim real-time dari API RajaOngkir Komerce berdasarkan city_id tujuan, berat total order (dalam gram), dan pilihan kurir. Hasil di-cache selama 1 jam.
- **REQ-C-CHECKOUT-04:** Sebelum membuat Snap Token, sistem memvalidasi ulang ketersediaan stok dari database untuk mencegah overselling.
- **REQ-C-CHECKOUT-05:** Pelanggan dapat memasukkan kode voucher. Validasi server-side: status aktif, kuota, tanggal berlaku, minimum pembelian.
- **REQ-C-CHECKOUT-06:** Order dibuat dalam DB dengan status `pending` sebelum Snap Token di-request ke Midtrans.
- **REQ-C-CHECKOUT-07:** Pembayaran dilakukan melalui popup Midtrans Snap. Metode: Kartu Kredit (3DS), VA BCA/BNI/BRI/Lainnya, GoPay, ShopeePay, QRIS, Indomaret, Alfamart.
- **REQ-C-CHECKOUT-08:** Webhook Midtrans (POST /midtrans/webhook) memverifikasi signature sebelum memproses. Setelah berhasil: status order → paid, stok dikurangi, shipment record dibuat, voucher usage dicatat.
- **REQ-C-CHECKOUT-09:** Jika ditolak/kedaluwarsa: status order → dibatalkan dan stok dipulihkan.

### 4.6 Modul Pesanan

- **REQ-C-ORDER-01:** Pelanggan dapat melihat riwayat semua pesanan miliknya, diurutkan terbaru, dengan paginasi 10 per halaman.
- **REQ-C-ORDER-02:** Pelanggan dapat melihat detail satu pesanan: item, varian, alamat pengiriman, data pembayaran, data pengiriman, dan voucher.
- **REQ-C-ORDER-03:** Pelanggan hanya dapat membatalkan pesanan berstatus `pending`. Pembatalan mengubah status order ke `dibatalkan` dan payment ke `failed`.
- **REQ-C-ORDER-04:** Sistem mencegah IDOR — query di-scope pada `auth()->user()->orders()`.

### 4.7 Modul Wishlist

- **REQ-C-WISH-01:** Pelanggan dapat toggle wishlist dari halaman produk atau listing via AJAX.
- **REQ-C-WISH-02:** Halaman /wishlist menampilkan seluruh produk dalam wishlist.
- **REQ-C-WISH-03:** Badge wishlist count di bottom nav diperbarui real-time via `/wishlist/count`.
- **REQ-C-WISH-04:** Tamu yang mencoba akses wishlist diarahkan ke login (prompt modal).

### 4.8 Modul Ulasan Produk

- **REQ-C-REVIEW-01:** Pelanggan hanya dapat mengulas produk yang terikat pada `order_item_id` miliknya — order harus berstatus `selesai`. Satu ulasan per item pesanan.
- **REQ-C-REVIEW-02:** Ulasan: rating 1-5, komentar (max 1000 char), foto opsional (max 2MB, jpg/jpeg/png/webp).
- **REQ-C-REVIEW-03:** Ulasan baru → `is_approved = false` — hanya tampil setelah admin approve.
- **REQ-C-REVIEW-04:** Otorisasi via `Gate::allows('writeReview')` yang didefinisikan di `ReviewPolicy`.
- **REQ-C-REVIEW-05:** `ReviewObserver` terdaftar di `AppServiceProvider` — secara otomatis menghitung ulang `rating_avg` dan `rating_count` pada tabel `products` setiap kali review dibuat, diupdate, atau dihapus.

---

## 5. Kebutuhan Fungsional — Admin

> Seluruh fitur admin berada di bawah prefix route /admin/ dan dilindungi middleware auth + pengecekan role === 'admin'. **Seluruh fitur di bawah ini belum diimplementasikan** — tidak ada controller admin, tidak ada view admin, tidak ada route admin kecuali placeholder dashboard.

### 5.1 Dashboard (A1)

- **REQ-A-DASH-01:** Dashboard menampilkan ringkasan: total pendapatan, jumlah pesanan per status, jumlah produk aktif, jumlah pengguna terdaftar.
- **REQ-A-DASH-02:** Dashboard menampilkan grafik penjualan.
- **REQ-A-DASH-03:** Dashboard menampilkan tabel pesanan terbaru.
- **REQ-A-DASH-04:** Dashboard menampilkan produk terlaris.

### 5.2 Manajemen Produk (A2)

- **REQ-A-PROD-01 s/d REQ-A-PROD-07:** CRUD produk, varian, gambar, diskon, stok. Belum ada implementasi.

### 5.3 Manajemen Kategori (A3)

- **REQ-A-CAT-01 s/d REQ-A-CAT-03:** CRUD kategori. Belum ada implementasi.

### 5.4 Manajemen Banner (A4)

- **REQ-A-BANNER-01 / REQ-A-BANNER-02:** Tabel `banners` belum ada. Perlu migrasi baru.

### 5.5 Manajemen Pesanan (A5)

- **REQ-A-ORDER-01 s/d REQ-A-ORDER-05:** Alur pesanan admin. Belum ada implementasi.

### 5.6 Monitoring Pembayaran (A6)

- **REQ-A-PAY-01 s/d REQ-A-PAY-03:** Tampilan pembayaran admin. Belum ada implementasi.

### 5.7 Manajemen Voucher (A7)

- **REQ-A-VCHR-01 s/d REQ-A-VCHR-04:** CRUD voucher admin. Belum ada implementasi. (Catatan: tabel dan logic `apply()` sudah ada; yang belum ada hanya UI admin.)

### 5.8 Moderasi Ulasan (A8)

- **REQ-A-REVIEW-01 s/d REQ-A-REVIEW-04:** Approve/reject ulasan. Belum ada implementasi.

### 5.9 Manajemen Pengguna (A9)

- **REQ-A-USER-01 s/d REQ-A-USER-04:** Daftar pengguna, nonaktifkan, ubah role. Belum ada implementasi.

### 5.10 Manajemen Retur/Refund (A10)

- **REQ-A-RETURN-01 s/d REQ-A-RETURN-03:** Tidak ada `ReturnController` di codebase. Fitur baru sepenuhnya.

---

## 6. Kebutuhan Non-Fungsional

### 6.1 Keamanan (Security)

| Aspek | Implementasi Saat Ini |
|-------|----------------------|
| Validasi stok server-side | `CheckoutController::createSnapToken()` — cek stok dari DB sebelum token |
| Pencegahan IDOR pesanan | Query di-scope pada `auth()->user()->orders()` |
| Signature webhook | `MidtransService::verifySignature()` — SHA-512 |
| Otorisasi ulasan | `Gate::allows('writeReview')` via `ReviewPolicy` |
| Perlindungan CSRF | Semua form; webhook + shipping/cost dikecualikan di `bootstrap/app.php` |
| Hashing password | Bcrypt via Laravel Hashing |
| Admin role check | Route `/admin/dashboard` memeriksa `role === 'admin'`, abort 403 |
| Kolom sensitif hidden | `password`, `remember_token` di-set `#[Hidden]` pada `User.php` |
| Alamat policy | `AddressPolicy` mencegah user mengubah/hapus alamat milik user lain |

### 6.2 Keandalan (Reliability)

- **REQ-NFR-REL-01:** `DB::transaction()` digunakan pada: pembuatan order, webhook pembayaran, pembatalan + pemulihan stok, dan operasi alamat.
- **REQ-NFR-REL-02:** Webhook Midtrans menggunakan cek idempotency (`if order && order->status !== 'paid'`).
- **REQ-NFR-REL-03:** Semua error API eksternal di-catch dan di-log via `Log::error()`.

### 6.3 Performa (Performance)

- **REQ-NFR-PERF-01:** Data provinsi/kota RajaOngkir di-cache 7 hari. Ongkir di-cache 1 jam.
- **REQ-NFR-PERF-02:** Semua query pada halaman listing menggunakan eager loading (`with(...)`) — N+1 prevention.
- **REQ-NFR-PERF-03:** Aset frontend di-compile via Vite.

---

## 7. Ringkasan Model Data

### 7.1 Daftar Entitas

| Tabel | Model | Status | Tujuan |
|-------|-------|--------|--------|
| users | User | ✅ | Akun pengguna (customer dan admin) |
| categories | Category | ✅ | Kategori produk |
| products | Product | ✅ | Katalog produk dengan harga, diskon, stok, berat |
| product_images | ProductImage | ✅ | Galeri gambar produk; is_primary + sort_order |
| product_variants | ProductVariant | ✅ | Varian produk (warna, ukuran) dengan harga tambahan + stok sendiri |
| carts | Cart | ✅ | Satu keranjang per user |
| cart_items | CartItem | ✅ | Item dalam keranjang — unique (cart_id, product_id, variant_id) |
| addresses | Address | ✅ | Buku alamat + city_id/province_id RajaOngkir |
| vouchers | Voucher | ✅ | Kode voucher diskon |
| voucher_usages | VoucherUsage | ✅ | Catatan penggunaan voucher per user per order |
| orders | Order | ✅ | Header pesanan |
| order_items | OrderItem | ✅ | Baris item pesanan; snapshot nama + harga saat beli |
| payments | Payment | ✅ | Data transaksi Midtrans per order |
| shipments | Shipment | ✅ | Data pengiriman per order |
| reviews | Review | ✅ | Ulasan produk; moderasi via is_approved |
| wishlists | Wishlist | ✅ | Daftar produk yang di-save pengguna |

### 7.2 Relasi Kunci

```
User        --has one-->   Cart        --has many-->  CartItem   --belongs to-->  Product
User        --has many-->  Order       --has many-->  OrderItem  --belongs to-->  Product
User        --has many-->  Address
User        --has many-->  Wishlist    --belongs to-->  Product
User        --has many-->  Review
Order       --belongs to-->  Address
Order       --belongs to-->  Voucher
Order       --has one-->   Payment
Order       --has one-->   Shipment
OrderItem   --has one-->   Review
Product     --belongs to-->  Category
Product     --has many-->  ProductImage
Product     --has many-->  ProductVariant
Product     --has many-->  Review
Voucher     --has many-->  VoucherUsage
```

### 7.3 Arsitektur Varian/Stok

**Status aktual (diverifikasi):**

- **ProductVariant model** (`app/Models/ProductVariant.php`): ✅ Ada — fillable fields: product_id, variant_name, variant_type, additional_price, stock, sku, is_active. Memiliki accessor `getFinalPriceAttribute()` = product.final_price + additional_price.
- **Auto-created "Standar" variant logic**: ❌ **Tidak ada** — tidak ada ProductObserver, tidak ada trigger, tidak ada logic di mana pun yang otomatis membuat variant "Standar" saat produk dibuat tanpa varian. Ini murni design intention yang belum diimplementasikan.
- **Stock-sync Observer**: ❌ **Tidak ada** — satu-satunya observer yang terdaftar adalah `ReviewObserver`. Tidak ada observer untuk Product atau ProductVariant.
- **Actual stock decrement**: ✅ Dilakukan di `MidtransWebhookController::markAsPaid()` — decrement per item berdasarkan variant_id (jika ada) atau product_id.

### 7.4 Field Penting

| Tabel.Kolom | Tipe | Keterangan |
|-------------|------|------------|
| users.role | enum(customer, admin) | Hak akses pengguna |
| users.is_active | boolean | Ada, **tidak di-enforce** di login |
| users.google_id | string nullable | Reserved untuk OAuth — **tidak digunakan** |
| products.weight | integer default 0 | Dalam gram, digunakan untuk ongkir |
| products.discount_type | enum(none, percent, nominal) | Tipe diskon produk |
| products.rating_avg / rating_count | decimal/int | Di-update otomatis oleh ReviewObserver |
| orders.status | string | Alur: pending → paid → diproses → dikirim → selesai / dibatalkan |
| payments.status | string | pending / success / failed / expired |
| reviews.is_approved | boolean default false | Flag moderasi ulasan |

---

## 8. Di Luar Cakupan Saat Ini (Out of Scope)

### 8.1 Login Google OAuth
**Status:** Ditangguhkan. Kolom `google_id` ada; Socialite tidak terinstall.

### 8.2 Otomatisasi Delivery Order (Komship API)
**Status:** Ditangguhkan. RajaOngkir via Komerce hanya digunakan untuk kalkulasi biaya.

### 8.3 Upload Avatar Profil
**Status:** Field `avatar` ada di DB; `ProfileController::update()` tidak meng-handle file upload.

---

## 9. Technical Debt yang Diketahui

### 9.1 Kurir Pilihan Pelanggan Tidak Tersimpan ke Shipment

**Lokasi:** `MidtransWebhookController.php` line 104-111  
**Masalah:** Saat webhook menerima pembayaran sukses, shipment dibuat dengan `courier = 'Manual'` dan `service = 'Reguler'`, bukan kurir yang dipilih pelanggan di checkout.  
**Dampak:** Admin tidak punya data kurir pilihan pelanggan secara terstruktur.

### 9.2 Komentar TODO Sisa di MidtransWebhookController

**Lokasi:** Line 58-59  
```php
// TODO: Ganti dengan model Order yang sebenarnya setelah dibuat
// $order = Order::where('order_number', $orderId)->firstOrFail();
```
**Catatan:** Komentar sisa — Model Order sudah aktif digunakan. Bisa dihapus.

### 9.3 Email Notifikasi Belum Ada

**Fakta:** Tidak ada `app/Mail/`, `app/Notifications/`, atau pemanggilan `Mail::send()` di seluruh codebase.  
**Dampak:** Pelanggan tidak menerima email konfirmasi pembayaran atau pengiriman.

### 9.4 `users.is_active` Tidak Di-Enforce

**Fakta:** Kolom ada, tapi tidak ada middleware atau guard yang memeriksanya saat login.

### 9.5 Whitelist Kurir Hardcoded Tidak Sinkron Antar File

**Lokasi:**
- `ShippingService.php` line 128: `in_array($courier, ['jne', 'pos', 'tiki', 'jnt'])` — **4 kurir**
- `ShippingController.php` line 90: `'courier' => 'required|string|in:jne,pos,tiki,jnt'` — **4 kurir**
- `CheckoutController.php` line 94: `'courier' => 'nullable|string|in:jne,pos,tiki'` — **3 kurir** (jnt TIDAK termasuk!)
- Checkout frontend line 195: `@foreach(['jne' => 'JNE', 'jnt' => 'J&T Express', 'pos' => 'POS Indonesia', 'tiki' => 'TIKI']` — **4 kurir**

**Masalah:** `CheckoutController::createSnapToken()` hanya menerima `jne,pos,tiki` — sehingga order dengan courier J&T (`jnt`) yang dipilih pelanggan akan **gagal validasi** saat pembayaran. Frontend menampilkan J&T, tapi backend menolaknya.

### 9.6 `MustVerifyEmail` Di-Comment

**Lokasi:** `User.php` line 5: `// use Illuminate\Contracts\Auth\MustVerifyEmail;`  
**Dampak:** Verifikasi email tidak aktif. Controller Breeze (verify-email views) ada tapi tidak terpanggil.

### 9.7 Berat Produk Fallback Logic Tidak Sempurna

**Lokasi:** `CheckoutController.php` line 63  
```php
$weight = $item->product->weight ?? 100;
```
**Masalah:** Null-coalescing (`??`) tidak menangkap `weight = 0` (integer, bukan null). Produk dengan weight 0 akan dihitung 0 gram, bukan 100g fallback. Seharusnya: `max(100, (int) $item->product->weight)`.

### 9.8 Harga Ongkir Belum Diperiksa Server-Side

**Lokasi:** `CheckoutController::createSnapToken()` line 149  
```php
$shipping = max(0, (int) $request->input('shipping_cost', 0));
```
**Masalah:** Ongkir yang dikirim dari frontend diterima apa adanya tanpa validasi ulang terhadap API. Client bisa mengirim shipping_cost=0 untuk mendapat gratis ongkir.

---

## 10. Rencana Implementasi Berikutnya

| Prioritas | Modul | Alasan |
|-----------|-------|--------|
| P0 (Kritis) | Fix whitelist kurir di CheckoutController (9.5) | J&T checkout broken sekarang |
| P0 (Kritis) | Validasi ongkir server-side (9.8) | Security vulnerability |
| P0 (Kritis) | Enforce is_active login (9.4) | Keamanan dasar |
| P1 (Tinggi) | Admin: Manajemen Produk (A2) | Toko tidak bisa beroperasi tanpa ini |
| P1 (Tinggi) | Admin: Manajemen Pesanan (A5) | Toko tidak bisa beroperasi tanpa ini |
| P1 (Tinggi) | Admin: Dashboard (A1) | Visibilitas bisnis |
| P2 (Sedang) | Notifikasi Email (C23) | Kepercayaan pelanggan |
| P2 (Sedang) | Admin: Moderasi Ulasan (A8) | Reputasi produk |
| P2 (Sedang) | Admin: Manajemen Voucher (A7) | Kampanye marketing |
| P3 (Rendah) | Admin: Manajemen Kategori (A3) | Mendukung A2 |
| P3 (Rendah) | Admin: Manajemen Pengguna (A9) | Kontrol akses |
| P3 (Rendah) | Admin: Banner (A4) | Perlu migrasi baru |
| P4 (Masa Depan) | Admin: Retur/Refund (A10) | After-sales |
| P4 (Masa Depan) | Google OAuth (C21) | Nice-to-have |

---

*Dokumen ini diverifikasi terhadap source code aktual repository el Craft pada 12 Agustus 2026. Setiap status fitur dikonfirmasi dengan membaca file-file terkait secara langsung.*
