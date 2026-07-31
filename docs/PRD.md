# Product Requirements Document (PRD)
# el Craft — Sistem E-Commerce Aksesoris Wanita

**Versi:** 1.0  
**Tanggal:** 31 Juli 2026  
**Status:** Draft — menunggu persetujuan klien  
**Stack:** Laravel 13 · PHP 8.3 · Tailwind CSS · MySQL · Midtrans Snap · RajaOngkir Starter

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
| Frontend | Blade + Tailwind CSS + Vanilla JS |
| Database | MySQL (via Laragon lokal; produksi: VPS) |
| Autentikasi | Laravel Breeze (session-based) |
| Pembayaran | Midtrans Snap (Sandbox ke Production) |
| Ongkos Kirim | RajaOngkir Starter (JNE, POS, TIKI) |
| Email | Mailtrap (development) ke SMTP produksi |
| Queue | Laravel Queue (worker direncanakan untuk produksi) |
| Cache | Laravel Cache — digunakan untuk data provinsi/kota RajaOngkir (7 hari) |

---

## 2. Aktor

| Aktor | Deskripsi | Autentikasi |
|-------|-----------|-------------|
| **Tamu (Guest)** | Pengunjung yang belum login. Dapat melihat produk, kategori, homepage. Tidak dapat checkout atau wishlist. | Tidak diperlukan |
| **Pelanggan (Customer)** | Pengguna terdaftar dengan role = customer. Dapat melakukan seluruh alur belanja. | Email + Password (Breeze) |
| **Admin** | Pengguna dengan role = admin. Mengelola seluruh konten dan operasional toko. | Email + Password (Breeze) — redirect ke /admin/dashboard |

> **Catatan Kode:** Kolom `users.role` adalah enum `['customer', 'admin']` dengan default `'customer'`. Kolom `users.is_active` (boolean, default `true`) tersedia di skema database namun belum ada logika *enforcement* — pengguna dengan `is_active = false` saat ini masih dapat login.

---

## 3. Matriks Status Fitur

### 3.1 Fitur Pelanggan (Customer-Facing)

| No | Fitur | Status | Catatan |
|----|-------|--------|---------|
| C1 | Registrasi akun | Selesai | Breeze default — email + password |
| C2 | Login / Logout | Selesai | Breeze default |
| C3 | Lupa password (reset via email) | Selesai | Breeze default — password_reset_tokens |
| C4 | Edit profil (nama, email, avatar, telepon) | Selesai | ProfileController + AddressController |
| C5 | Manajemen buku alamat | Selesai | CRUD + set alamat default |
| C6 | Browse produk + filter (kategori, harga, nama, sort) | Selesai | ProductController::index() |
| C7 | Halaman detail produk + varian + produk terkait | Selesai | ProductController::show() |
| C8 | Keranjang belanja (tambah, update qty, hapus) | Selesai | CartController — 1 cart per user |
| C9 | Checkout dengan pemilihan kurir dan layanan | Selesai | CheckoutController — validasi stok server-side |
| C10 | Kalkulasi ongkos kirim real-time (RajaOngkir) | Selesai | ShippingController — province ke city ke cost |
| C11 | Penerapan voucher diskon saat checkout | Selesai | VoucherController::apply() — validasi server-side |
| C12 | Pembayaran via Midtrans Snap | Selesai | QRIS, VA BCA/BNI/BRI, GoPay, ShopeePay, CC |
| C13 | Halaman konfirmasi setelah bayar | Selesai | checkout.finish view |
| C14 | Riwayat pesanan + detail pesanan | Selesai | OrderController::index() + show() |
| C15 | Pembatalan pesanan mandiri | Selesai | Hanya status pending yang dapat dibatalkan |
| C16 | Wishlist (toggle, daftar, hapus, count) | Selesai | WishlistController — AJAX + halaman /wishlist |
| C17 | Ulasan produk (rating + komentar + foto) | Selesai | ReviewController::store() — terikat order_item_id |
| C18 | Homepage: New Arrivals | Selesai | Produk is_featured = true |
| C19 | Homepage: Produk Terpopuler | Selesai | Ranking berdasarkan total unit terjual; fallback ke latest |
| C20 | Homepage: Sedang Diskon | Selesai | Filter discount_type bukan none + tanggal aktif; hidden jika kosong |
| C21 | Login Google OAuth | Belum Dimulai | Kolom google_id ada di DB; Socialite TIDAK terinstall; tidak ada logic OAuth |
| C22 | Verifikasi email | Parsial | Controller ada, MustVerifyEmail di-comment pada User.php — tidak diaktifkan |
| C23 | Notifikasi email (pesanan dikonfirmasi, dikirim) | Belum Dimulai | Tidak ada class Mail, Notification, atau Mailable di codebase |

### 3.2 Fitur Admin (Back-Office)

| No | Fitur | Status | Catatan |
|----|-------|--------|---------|
| A1 | Dashboard Admin (ringkasan statistik) | Belum Dimulai | Route /admin/dashboard hanya mengembalikan string HTML statis |
| A2 | Manajemen Produk (CRUD + varian + diskon) | Belum Dimulai | Tidak ada controller atau view admin untuk produk |
| A3 | Manajemen Kategori (CRUD) | Belum Dimulai | Tidak ada controller atau view admin untuk kategori |
| A4 | Manajemen Banner | Belum Dimulai | Tidak ada tabel banners di database; perlu migrasi baru |
| A5 | Manajemen Pesanan (list, detail, update status) | Belum Dimulai | Tidak ada controller admin — pesanan hanya bisa dilihat dari sisi pelanggan |
| A6 | Lihat Status Pembayaran | Belum Dimulai | Data pembayaran tersimpan di tabel payments, namun tanpa tampilan admin |
| A7 | Manajemen Voucher (buat, aktifkan/nonaktifkan) | Belum Dimulai | Tabel vouchers dan voucher_usages ada; logic apply() aktif di checkout; tidak ada UI admin |
| A8 | Moderasi Ulasan (approve/reject) | Belum Dimulai | Kolom reviews.is_approved default false; tidak ada UI admin untuk mengubahnya |
| A9 | Manajemen Pengguna (lihat, aktifkan/nonaktifkan) | Belum Dimulai | Kolom users.is_active ada tapi tidak di-enforce; tidak ada UI admin |
| A10 | Manajemen Retur/Refund | Belum Dimulai | Tidak ada ReturnController — disebutkan dalam konteks tapi tidak ada di codebase |

---

## 4. Kebutuhan Fungsional — Pelanggan

### 4.1 Modul Autentikasi

- **REQ-C-AUTH-01:** Sistem harus mengizinkan pengguna baru mendaftar menggunakan nama, email, dan password. Password di-hash menggunakan Bcrypt.
- **REQ-C-AUTH-02:** Sistem harus mengizinkan pengguna login menggunakan email dan password terdaftar.
- **REQ-C-AUTH-03:** Sistem harus menyediakan fitur reset password melalui link email yang dikirim ke alamat terdaftar.
- **REQ-C-AUTH-04:** Sistem harus mengizinkan pengguna logout dari sesi aktif.
- **REQ-C-AUTH-05:** Semua halaman yang memerlukan data pengguna (keranjang, checkout, pesanan, profil, wishlist) harus dilindungi middleware `auth` dan mengarahkan tamu ke halaman login.

### 4.2 Modul Profil dan Alamat

- **REQ-C-PROFILE-01:** Pengguna dapat mengubah nama, email, nomor telepon, dan foto profil (avatar).
- **REQ-C-PROFILE-02:** Pengguna dapat menambah, mengubah, menghapus, dan menentukan alamat pengiriman utama (default).
- **REQ-C-PROFILE-03:** Setiap alamat menyimpan: nama penerima, telepon, alamat lengkap, kota, provinsi, city_id, province_id (untuk kalkulasi ongkir), dan kode pos.

### 4.3 Modul Produk dan Pencarian

- **REQ-C-PRODUCT-01:** Sistem menampilkan semua produk aktif (is_active = true) dengan paginasi 9 produk per halaman.
- **REQ-C-PRODUCT-02:** Pelanggan dapat memfilter produk berdasarkan: kategori (slug), nama (pencarian teks), rentang harga (min/max).
- **REQ-C-PRODUCT-03:** Pelanggan dapat mengurutkan produk berdasarkan: terbaru (created_at DESC), harga rendah ke tinggi, harga tinggi ke rendah, atau popularitas (is_featured DESC).
- **REQ-C-PRODUCT-04:** Halaman detail produk menampilkan: semua gambar (diurutkan sort_order), varian aktif, ulasan yang sudah disetujui (max 10 terbaru), dan produk terkait (kategori sama, max 4, acak).
- **REQ-C-PRODUCT-05:** Jika produk memiliki diskon aktif (tipe percent atau nominal, dalam rentang tanggal discount_start — discount_end), sistem menampilkan harga coret dan harga final.
- **REQ-C-PRODUCT-06:** Rating rata-rata dan jumlah ulasan ditampilkan pada halaman detail produk (kolom rating_avg dan rating_count di tabel products).

### 4.4 Modul Keranjang Belanja

- **REQ-C-CART-01:** Setiap pengguna terdaftar memiliki tepat satu keranjang (carts.user_id unik). Keranjang dibuat otomatis saat pertama kali dibutuhkan.
- **REQ-C-CART-02:** Pelanggan dapat menambahkan produk (dengan atau tanpa varian) ke keranjang. Kombinasi (cart_id, product_id, variant_id) bersifat unik — penambahan produk yang sama akan menambah kuantitas.
- **REQ-C-CART-03:** Pelanggan dapat mengubah kuantitas item atau menghapus item dari keranjang.
- **REQ-C-CART-04:** Jumlah item di keranjang (badge) ditampilkan di navbar — diperbarui via AJAX (/cart/count).
- **REQ-C-CART-05:** Harga satuan yang digunakan adalah final_price (sudah memperhitungkan diskon produk yang aktif).

### 4.5 Modul Checkout dan Pembayaran

- **REQ-C-CHECKOUT-01:** Checkout hanya tersedia untuk pengguna yang sudah login dan memiliki keranjang tidak kosong.
- **REQ-C-CHECKOUT-02:** Pelanggan mengisi: nama penerima, nomor telepon, alamat lengkap, provinsi, kota/kabupaten (dari dropdown data RajaOngkir), kode pos, memilih kurir (JNE/POS/TIKI) dan layanan, serta catatan (opsional).
- **REQ-C-CHECKOUT-03:** Sistem menghitung ongkos kirim real-time dari API RajaOngkir berdasarkan city_id tujuan, berat total order (dalam gram), dan pilihan kurir. Hasil di-cache selama 1 jam.
- **REQ-C-CHECKOUT-04:** Sebelum membuat Snap Token, sistem memvalidasi ulang ketersediaan stok dari database (bukan dari sesi/cache) untuk mencegah overselling.
- **REQ-C-CHECKOUT-05:** Pelanggan dapat memasukkan kode voucher. Sistem memvalidasi: status aktif, kuota, tanggal berlaku, dan minimum pembelian — semua dilakukan server-side.
- **REQ-C-CHECKOUT-06:** Order dibuat dalam DB dengan status pending sebelum Snap Token di-request ke Midtrans, sehingga data tidak hilang jika terjadi kegagalan di sisi Midtrans.
- **REQ-C-CHECKOUT-07:** Pembayaran dilakukan melalui popup Midtrans Snap. Metode yang diaktifkan: Kartu Kredit (3DS), VA BCA/BNI/BRI/Lainnya, GoPay, ShopeePay, QRIS, Indomaret, Alfamart.
- **REQ-C-CHECKOUT-08:** Webhook Midtrans (POST /midtrans/webhook) memverifikasi signature sebelum memproses update status. Setelah pembayaran berhasil (settlement/capture): status order ke paid, stok dikurangi, data shipment awal dibuat, penggunaan voucher dicatat.
- **REQ-C-CHECKOUT-09:** Jika pembayaran ditolak/kedaluwarsa (deny/expire/cancel), status order ke dibatalkan dan stok dipulihkan (jika sebelumnya sudah dikurangi).

### 4.6 Modul Pesanan

- **REQ-C-ORDER-01:** Pelanggan dapat melihat riwayat semua pesanan miliknya, diurutkan terbaru, dengan paginasi 10 per halaman.
- **REQ-C-ORDER-02:** Pelanggan dapat melihat detail satu pesanan: item, varian, alamat pengiriman, data pembayaran, data pengiriman (kurir, no. resi), dan voucher yang digunakan.
- **REQ-C-ORDER-03:** Pelanggan hanya dapat membatalkan pesanan berstatus pending. Pembatalan mengubah status order ke dibatalkan dan payment ke failed.
- **REQ-C-ORDER-04:** Sistem mencegah akses IDOR — detail pesanan hanya dapat diakses oleh pemilik pesanan (query di-scope pada relasi user->orders).

### 4.7 Modul Wishlist

- **REQ-C-WISH-01:** Pelanggan dapat melakukan toggle wishlist (tambah/hapus) dari halaman produk atau listing via AJAX.
- **REQ-C-WISH-02:** Pelanggan dapat melihat seluruh produk dalam wishlist di halaman /wishlist, berikut gambar, kategori, dan rating.
- **REQ-C-WISH-03:** Jumlah item wishlist (badge di navbar) diperbarui real-time via endpoint /wishlist/count.
- **REQ-C-WISH-04:** Tamu yang mencoba mengakses wishlist diarahkan ke halaman login.

### 4.8 Modul Ulasan Produk

- **REQ-C-REVIEW-01:** Pelanggan hanya dapat mengulas produk yang terikat pada order_item_id miliknya — satu ulasan per item pesanan (unique constraint: user_id + order_item_id).
- **REQ-C-REVIEW-02:** Ulasan terdiri dari: rating (1-5 bintang), komentar teks (opsional, maks 1000 karakter), dan satu foto (opsional, maks 2 MB, format jpg/jpeg/png/webp).
- **REQ-C-REVIEW-03:** Setiap ulasan baru dikirim dengan status is_approved = false dan hanya tampil di halaman produk setelah disetujui admin.
- **REQ-C-REVIEW-04:** Sistem memverifikasi otorisasi via Gate::allows('writeReview') sebelum menyimpan ulasan.

---

## 5. Kebutuhan Fungsional — Admin

> Seluruh fitur admin berada di bawah prefix route /admin/ dan dilindungi middleware auth + pengecekan role === 'admin'. Semua fitur di bawah ini belum diimplementasikan dan merupakan scope pengembangan berikutnya.

### 5.1 Dashboard (A1)

- **REQ-A-DASH-01:** Dashboard menampilkan ringkasan: total pendapatan hari ini / bulan ini, jumlah pesanan baru (status pending), jumlah pesanan diproses, jumlah produk aktif, jumlah pengguna terdaftar.
- **REQ-A-DASH-02:** Dashboard menampilkan grafik penjualan 7 hari / 30 hari terakhir.
- **REQ-A-DASH-03:** Dashboard menampilkan tabel 5 pesanan terbaru dengan link ke detail.
- **REQ-A-DASH-04:** Dashboard menampilkan 5 produk terlaris (berdasarkan total unit terjual dari pesanan berstatus paid/diproses/dikirim/selesai).

### 5.2 Manajemen Produk (A2)

- **REQ-A-PROD-01:** Admin dapat melihat daftar semua produk (aktif dan non-aktif) dengan filter kategori, status, dan pencarian nama. Paginasi diperlukan.
- **REQ-A-PROD-02:** Admin dapat membuat produk baru dengan mengisi: nama, slug (auto-generate dari nama), kategori, deskripsi, harga dasar, berat (gram), status aktif, status unggulan (is_featured).
- **REQ-A-PROD-03:** Admin dapat mengatur diskon produk: tipe (none/percent/nominal), nilai diskon, tanggal mulai, tanggal berakhir.
- **REQ-A-PROD-04:** Admin dapat mengelola varian produk: tambah/ubah/hapus varian (nama varian, tipe, harga tambahan, stok, SKU, status aktif).
- **REQ-A-PROD-05:** Admin dapat mengunggah satu atau lebih gambar produk, menandai gambar utama (is_primary), dan mengatur urutan tampil (sort_order).
- **REQ-A-PROD-06:** Admin dapat menonaktifkan (is_active = false) atau menghapus produk. Produk tidak dapat dihapus jika memiliki order item terkait (restrictOnDelete).
- **REQ-A-PROD-07:** Perubahan stok melalui antarmuka admin harus tercatat (minimal log aktivitas sederhana).

### 5.3 Manajemen Kategori (A3)

- **REQ-A-CAT-01:** Admin dapat membuat kategori baru: nama, slug, deskripsi, gambar, status aktif.
- **REQ-A-CAT-02:** Admin dapat mengubah dan menonaktifkan kategori.
- **REQ-A-CAT-03:** Kategori tidak dapat dihapus jika masih ada produk yang menggunakannya (restrictOnDelete pada FK products.category_id).

### 5.4 Manajemen Banner (A4)

> **Catatan:** Tabel `banners` belum ada di database. Implementasi memerlukan migrasi baru.

- **REQ-A-BANNER-01:** Admin dapat mengelola banner carousel pada halaman homepage (judul, gambar, URL tautan, urutan tampil, status aktif).
- **REQ-A-BANNER-02:** Sistem membaca banner aktif secara dinamis dari database, bukan dari data statis di view.

### 5.5 Manajemen Pesanan (A5)

- **REQ-A-ORDER-01:** Admin dapat melihat semua pesanan dari semua pengguna, dengan filter status, tanggal, dan pencarian nomor pesanan.
- **REQ-A-ORDER-02:** Admin dapat melihat detail lengkap satu pesanan: item, pelanggan, alamat, pembayaran, dan pengiriman.
- **REQ-A-ORDER-03:** Admin dapat memperbarui status pesanan mengikuti alur: pending ke paid (otomatis via webhook) ke diproses ke dikirim ke selesai.
- **REQ-A-ORDER-04:** Saat status diubah ke dikirim, admin mengisi nomor resi dan nama kurir pada record shipments.
- **REQ-A-ORDER-05:** Admin dapat membatalkan pesanan yang belum selesai dengan alasan, dan memulai proses refund jika pembayaran sudah terjadi.

### 5.6 Monitoring Pembayaran (A6)

- **REQ-A-PAY-01:** Admin dapat melihat daftar semua record pembayaran dengan status (pending/success/failed/expired), metode pembayaran, dan jumlah.
- **REQ-A-PAY-02:** Admin dapat melihat detail payload webhook Midtrans (response_data JSON) yang tersimpan per transaksi untuk keperluan investigasi.
- **REQ-A-PAY-03:** Admin dapat menandai pembayaran secara manual sebagai success untuk kasus edge (misal: transfer manual yang tidak terdeteksi webhook).

### 5.7 Manajemen Voucher (A7)

- **REQ-A-VCHR-01:** Admin dapat membuat voucher baru dengan: kode unik, deskripsi, tipe diskon (percent/nominal), nilai diskon, minimum pembelian, batas maksimal diskon, kuota penggunaan, tanggal mulai, tanggal berakhir, dan status aktif.
- **REQ-A-VCHR-02:** Admin dapat mengaktifkan atau menonaktifkan voucher kapan saja (is_active).
- **REQ-A-VCHR-03:** Admin dapat melihat daftar penggunaan setiap voucher: siapa yang menggunakannya, di pesanan mana, dan kapan (voucher_usages).
- **REQ-A-VCHR-04:** Admin tidak dapat menghapus voucher yang sudah digunakan untuk menjaga integritas data historis.

### 5.8 Moderasi Ulasan (A8)

- **REQ-A-REVIEW-01:** Admin dapat melihat semua ulasan dengan filter: belum disetujui (is_approved = false), sudah disetujui, semua.
- **REQ-A-REVIEW-02:** Admin dapat menyetujui ulasan — mengubah is_approved menjadi true. Ulasan yang disetujui langsung tampil di halaman produk.
- **REQ-A-REVIEW-03:** Admin dapat menolak (menghapus) ulasan yang tidak sesuai aturan.
- **REQ-A-REVIEW-04:** Setelah moderasi, rating_avg dan rating_count pada tabel products harus diperbarui.

### 5.9 Manajemen Pengguna (A9)

- **REQ-A-USER-01:** Admin dapat melihat daftar semua pengguna dengan filter role dan status aktif.
- **REQ-A-USER-02:** Admin dapat menonaktifkan akun pengguna (is_active = false). Pengguna yang dinonaktifkan tidak dapat login.
- **REQ-A-USER-03:** Admin dapat melihat riwayat pesanan milik pengguna tertentu.
- **REQ-A-USER-04:** Admin dapat mengubah role pengguna (customer ke admin) dengan konfirmasi eksplisit.

### 5.10 Manajemen Retur/Refund (A10)

> **Catatan:** Tidak ada `ReturnController` dalam codebase saat ini. Ini adalah fitur baru sepenuhnya.

- **REQ-A-RETURN-01:** Admin dapat membuat request retur/refund manual untuk pesanan berstatus selesai atau dikirim dengan mencatat alasan dan jumlah refund.
- **REQ-A-RETURN-02:** Admin dapat mengubah status retur: diajukan ke disetujui ke diproses ke selesai.
- **REQ-A-RETURN-03:** Setelah refund disetujui, stok produk terkait harus dikembalikan secara manual oleh admin atau otomatis oleh sistem.

---

## 6. Kebutuhan Non-Fungsional

### 6.1 Keamanan (Security)

| Aspek | Implementasi Saat Ini |
|-------|----------------------|
| Validasi stok server-side | CheckoutController::createSnapToken() memvalidasi stok dari DB sebelum membuat token Midtrans |
| Validasi harga server-side | Ongkos kirim di-re-fetch dari RajaOngkir API; tidak menggunakan nilai dari form client |
| Pencegahan IDOR pesanan | Query pesanan di-scope pada auth()->user()->orders() |
| Signature webhook | MidtransService::verifySignature() memverifikasi SHA-512 sebelum memproses notifikasi |
| Otorisasi ulasan | Gate::allows('writeReview') — pelanggan hanya bisa mengulas item miliknya |
| Perlindungan CSRF | Semua form menggunakan token CSRF Laravel; webhook Midtrans dikecualikan di bootstrap/app.php |
| Hashing password | Bcrypt via Laravel Hashing |
| Admin role check | Route /admin/* memeriksa role === 'admin', abort 403 jika tidak |
| Kolom sensitif hidden | password dan remember_token di-set #[Hidden] pada User.php |

### 6.2 Keandalan (Reliability)

- **REQ-NFR-REL-01:** Transaksi DB (DB::transaction) digunakan pada operasi kritis: pembuatan order, pemrosesan webhook pembayaran, pembatalan dengan pemulihan stok — untuk menjamin atomisitas.
- **REQ-NFR-REL-02:** Webhook Midtrans menggunakan cek idempotency (if order && order->status !== 'paid') untuk mencegah double-processing.
- **REQ-NFR-REL-03:** Semua error API eksternal (RajaOngkir, Midtrans) di-catch dan di-log via Log::error(); respons pengguna menggunakan pesan yang user-friendly.
- **REQ-NFR-REL-04:** Email transaksional direncanakan (belum diimplementasikan) untuk notifikasi pesanan dikonfirmasi dan dikirim. Pengiriman email harus menggunakan try-catch untuk mencegah kegagalan email memblokir alur pesanan.
- **REQ-NFR-REL-05:** Queue worker (php artisan queue:listen) direncanakan untuk produksi guna mengisolasi proses email dan tugas async dari request HTTP.

### 6.3 Performa (Performance)

- **REQ-NFR-PERF-01:** Data provinsi dan kota RajaOngkir di-cache selama 7 hari. Data ongkos kirim di-cache selama 1 jam.
- **REQ-NFR-PERF-02:** Semua query Eloquent pada halaman listing menggunakan eager loading (with(...)) untuk mencegah N+1 queries.
- **REQ-NFR-PERF-03:** Aset frontend (CSS/JS) di-compile via Vite dan di-cache oleh browser menggunakan content hash.

### 6.4 Skalabilitas (Scalability)

Deployment produksi direncanakan pada VPS Linux (Nginx/Apache) dengan:
- Queue worker (supervisor) untuk proses async
- Cron job (php artisan schedule:run) untuk tugas terjadwal
- Redis atau database cache driver untuk session dan cache

Sistem tidak dirancang untuk high-concurrency ekstrem; target adalah UMKM skala menengah (ratusan order per hari).

---

## 7. Ringkasan Model Data

### 7.1 Daftar Entitas

| Tabel | Model | Tujuan |
|-------|-------|--------|
| users | User | Akun pengguna (customer dan admin) |
| categories | Category | Kategori produk (gelang, kalung, dst.) |
| products | Product | Katalog produk dengan harga, diskon, stok, dan berat |
| product_images | ProductImage | Galeri gambar produk; mendukung flag is_primary dan sort_order |
| product_variants | ProductVariant | Varian produk (warna, ukuran, dll.) dengan harga tambahan dan stok sendiri |
| carts | Cart | Satu keranjang per pengguna terdaftar |
| cart_items | CartItem | Item dalam keranjang — kombinasi unik (cart_id, product_id, variant_id) |
| addresses | Address | Buku alamat pengiriman pengguna (mendukung city_id/province_id RajaOngkir) |
| vouchers | Voucher | Kode voucher diskon dengan tipe, nilai, kuota, dan masa berlaku |
| voucher_usages | VoucherUsage | Catatan penggunaan voucher per user per order |
| orders | Order | Header pesanan dengan status (pending ke paid ke diproses ke dikirim ke selesai ke dibatalkan) |
| order_items | OrderItem | Baris item dalam satu pesanan; menyimpan snapshot nama dan harga saat pembelian |
| payments | Payment | Data transaksi Midtrans per order (snap_token, response_data webhook) |
| shipments | Shipment | Data pengiriman per order (kurir, resi, waktu kirim/terima) |
| reviews | Review | Ulasan produk terikat order_item_id; moderasi via is_approved |
| wishlists | Wishlist | Daftar produk yang di-save pengguna |
| sessions | (bawaan Laravel) | Manajemen sesi |
| cache | (bawaan Laravel) | Penyimpanan cache |
| jobs | (bawaan Laravel) | Antrian job (queue) |

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

### 7.3 Field Penting yang Perlu Diperhatikan

| Tabel.Kolom | Tipe | Keterangan |
|-------------|------|------------|
| users.role | enum(customer, admin) | Hak akses pengguna |
| users.is_active | boolean | Status aktif — belum di-enforce di login |
| users.google_id | string nullable | Reserved untuk OAuth — belum digunakan |
| products.discount_type | enum(none, percent, nominal) | Tipe diskon produk |
| products.discount_start / discount_end | date nullable | Rentang tanggal diskon aktif |
| products.rating_avg / rating_count | decimal/int | Cache agregat rating dari ulasan |
| orders.status | enum 6 nilai | Alur status pesanan |
| payments.status | enum(pending, success, failed, expired) | Status transaksi Midtrans |
| reviews.is_approved | boolean default false | Flag moderasi ulasan |
| shipments.tracking_number | string nullable | Diisi admin saat status dikirim |

---

## 8. Di Luar Cakupan Saat Ini (Out of Scope)

### 8.1 Login Google OAuth

**Status:** Ditangguhkan (deferred).

**Alasan:** Kolom `users.google_id` sudah tersedia di database sebagai persiapan. Namun paket `laravel/socialite` belum terinstall di `composer.json` dan tidak ada satu pun baris kode logic OAuth dalam codebase. Implementasi membutuhkan: instalasi Socialite, pembuatan Google OAuth credentials (Google Cloud Console), serta penanganan edge case seperti akun email yang sudah terdaftar via password. Fitur ini akan dipertimbangkan pada rilis v1.1 berdasarkan prioritas klien.

### 8.2 Otomatisasi Delivery Order (Komship/Collaborator API)

**Status:** Ditangguhkan (deferred) — beda modul, beda integrasi.

**Alasan:** Integrasi RajaOngkir yang ada (via `ShippingService`) hanya digunakan untuk kalkulasi biaya kirim. Pembuatan Delivery Order (DO) ke mitra logistik memerlukan API yang berbeda (Komship atau Collaborator API milik Komerce), dengan base URL dan API Key yang berbeda dari API RajaOngkir saat ini. Implementasi modul DO membutuhkan desain ulang alur shipments, integrasi callback pickup dari kurir, dan koordinasi dengan klien mengenai akun logistik yang akan digunakan.

---

## 9. Technical Debt yang Diketahui

### 9.1 Status Otomatis 'Manual' pada Shipment Saat Pembayaran Diterima

**Lokasi:** `app/Http/Controllers/MidtransWebhookController.php`, baris 104-111

**Masalah:** Saat webhook Midtrans menerima pembayaran sukses, sistem otomatis membuat record di tabel `shipments` dengan `courier = 'Manual'` dan `service = 'Reguler'`. Kurir dan layanan yang dipilih pelanggan di halaman checkout tidak disimpan ke kolom dedicated manapun di tabel orders atau shipments.

**Dampak:** Admin tidak memiliki data kurir pilihan pelanggan secara terstruktur. Nomor resi dan kurir harus diisi ulang secara manual oleh admin.

**Solusi yang Direncanakan:** Saat implementasi admin panel (A5), simpan kurir dan layanan pilihan pelanggan ke shipments saat order dibuat, dan hapus pembuatan otomatis courier = 'Manual' dari webhook.

### 9.2 Komentar TODO Sisa di MidtransWebhookController

**Lokasi:** `app/Http/Controllers/MidtransWebhookController.php`, baris 58-59

**Isi:**
```php
// TODO: Ganti dengan model Order yang sebenarnya setelah dibuat
// $order = Order::where('order_number', $orderId)->firstOrFail();
```

**Catatan:** Komentar sisa dari masa pengembangan awal. Model Order sudah ada dan digunakan. Dapat dihapus tanpa dampak fungsional.

### 9.3 Email Notifikasi Belum Diimplementasikan

**Konteks:** Dalam deskripsi proyek disebutkan notifikasi email via Mailtrap, namun tidak ada class Mailable, Notification, atau pemanggilan Mail::send() di seluruh codebase. Tidak ada direktori app/Mail/ maupun app/Notifications/.

**Dampak:** Pelanggan tidak menerima email konfirmasi setelah membayar atau setelah pesanan dikirim.

**Prioritas:** Harus diselesaikan sebelum go-live produksi.

### 9.4 users.is_active Tidak Di-Enforce

**Lokasi:** Migrasi `2026_06_15_144210_add_custom_fields_to_users_table.php`

**Masalah:** Kolom `is_active` ada di database namun tidak ada middleware atau guard yang memeriksa nilai ini saat pengguna login. Pengguna yang di-set `is_active = false` tetap dapat masuk ke sistem.

**Solusi:** Tambahkan pengecekan is_active di middleware autentikasi.

### 9.5 Kurir Checkout Terbatas pada RajaOngkir Starter

**Lokasi:** `CheckoutController.php` (validasi in:jne,pos,tiki), `ShippingController.php`

**Masalah:** RajaOngkir Starter Plan hanya mendukung 3 kurir (JNE, POS, TIKI). Kurir populer seperti JNT, SiCepat, AnterAja, dan Ninja tidak tersedia.

**Solusi yang Direncanakan:** Upgrade ke RajaOngkir Pro/Basic atau migrasi ke provider alternatif (Binderbyte, dll.).

### 9.6 rating_avg dan rating_count Tidak Diperbarui Otomatis

**Lokasi:** Tabel `products`, kolom `rating_avg` dan `rating_count`

**Masalah:** Kolom ini ada di database namun tidak ada trigger, observer, atau logika apapun yang memperbarui nilainya ketika ulasan baru disetujui atau dihapus.

**Solusi:** Tambahkan pembaruan agregat di ReviewController saat moderasi diimplementasikan, atau gunakan database trigger/observer.

---

## 10. Rencana Implementasi Berikutnya

Urutan prioritas pengembangan yang disarankan berdasarkan dampak bisnis:

| Prioritas | Modul | Alasan |
|-----------|-------|--------|
| P0 (Kritis) | Notifikasi Email (C23) | Wajib sebelum go-live — kepercayaan pelanggan |
| P0 (Kritis) | Enforce is_active login (9.4) | Keamanan dasar |
| P1 (Tinggi) | Admin: Manajemen Produk (A2) | Operasional toko tidak bisa berjalan tanpa ini |
| P1 (Tinggi) | Admin: Manajemen Pesanan (A5) | Operasional toko tidak bisa berjalan tanpa ini |
| P1 (Tinggi) | Admin: Dashboard (A1) | Visibilitas bisnis |
| P2 (Sedang) | Admin: Moderasi Ulasan (A8) | Reputasi produk |
| P2 (Sedang) | Admin: Manajemen Voucher (A7) | Kampanye marketing |
| P2 (Sedang) | Admin: Manajemen Banner (A4) | Promosi homepage (perlu migrasi DB baru) |
| P3 (Rendah) | Admin: Manajemen Kategori (A3) | Mendukung A2 |
| P3 (Rendah) | Admin: Manajemen Pengguna (A9) | Kontrol akses |
| P4 (Masa Depan) | Admin: Retur/Refund (A10) | After-sales service |
| P4 (Masa Depan) | Google OAuth (C21) | Nice-to-have |
| P4 (Masa Depan) | Delivery Order API (Komship) | Otomatisasi logistik |

---

*Dokumen ini dihasilkan berdasarkan investigasi langsung terhadap source code repository pada tanggal 31 Juli 2026. Semua pernyataan status fitur telah diverifikasi terhadap file aktual.*
