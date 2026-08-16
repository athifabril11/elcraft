# Status Report — el Craft
**Tanggal:** 12 Agustus 2026  
**Diverifikasi terhadap:** source code aktual (bukan ringkasan percakapan)

---

## Ringkasan Singkat

el Craft adalah platform e-commerce aksesoris wanita yang dibangun dengan Laravel 13. **Seluruh alur belanja pelanggan (customer-facing) sudah selesai dan berfungsi** — dari registrasi, browse produk, keranjang, checkout dengan ongkir real-time, pembayaran Midtrans Snap, hingga riwayat pesanan dan ulasan. **Admin panel belum ada sama sekali** — bahkan halaman dashboard admin hanya menampilkan satu baris teks HTML statis. Tanpa admin panel, toko belum bisa beroperasi secara mandiri karena pengelolaan produk, pesanan, dan voucher hanya bisa dilakukan melalui database langsung.

---

## ✅ Sudah Selesai dan Berfungsi

### Customer-Facing
- Registrasi, login, logout, reset password (Laravel Breeze)
- Profil pengguna (edit nama, email, password)
- Buku alamat pengiriman (CRUD + set default, terintegrasi province_id/city_id RajaOngkir)
- Homepage dengan 3 section dinamis: New Arrivals, Produk Terpopuler, Sedang Diskon
- Katalog produk dengan filter (kategori, harga, pencarian) dan 4 opsi sort
- Halaman detail produk dengan galeri gambar, varian, ulasan, dan produk terkait
- Keranjang belanja (tambah/update/hapus, validasi stok, variant-aware)
- Wishlist (toggle, halaman daftar, badge count)
- Bottom navigation bar (mobile) dengan 5 tab: Home, Search, Cart, Wishlist, Profile

### Checkout & Pembayaran
- Pilih alamat tersimpan atau isi manual
- Dropdown provinsi & kota dari RajaOngkir Komerce API (sorted A-Z, cached 7 hari)
- Pilihan 4 kurir di frontend: JNE, J&T Express, POS Indonesia, TIKI
- Kalkulasi ongkir real-time berdasarkan berat aktual produk
- Penerapan voucher diskon (validasi server-side lengkap dengan pesan error spesifik)
- Pembayaran Midtrans Snap (10 metode: CC, VA BCA/BNI/BRI, GoPay, ShopeePay, QRIS, Indomaret, Alfamart)
- Webhook Midtrans dengan verifikasi signature SHA-512
- Stok otomatis dikurangi saat pembayaran berhasil, dipulihkan saat dibatalkan

### Pesanan & Ulasan
- Riwayat pesanan (paginated) + detail pesanan lengkap
- Pembatalan pesanan mandiri (status pending saja)
- Ulasan produk (rating 1-5 + komentar + foto) — terikat order item, Gate-protected
- ReviewObserver otomatis menghitung ulang rating_avg/rating_count di tabel products

---

## ⚠️ Ada Tapi Belum Sempurna (Technical Debt Aktif)

- **J&T Express (jnt) gagal saat bayar** — Frontend menampilkan J&T sebagai opsi kurir, tapi `CheckoutController::createSnapToken()` hanya menerima `jne,pos,tiki` (jnt TIDAK ada dalam validasi). Order dengan J&T akan ditolak saat klik Bayar. **Impact: pelanggan yang pilih J&T tidak bisa checkout.**

- **Ongkir tidak divalidasi ulang server-side** — Nilai `shipping_cost` dari frontend diterima apa adanya (`max(0, (int) $request->input('shipping_cost', 0))`). Client bisa manipulasi untuk gratis ongkir. **Impact: potensi kerugian finansial.**

- **Kurir pilihan pelanggan tidak tersimpan ke shipment** — Saat pembayaran berhasil, webhook membuat record shipment dengan `courier = 'Manual'`. Admin tidak tahu kurir mana yang dipilih pelanggan. **Impact: admin harus tanya pelanggan ulang.**

- **`users.is_active` tidak di-enforce** — Kolom ada tapi tidak ada pengecekan saat login. User yang dinonaktifkan tetap bisa masuk. **Impact: tidak bisa ban user.**

- **`MustVerifyEmail` di-comment** — Controller verifikasi email ada (Breeze default) tapi tidak aktif karena interface di-comment di User.php. **Impact: siapapun bisa daftar dengan email palsu.**

- **Berat produk fallback logic** — Produk dengan `weight = 0` dihitung 0 gram (bukan 100g fallback), menyebabkan ongkir terlalu murah. **Impact: potential underpay ongkir untuk produk tanpa weight.**

- **Komentar TODO sisa** di MidtransWebhookController — tidak merusak fungsional, tapi misleading.

---

## 🎨 Baru Mockup/Prototype (Belum Kode Laravel Asli)

- **Dashboard Admin** — Route `/admin/dashboard` ada, tapi hanya return: `<h1>Admin Dashboard (Placeholder)</h1><p>Selamat datang di Dashboard Admin el Craft.</p>`. Tidak ada controller, model, view, atau logic statistik apapun.

> Selain placeholder ini, **tidak ada UI admin lainnya** — tidak ada views admin untuk produk, pesanan, voucher, ulasan, atau pengguna. Direktori `resources/views/admin/` tidak ada.

---

## 📋 Direncanakan Tapi Belum Dikerjakan Sama Sekali

- Admin: Manajemen Produk (CRUD + varian + gambar + diskon)
- Admin: Manajemen Pesanan (list, detail, update status, isi resi)
- Admin: Dashboard dengan statistik penjualan
- Admin: Moderasi Ulasan (approve/reject)
- Admin: Manajemen Voucher (CRUD)
- Admin: Manajemen Kategori (CRUD)
- Admin: Manajemen Pengguna (list, nonaktifkan)
- Admin: Manajemen Banner Homepage (tabel `banners` belum ada)
- Admin: Retur/Refund (tidak ada `ReturnController` — dikonfirmasi tidak pernah ada)
- Notifikasi Email (tidak ada `app/Mail/` atau `app/Notifications/`)
- Login Google OAuth (kolom `google_id` ada, Socialite tidak terinstall)
- Upload avatar profil (kolom `avatar` ada, logic upload tidak ada)

---

## 🚧 Blocker atau Hal yang Butuh Keputusan Abril

1. **J&T Express**: Apakah J&T harus tetap ditawarkan ke pelanggan? Jika ya, `CheckoutController` harus di-fix SEGERA (tambah `jnt` ke validasi). Jika tidak, hapus dari frontend. **Ini bug aktif yang memblokir checkout.**

2. **Kurir mana yang sebenarnya ingin ditawarkan?** Akun RajaOngkir Komerce kemungkinan mendukung lebih banyak kurir (SiCepat, AnterAja, Ninja, dll.). Abril perlu cek dashboard Komerce di `rajaongkir.komerce.id` untuk melihat kurir apa saja yang tersedia, lalu tentukan mana yang mau diaktifkan.

3. **Prioritas: admin panel atau email dulu?** Tanpa admin panel, produk/pesanan/voucher hanya bisa dikelola via phpMyAdmin/Tinker. Tanpa email, pelanggan tidak dapat konfirmasi pembayaran. Keduanya kritis — perlu keputusan mana yang dikerjakan duluan.

4. **Midtrans mode**: Saat ini masih **Sandbox** (`MIDTRANS_IS_PRODUCTION=false`). Kapan rencana go-live? Perlu isi `MIDTRANS_SERVER_KEY_PRODUCTION` dan `MIDTRANS_CLIENT_KEY_PRODUCTION` sebelum production.

5. **Origin city**: Saat ini di-set ke city ID `256` (Jember). Apakah ini benar?

---

## 🎯 Next Recommended Step

**Fix bug J&T checkout (15 menit) → lalu mulai Admin Panel Pesanan (A5).**

Alasan:
- Bug J&T adalah **bug aktif yang memblokir checkout** — fix-nya sepele (tambah `'jnt'` ke validasi di CheckoutController). Harus dilakukan segera.
- Setelah itu, Admin Manajemen Pesanan (A5) adalah fitur admin yang paling mendesak karena pesanan sudah bisa masuk dari sisi pelanggan, tapi admin tidak punya cara untuk memprosesnya (update status, isi resi, dll.). Ini blocker operasional terbesar.

---

*Laporan ini dihasilkan dari investigasi langsung terhadap source code, bukan ringkasan percakapan.*
