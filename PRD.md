# Product Requirements Document — el Craft

**Versi:** 1.0  
**Disusun:** 5 September 2026  
**Metodologi:** Dokumen ini disusun dari riwayat kerja yang terverifikasi lewat bukti mentah (raw test output, migration diff, git commit hash) sepanjang sesi kolaborasi Abril–Claude. Setiap klaim status "✅ Selesai" dikonfirmasi lewat output `php artisan test` yang di-paste langsung, bukan asumsi atau ringkasan. Item yang belum terkonfirmasi eksekusinya ditandai jujur sebagai belum selesai, meski prompt-nya sudah pernah dibuat.

> ⚠️ **Catatan metodologi penting:** Dokumen ini disusun oleh Claude berdasarkan riwayat percakapan, BUKAN hasil scan langsung ke codebase (Claude tidak punya akses filesystem ke komputer Abril). Untuk kepastian mutlak sebelum dipakai sebagai acuan akademik/client-facing, disarankan menjalankan satu pass verifikasi tambahan via Antigravity (`php artisan route:list`, `php artisan test --testdox`, `php artisan migrate:status`) dan membandingkan dengan isi dokumen ini.

---

## 1. Ringkasan Eksekutif

**Nama Produk:** el Craft  
**Konteks Bisnis:** Platform e-commerce produksi nyata untuk UMKM aksesoris wanita Indonesia (gelang, kalung, anting, bros, aksesoris rambut)  
**Jenis Platform:** Web e-commerce B2C, sekaligus proyek capstone D4 Teknik Informatika  
**Tech Stack:** Laravel 13 + Breeze (Blade + Alpine.js + Tailwind CSS), MySQL InnoDB, Midtrans Snap (pembayaran), Komerce/RajaOngkir (ongkir), PHPUnit  
**Prinsip Kualitas:** Standar produksi diutamakan di atas kenyamanan akademik — setiap fitur harus punya bukti test otomatis sebelum dianggap selesai.

---

## 2. Tujuan & Sasaran

**Tujuan Bisnis:**
- Memungkinkan UMKM klien berjualan online dengan sistem checkout, pembayaran, dan pengiriman yang terintegrasi penuh
- Menyediakan panel admin yang efisien untuk operasional harian toko

**Tujuan Teknis:**
- Keandalan tingkat produksi, bukan sekadar demo akademik
- Integritas data transaksi terjamin di bawah kondisi konkuren (race condition aman)
- Cakupan test otomatis untuk setiap fitur kritis

---

## 3. Aktor / Persona Pengguna

| Aktor | Deskripsi |
|---|---|
| **Guest** | Pengunjung belum login, bisa browsing produk, diarahkan ke modal login saat coba akses fitur terproteksi |
| **Customer** | Pengguna terautentikasi, bisa belanja, checkout, kelola pesanan, ajukan retur, tulis ulasan |
| **Admin/Staff** | Pengelola toko — saat ini single-role, rencana ke depan mendukung multi-level akses (lihat Bagian 8) |

---

## 4. Kebutuhan Fungsional (RF)

### 4.1 Autentikasi & Manajemen Akun
| ID | Requirement | Status |
|---|---|---|
| RF-01 | Modal login/register tab tunggal (AJAX, tanpa reload) | ✅ Selesai & teruji — `AuthenticationTest.php` |
| RF-02 | Login via Google OAuth (Socialite) | ✅ Selesai & teruji — `GoogleLoginTest.php` (4 test) |
| RF-03 | Verifikasi email wajib setelah registrasi | ✅ Selesai & teruji — `EmailVerificationTest.php`, `CheckoutEmailVerificationTest.php` |
| RF-04 | Reset password via email | ✅ Selesai & teruji — `PasswordResetTest.php` |
| RF-05 | Hapus akun: anonymize jika punya riwayat pesanan, hard delete jika tidak | ✅ Selesai & teruji — `AccountDeletionAnonymizationTest.php` (5 test) |
| RF-06 | Enforcement `is_active` di semua jalur login (termasuk Google) | ✅ Selesai & teruji |
| RF-07 | Guest-gating: modal login otomatis muncul saat guest akses halaman terproteksi | ✅ Selesai & teruji |

### 4.2 Katalog Produk
| ID | Requirement | Status |
|---|---|---|
| RF-08 | Listing produk dengan filter/sort | ✅ Selesai |
| RF-09 | Setiap produk minimal punya 1 varian (default "Standar" untuk produk simple) | ✅ Selesai — arsitektur inti sistem |
| RF-10 | `product_variants.stock` sebagai satu-satunya sumber kebenaran stok; `products.stock` cache otomatis via Observer | ✅ Selesai |
| RF-11 | SKU dua level: `products.sku` (opsional, unik) + `product_variants.sku` (opsional, unik) — keduanya input manual | ✅ Selesai & teruji — `ProductSkuTest.php` (5 test), `ProductVariantSkuTest.php` (5 test), total 215/215 test lolos di titik ini |
| RF-12 | Manajemen kategori | 🚧 Sebagian — model & data ada, admin CRUD masih mockup |

### 4.3 Keranjang & Wishlist
| ID | Requirement | Status |
|---|---|---|
| RF-13 | Keranjang: validasi stok, bagian dari alur checkout | ✅ Selesai & teruji — `CartTest.php` (9 test) |
| RF-14 | Wishlist: terpisah dari keranjang (keputusan desain sadar — beda niat pengguna), tidak divalidasi stok, tidak masuk checkout | ✅ Selesai & teruji — `WishlistTest.php` (7 test) |

### 4.4 Checkout & Pembayaran
| ID | Requirement | Status |
|---|---|---|
| RF-15 | Integrasi Midtrans Snap | ✅ Selesai & teruji |
| RF-16 | Voucher: validasi kuota, tanggal berlaku, minimum belanja, cap diskon | ✅ Selesai & teruji |
| RF-17 | Validasi ongkos kirim server-side — nilai client diabaikan total, dihitung ulang dari API | ✅ Selesai & teruji — `ShippingTest.php` (19 test) |
| RF-18 | Pesanan pending lama otomatis dibatalkan saat checkout ulang | ✅ Selesai & teruji |
| RF-19 | Kelola metode pembayaran (toggle aktif/nonaktif per metode) | 📋 Direncanakan — terkait item ditunda "dynamic payment config", lihat Bagian 7 |

### 4.5 Siklus Status Pesanan (End-to-End)
Alur: `pending → paid → diproses → dikirim → selesai` (atau `dibatalkan` di titik manapun)

| ID | Requirement | Status |
|---|---|---|
| RF-20 | Race condition fix pada webhook Midtrans (`lockForUpdate` + idempotency check) | ✅ Selesai & teruji — `WebhookIdempotencyTest.php` (3 test), memverifikasi webhook duplikat tidak dobel-proses stok/voucher |
| RF-21 | Perbaikan bug lama: `VoucherUsage.discount_applied` NOT NULL yang tidak pernah diisi (berpotensi crash order berpromo) | ✅ Ditemukan & difix dalam sesi yang sama dengan RF-20 |
| RF-22 | Admin bisa majukan status pesanan (paid → diproses → dikirim), versi minimal | ✅ Selesai & teruji — `OrderStatusAdminTest.php` (12 test), total 178/178 test lolos di titik ini |
| RF-23 | Customer bisa konfirmasi "Pesanan Diterima" (dikirim → selesai) | ✅ Selesai & teruji — `CustomerOrderConfirmReceivedTest.php` (14 test termasuk regression guard anti-duplikasi tombol), total 205/205 test lolos |
| RF-24 | Auto-selesai otomatis jika customer tidak respon dalam X hari (dapat diatur admin) | ✅ Selesai & teruji — `AutoCompleteDeliveredOrdersTest.php` (13 test), scheduled command `orders:auto-complete` terdaftar di `bootstrap/app.php` |
| RF-25 | Setting jumlah hari auto-selesai (default 7, admin-configurable) | ✅ Selesai & teruji — `OrderAutoCompleteAdminTest.php` (13 test) |

> **Catatan infrastruktur:** RF-24 memerlukan cron job (`* * * * * php artisan schedule:run`) yang **belum dikonfigurasi** di server produksi — ini murni langkah deployment, tidak ada perubahan kode yang diperlukan.

### 4.6 Pengiriman
| ID | Requirement | Status |
|---|---|---|
| RF-26 | Integrasi Komerce/RajaOngkir untuk kalkulasi ongkir | ✅ Selesai & teruji |
| RF-27 | Whitelist 6 kurir: JNE, J&T, POS, TIKI, Lion Parcel, SAP Express | ✅ Selesai & teruji — `CourierAdminTest.php` (9 test) |
| RF-28 | Admin atur asal pengiriman (kota origin) | ✅ Selesai & teruji — `ShippingOriginAdminTest.php` (10 test) |
| RF-29 | Admin atur aturan diskon ongkir per kota/provinsi | ✅ Selesai & teruji — `ShippingDiscountAdminTest.php` (9 test) |
| RF-30 | Admin toggle aktif/nonaktif per kurir | ✅ Selesai & teruji |

### 4.7 Ulasan (Review)
| ID | Requirement | Status |
|---|---|---|
| RF-31 | Customer bisa ulas produk hanya dari order berstatus `selesai` | ✅ Selesai & teruji — `ReviewTest.php` (3 test) |
| RF-32 | Rating bintang + komentar + foto opsional | ✅ Selesai |
| RF-33 | Moderasi admin (approve/reject) | 🚧 Sebagian — logic dasar ada, halaman admin masih mockup |

### 4.8 Voucher
| ID | Requirement | Status |
|---|---|---|
| RF-34 | Kode diskon (persen/nominal), min. belanja, cap maksimum, kuota, tanggal berlaku, toggle aktif | ✅ Selesai & teruji |

### 4.9 Modul Retur/Refund
| ID | Requirement | Status |
|---|---|---|
| RF-35 | Retur per item (bukan per keseluruhan order) | ✅ Skema dasar selesai & teruji — `ReturnRequestTest.php` (7 test), total 222/222 test lolos |
| RF-36 | Maksimum 1 pengajuan retur per order (unique constraint di `order_id`) | ✅ Selesai & teruji |
| RF-37 | Model `ReturnRequest` + `ReturnItem`, relasi ke `Order`/`OrderItem` | ✅ Selesai & teruji |
| RF-38 | Field tambahan: `return_number`, `resolution_type` (tukar barang/refund), `refund_amount` | ✅ Selesai & teruji — `ReturnRequestTest.php` (13 test), total 228/228 test lolos |
| RF-39 | Status flow: `diajukan → disetujui/ditolak → diproses → selesai` | ✅ Skema selesai (kolom `status` enum sudah ada) |
| RF-40 | Notifikasi admin: badge panel + WhatsApp (`wa.me` gratis) | 📋 Direncanakan, belum diimplementasi |
| RF-41 | Foto bukti tersimpan di sistem (tidak auto-attach ke WA) | ✅ Skema selesai (`evidence_photo_path`) |
| RF-42 | Form pengajuan retur (customer) & kelola retur (admin) — controller/route beneran | 📋 Direncanakan — baru mockup visual |

> ⚠️ **Belum dikonfirmasi ter-commit ke git** — perlu dicek `git status` sebelum melanjutkan pekerjaan di area ini.

### 4.10 Panel Admin — Inti
| ID | Requirement | Status |
|---|---|---|
| RF-43 | Middleware proteksi berbasis role (`EnsureUserIsAdmin`) | ✅ Selesai & teruji |
| RF-44 | Dashboard admin dengan KPI pendapatan/pesanan/stok dan alert actionable | ✅ Desain final disepakati (mockup direvisi berkali-kali), belum diimplementasi jadi Blade beneran |
| RF-45 | Halaman kelola pesanan (list + ubah status, versi minimal) | ✅ Selesai & teruji — lihat RF-22 |
| RF-46 | CRUD lengkap: Produk, Kategori, Voucher, Ulasan, Banner | 🚧 Sebagian — **Voucher & Banner ✅ Selesai & teruji** (`VoucherAdminTest.php`, `BannerAdminTest.php`); Produk, Kategori, Ulasan dalam antrean |

### 4.11 Homepage Hero Banner CMS
| ID | Requirement | Status |
|---|---|---|
| RF-47 | 5 elemen carousel (foto, eyebrow berwarna, judul, deskripsi, tombol CTA) jadi admin-editable | ✅ Selesai & teruji — `BannerAdminTest.php` (8 test), terhubung dinamis ke `home.blade.php` |

### 4.12 Kelola Metode Pembayaran
| ID | Requirement | Status |
|---|---|---|
| RF-48 | Toggle aktif/nonaktif per metode pembayaran, pola arsitektur sama seperti toggle kurir | 📋 Direncanakan — terkait item ditunda sebelumnya, kini diprioritaskan ulang |

### 4.13 Kelola Akun Customer
| ID | Requirement | Status |
|---|---|---|
| RF-49 | List semua customer, detail (profil, riwayat order, total belanja), aktifkan/nonaktifkan akun | ✅ Selesai & teruji — `CustomerAdminTest.php` (7 test) |

### 4.14 Kelola Akun Admin/Staff
| ID | Requirement | Status |
|---|---|---|
| RF-50 | Dukungan multi-admin dengan level akses berbeda (Superadmin vs Staff terbatas) | 📋 Direncanakan — **BUTUH SESI SPESIFIKASI TERPISAH** sebelum implementasi; matriks permission per role belum didefinisikan |

### 4.15 Laporan/Export Keuangan
| ID | Requirement | Status |
|---|---|---|
| RF-51 | Laporan pendapatan/penjualan per rentang tanggal, dengan kemampuan export | ✅ Selesai & teruji — `SalesReportTest.php` (5 test), export CSV dengan UTF-8 BOM |

---

## 5. Kebutuhan Non-Fungsional (RNF)

| ID | Requirement | Bukti |
|---|---|---|
| RNF-01 | Integritas data di bawah konkurensi — row-level locking (`lockForUpdate`) + `DB::transaction()` di semua mutasi status kritis | Diverifikasi via `WebhookIdempotencyTest.php`, `OrderStatusAdminTest.php`, `AutoCompleteDeliveredOrdersTest.php` — semua pakai pola lock-then-recheck yang konsisten |
| RNF-02 | Keamanan — nilai dari client tidak pernah dipercaya untuk field kritis (ongkir, stok, harga) | Diverifikasi via `ShippingTest.php` (server re-fetch ongkir, abaikan nilai client) |
| RNF-03 | Akurasi timestamp — timezone eksplisit di-set `Asia/Jakarta`, tidak mengandalkan default framework | Bug ditemukan & difix: `config/app.php` sebelumnya hardcode `'UTC'` tanpa baca `.env`, menyebabkan semua jam pesanan tampil salah 7 jam meski label "WIB" sudah benar di Blade. Diverifikasi via tinker: `now()` match `Carbon::now('Asia/Jakarta')` |
| RNF-04 | Cakupan test wajib — setiap fitur harus punya bukti test otomatis sebelum dianggap selesai | Total test lolos meningkat bertahap sepanjang sesi: 150 → 153 → 166 → 178 → 191 → 204 → 205 → 210 → 215 → 222 → 228 → 298 → **313** (terkonfirmasi via `vendor/bin/phpunit` per 7 Sep 2026) |

---

## 6. Ringkasan Model Data

Berdasarkan tabel yang terkonfirmasi ada lewat migration yang dijalankan sepanjang sesi:

**Domain Pengguna:** `users`, `addresses`  
**Domain Katalog:** `categories`, `products`, `product_variants`, `product_images`  
**Domain Transaksi:** `carts`, `cart_items`, `orders`, `order_items`, `payments`, `shipments`, `vouchers`, `voucher_usages`  
**Domain Engagement:** `wishlists`, `reviews`  
**Domain Konfigurasi:** `shop_settings`, `shipping_discount_rules`, `couriers`  
**Domain Retur (baru):** `returns`, `return_items`

**Tabel yang PERLU DIBUAT untuk fitur yang masih direncanakan (belum ada):**
- Kemungkinan `payment_methods` (untuk RF-48)
- Kemungkinan tabel role/permission (untuk RF-50 — struktur exact tergantung hasil sesi spesifikasi)
- Tidak perlu tabel baru untuk RF-49 (Kelola Akun Customer) — cukup query `users` + `orders` yang sudah ada

---

## 7. Di Luar Cakupan / Ditunda

| Item | Alasan |
|---|---|
| Komerce Delivery Order API (Komship) | Terkunci di tier Enterprise (~Rp249rb/bulan), menunggu diskusi biaya dengan klien |
| Presisi alamat ke level kecamatan/kelurahan | Ditunda, prioritas rendah |

---

## 8. Pertanyaan Terbuka / Keputusan Tertunda

1. **Desain visual final untuk admin CRUD** (Produk, Kategori, Voucher, Ulasan, Banner) — 10 halaman mockup tema Atelier Rose Gold sudah selesai dibuat dan sidebar fixed telah distandarkan. Siap diimplementasikan ke Blade.
2. **Homepage Hero Banner** — apakah mendukung banyak slide (carousel) atau 1 banner statis saja? (Carousel-nya sendiri sudah berjalan, tinggal soal jumlah slide yang perlu di-CMS-kan)
3. **Matriks permission Admin/Staff (RF-50)** — perlu sesi spesifikasi terpisah untuk menentukan persis apa yang boleh/tidak boleh diakses role "Staff" vs "Superadmin/Owner" sebelum implementasi dimulai.
4. **Format export Laporan Keuangan (RF-51)** — CSV, Excel, atau PDF belum diputuskan.
5. **Scope Kelola Akun Customer (RF-49)** — diasumsikan read-only + toggle aktif/nonaktif; perlu dikonfirmasi apakah admin juga butuh edit data pribadi customer secara langsung.
6. **Field tambahan modul Retur (RF-38)** — `return_number`, `resolution_type`, `refund_amount`: **✅ SUDAH TERVERIFIKASI & TERUJI** via `ReturnRequestTest.php` (13 test lolos, migration Batch 5 sudah ran, total 228/228 test suite lolos).
7. **Status commit git modul Retur** — file skema `returns`/`return_items` dan migration masih staged/untracked di git working tree, menunggu commit resmi.

---

## 9. Glosarium

**Status Pesanan (ENUM):**
- `pending` — Menunggu Pembayaran
- `paid` — Pembayaran Berhasil
- `diproses` — Sedang Diproses
- `dikirim` — Sedang Dikirim
- `selesai` — Pesanan Selesai / Diterima
- `dibatalkan` — Dibatalkan

**SKU:**
- `products.sku` — kode identifikasi tingkat produk, opsional, unik, input manual
- `product_variants.sku` — kode identifikasi tingkat varian (mis. beda ukuran/warna), opsional, unik secara global (bukan cuma unik dalam 1 produk), input manual

**Status Retur (ENUM):**
- `diajukan` — Baru diajukan customer
- `disetujui` — Admin menyetujui klaim
- `ditolak` — Admin menolak klaim
- `diproses` — Barang retur sedang diproses (mis. diterima gudang)
- `selesai` — Retur selesai (barang ditukar / dana dikembalikan)

**Resolution Type (Retur):** `tukar_barang` (exchange) atau `refund` (pengembalian dana) — dipilih customer saat mengajukan, nominal refund final ditentukan admin.

---

*Dokumen ini hidup dan harus diperbarui setiap kali status sebuah requirement berubah, dengan bukti (test output/commit hash) sebagai syarat perubahan status ke "✅ Selesai".*
