# Laporan Perbaikan Pra-Deploy — elcraft.id

- **Tanggal:** 2026-10-16
- **Baseline:** commit `32ff969` (main, sudah ter-push)
- **Sumber:** review read-only 4 reviewer paralel (pembayaran/checkout, auth/keamanan, lifecycle pesanan/stok/retur, ongkir/voucher/storefront/email); ±140 file dibaca; 42+ tes terkait dijalankan ulang (hijau); suite penuh **665/665 lulus**.
- **Tujuan laporan:** daftar perbaikan yang dikonfirmasi sebelum eksekusi, agar tidak ada perbaikan ganda antar sesi AI (sesi Claude menyebutkan sudah ada rencana perbaikan "cache kegagalan RajaOngkir" — itu sama dengan **FIX-C3** di bawah; mohon konfirmasi siapa yang mengerjakan).

---

## Ringkasan eksekutif

Fondasi project **solid**: tidak ada IDOR, 52 route admin semuanya terproteksi, XSS/SQLi/upload bersih, harga & ongkir dihitung server-side, pembulatan rupiah lengkap & dites, reserve-stok anti double-restore, pre-hijack Google OAuth tertutup. Namun ditemukan **5 blokiran live** yang menjadi jalan konkret kehilangan uang tanpa jejak, plus **~20 perbaikan penting** dan ~25 minor. Semua reviewer sepakat: **belum siap live** sampai blokiran diperbaiki.

---

## Kondisi yang SUDAH baik (tidak perlu diubah)

- IDOR: semua resource pelanggan di-scope lewat pemilik (`OrderController.php:43-47,64-67`, `ReturnController.php:41-45`, Policy alamat/review).
- Admin gate: `route:list` membuktikan 52 route `admin/*` semuanya `web,auth,admin`.
- Harga: ongkir diverifikasi ulang server (`CheckoutController.php:150-180,485-531`), payload Midtrans dijaga seimbang (`MidtransService.php:96-109`), pembulatan komprehensif (`CheckoutRoundingTest`, 9 skenario).
- Stok: reserve-at-checkout dengan `lockForUpdate` (`StockService.php:33-62`); semua jalur cancel membungkus status+restore dalam transaksi + guard status (kecuali satu pengecualian — lihat FIX-I5).
- Webhook: signature di-hash sesuai spesifikasi + `hash_equals`; idempotensi & out-of-order diuji (`WebhookOutOfOrderTest`, `WebhookIdempotencyTest`).
- Email gagal selalu try/catch, tidak memutus alur.
- Retur: guard kuantitas per item, `return_number` unik dipakai sebagai refund_key idempoten.

---

## BLOKIRAN LIVE (Critical — wajib sebelum go-live)

### FIX-C1 — Webhook Midtrans gagal-terbuka saat server key kosong
- **Lokasi:** `app/Services/MidtransService.php:169-179` (`verifySignature`), `config/midtrans.php:20,32` (default `''`), `app/Http/Controllers/MidtransWebhookController.php:47-60`.
- **Masalah:** key kosong → signature = hash yang bisa dihitung penyerang; field hilang di payload → `ErrorException` → 500 (bukan 403), memicu retry Midtrans.
- **Dampak:** POST `/midtrans/webhook` palsu → order `paid`, stok terpotong, email sukses terkirim — **barang dikirim, uang tidak masuk**. Pemicunya justru momen transisi sandbox→production.
- **Perbaikan:** `if ($this->serverKey === '') { Log::critical(...); return false; }`; guard `array_key_exists` field wajib → balas 403; opsional boot-time assert agar deploy gagal cepat bila key belum terisi.
- **Test (RED dulu):** webhook settlement dengan key kosong harus ditolak (bukan `markAsPaid`); payload tanpa `gross_amount` → 403.
- **Ukuran:** S

### FIX-C2 — Settlement telat saat order sedang dibatalkan hilang senyap (TOCTOU)
- **Lokasi:** `app/Http/Controllers/MidtransWebhookController.php:97-121` (baca status tanpa lock), `app/Services/PaymentSettlementService.php:47-50` (`markOrderPaid` return `false` senyap).
- **Masalah:** urutan: webhook baca `pending` → proses cancel commit `dibatalkan` → `markOrderPaid` lihat `dibatalkan`, return false → webhook balas 200 (Midtrans berhenti retry). Tidak ada auto-refund; `payment` tetap failed; **tidak pernah muncul di filter "Dana Tertangkap"** (`Admin/OrderController.php:44-46`).
- **Dampak:** uang pembeli sudah tertangkap di Midtrans, hilang tanpa jejak (hanya 1 baris warning).
- **Perbaikan:** `markOrderPaid` mengembalikan hasil di bawah `lockForUpdate` (`'paid'|'already_paid'|'order_cancelled'`); pemanggil pada `order_cancelled` → `refundLateSettlement()` + `Log::critical`. Test: cancel commit di antara baca dan lock (mock urutan lock) → auto-refund terpicu.
- **Test:** skenario race yang tidak dicakup test sequential saat ini; test `RefundWebhookTest` yang ada tidak melintasi jendela ini.
- **Ukuran:** M

### FIX-C3 — Cache `Cache::remember` mempermanenkan hasil gagal API ongkir
- **Lokasi:** `app/Services/ShippingService.php:54-81` (provinces, TTL 7 hari), `:91-122` (cities), `:162-201` (cost, TTL 1 jam).
- **Masalah:** pada HTTP gagal, closure `return []` — dan `[]` ikut tersimpan di cache (hanya `null` yang tidak di-cache).
- **Dampak:** 1× Komerce timeout di shared hosting → provinsi 503 **7 hari**, cek ongkir kosong 1 jam per kombinasi — checkout lumpuh tanpa error jelas di log operasional.
- **Perbaikan:** jangan cache hasil gagal — return `null` dari closure agar `Cache::remember` tidak menyimpan, atau `Cache::forget` + return `[]`; hanya cache bila `$response->successful()` dan hasil non-kosong.
- **⚠️ Koordinasi:** sesi Claude menyebut prompt "perbaikan cache kegagalan RajaOngkir" — konfirmasi siapa mengerjakan agar tidak duplikat.
- **Test:** HTTP gagal → hasil `[]` dan **tidak** tersimpan (panggilan kedua saat API pulih langsung sukses tanpa tunggu TTL).
- **Ukuran:** S

### FIX-C4 — Default base URL RajaOngkir masih Starter lama (fallback Komerce = dead code)
- **Lokasi:** `config/rajaongkir.php:5` (default `https://api.rajaongkir.com/starter`), `.env.example:80`, `app/Services/ShippingService.php:36` (fallback Komerce tak pernah terpakai karena `config()` selalu non-null).
- **Masalah:** kode memanggil endpoint skema Komerce (`/destination/province`, response `data[]`); URL Starter lama → semua panggilan gagal — dikombinasikan FIX-C3, gagalnya di-cache.
- **Dampak:** deploy yang menyalin `.env.example` tanpa mengisi `RAJAONGKIR_BASE_URL` → ongkir mati total.
- **Perbaikan:** default config → `https://rajaongkir.komerce.id/api/v1`; perbaiki `.env.example`; log warning bila base_url masih berisi `rajaongkir.com/starter`.
- **Test:** `config('rajaongkir.base_url')` default = URL Komerce.
- **Ukuran:** XS

### FIX-C5 — Nominal pembayaran tidak pernah dicocokkan; log memuat `signature_key` utuh
- **Lokasi:** `app/Http/Controllers/MidtransWebhookController.php:114-117` (tanpa cek amount), `:259` (log `$notification` utuh), `MidtransService.php:169-179` (signature tidak mencakup `transaction_status` — keterbatasan algoritma Midtrans).
- **Masalah:** order bisa `paid` meski `gross_amount` ≠ `total_amount`; payload lengkap (signature_key + PII) di log → bocor log = bisa replay pending→settlement.
- **Dampak:** salah nominal lolos senyap; bahan bakar replay dari kebocoran log.
- **Perbaikan:** sebelum `markOrderPaid`, `(int) $notification['gross_amount'] === (int) $payment->amount` — beda → `Log::critical` + masuk review admin, jangan set lunas otomatis; berhenti log payload penuh (cukup `order_id`, `transaction_status`, `payment_type`, `status_code`); untuk `settlement`/`capture` pertama, re-verify via `getTransactionStatus()` (server-to-server).
- **Test:** notifikasi dengan amount ≠ total order → tidak `markAsPaid`; log assertion tidak memuat `signature_key`.
- **Ukuran:** M

---

## PERBAIKAN PENTING (kerugian rupiah/stok nyata)

### Keuangan & checkout
| ID | Temuan | Lokasi | Perbaikan | Ukuran |
|----|--------|--------|-----------|--------|
| FIX-I1 | Kuota voucher tidak di-reservasi saat checkout; `used_count` baru naik saat settlement → voucher quota=1 bisa dipakai N order pending bersamaan; toko serap selisih | `CheckoutController.php:258-265`, `PaymentSettlementService.php:96-115` | Reservasi di Txn A: `lockForUpdate` voucher + tulis `voucher_usages` (status reserved) + increment `used_count`; lepas saat order batal; `isValid` baca reserved | M |
| FIX-I2 | Dua request checkout paralel user sama → dobel pakai voucher (cek di luar transaksi) | `CheckoutController.php:228-261` | Recheck `isUsedBy` + lock voucher di dalam Txn A | S |
| FIX-I3 | `resumePayment` menelan semua exception jadi "kedaluwarsa"; cabang refresh token praktis mati | `OrderController.php:193-277` | Pisahkan respons `expired` vs `error` (`retryable: true`), log exception; izinkan refresh token lama kedaluwarsa | S |
| FIX-I4 | Pembatalan darurat checkout tidak atomik (satu-satunya cancel tanpa `DB::transaction`) → stok bisa hilang permanen bila restore gagal | `CheckoutController.php:441-456` | Bungkus update status + `restoreForOrder` dalam `DB::transaction` | S |
| FIX-I5 | Webhook `cancel` pada order `paid` memaksa `payment → failed` — beda dengan invariant admin (`cancelPaid` sengaja pertahankan `success` agar terlihat di Dana Tertangkap) | `MidtransWebhookController.php:308-326` vs `Admin/OrderController.php:264-269` | Samakan: `paid`+cancel → pertahankan `success`; arahkan ke Dana Tertangkap | S |
| FIX-I6 | Refund otomatis late-settlement: baca status di luar lock, refund_key `AUTO-{order}` berbeda kebijakan dari jalur lain → bisa ganda dengan refund manual bank; efek samping sembunyikan dari Dana Tertangkap | `MidtransWebhookController.php:161-191` | Klaim intent di bawah lock (`payment.auto_refunding_at`) sebelum HTTP; samakan kebijakan refund_key; `duplicate`/refunded jangan diam-diam menyembunyikan dari daftar | M |
| FIX-I7 | Baris `DISCOUNT` harga-negatif ke Snap belum pernah diuji nyata (semua test mock) | `CheckoutController.php:378-396` | Uji sandbox sungguhan saat Task uji live; bila ditolak: turunkan gross_amount tanpa baris negatif | checklist |

### Retur & refund
| ID | Temuan | Lokasi | Perbaikan | Ukuran |
|----|--------|--------|-----------|--------|
| FIX-I8 | `refund_amount=0` lolos validasi (`min:0`) → retur terkunci permanen (refund tolak, advanceStatus blokir, tak ada edit) | `Admin/ReturnController.php:83-89,180-182`, `RefundService.php:306` | `min:1` + endpoint edit nominal selama `refund_status === null` | S |
| FIX-I9 | Plafon refund = total pesanan, bukan nilai item yang diretur → retur 1/3 item bisa di-refund penuh | `Admin/ReturnController.php:83-88` | Hitung batas pro-rata dari qty item diretur; tampilkan batas di form; tolak di atasnya | M |
| FIX-I10 | Notifikasi refund Midtrans menutup retur tanpa rekonsiliasi nominal (parsial dari dashboard Midtrans → retur ditutup seolah penuh) | `MidtransWebhookController.php:200-227` | Cocokkan `refund_key === return_number` dan `gross_amount == refund_amount` sebelum tutup; tidak cocok → catat ke admin_notes, jangan tutup | S |

### Stok & lifecycle
| ID | Temuan | Lokasi | Perbaikan | Ukuran |
|----|--------|--------|-----------|--------|
| FIX-I11 | Varian dihapus → FK `nullOnDelete` → semua operasi stok nyasar ke kolom induk (oversell / agregat rusak / restore tanpa log) | migration `order_items`/`cart_items`, `Admin/ProductController.php:321-324`, `StockService.php:79-135` | Tolak hapus varian yang dirujuk order/cart (pola guard produk); `Log::warning` saat restore lewati record tak ditemukan | M |
| FIX-I12 | Tulis stok admin absolut tanpa lock → lost update; test suite sendiri menuliskan "BUKTI BOLONG" | `Admin/ProductController.php:251,301`, `Product.php:194-207`, `ConcurrencyRaceConditionAuditTest.php:76-113` | Baca-tulis di bawah `lockForUpdate` + delta snapshot; test "BUKTI BOLONG" wajib jadi hijau | M |
| FIX-I13 | Auto-complete prematur: UI `min:1`, command tanpa clamp (`(int)` mentah), tidak cek retur terbuka, hari dihitung dari `updated_at` tanpa memandu `shipped_at`/ETD | `Admin/OrderAutoCompleteController.php:34`, `AutoCompleteDeliveredOrders.php:50-79` | Clamp `max(1, min(30, $days))`; skip order dengan retur aktif; pertimbangkan floor `shipped_at + etd` | M |
| FIX-I14 | Auto-cancel memuat semua pending + 1-2 HTTP serial per order tanpa limit batch → bisa melampaui mutex 30 menit | `AutoCancelExpiredOrders.php:71-74` | `limit()`/chunking + timeout HTTP eksplisit | S |

### Auth & keamanan
| ID | Temuan | Lokasi | Perbaikan | Ukuran |
|----|--------|--------|-----------|--------|
| FIX-I15 | Open redirect via header `Referer` (`url()->previous()` tanpa validasi host) untuk seluruh route `guest.modal` | `RedirectGuestsWithModal.php:38,45` | Validasi `str_starts_with($fallback, url('/'))`, selain itu `route('home')` | S |
| FIX-I16 | Ganti/reset password tidak mengusir sesi lain (dengan `SESSION_DATABASE` sesi penyerang tetap hidup) | `PasswordController.php:23-25`, `NewPasswordController.php:46-49` | Hapus baris `sessions` lain milik user_id, atau `sessions_valid_from` + middleware | M |
| FIX-I17 | `SESSION_SECURE_COOKIE` tidak diset di `.env.example`/plan produksi → sesi bisa terkirim via http | `config/session.php:172`, `.env.example`, plan deploy | Tambah `SESSION_SECURE_COOKIE=true` ke `.env` produksi & template | XS |
| FIX-I18 | `POST /register` & `POST /forgot-password` tanpa rate limit per IP (bakar kuota Resend, spam users) | `routes/auth.php:20,41`; pola di `AppServiceProvider.php:67-77` | Limiter bernama 5/menit per IP | S |
| FIX-I19 | `$fillable` User memuat `role`, `is_active`, `email_verified_at`, `anonymized_at` — ranjau refactor jadi eskalasi privilge | `User.php:21-32` | Keluarkan dari `$fillable`; jalur tepercaya pakai `forceFill()` (pola `GoogleController.php:95` sudah benar) | S |
| FIX-I20 | Injeksi formula CSV pada ekspor laporan admin (nama/email pelanggan mentah) | `Admin/ReportController.php:166-179` | Prefix `'` pada sel yang diawali `=`, `+`, `-`, `@`, tab, CR | S |

### Ongkir, alamat & tampilan
| ID | Temuan | Lokasi | Perbaikan | Ukuran |
|----|--------|--------|-----------|--------|
| FIX-I21 | `/shipping/cost` CSRF-exempt + 30/menit/IP → kuota API Komerce bisa dibakar iseng (43rb panggilan/hari/IP); frontend sudah kirim CSRF token | `bootstrap/app.php:21-24`, `AppServiceProvider.php:67-69` | Hapus exemption CSRF, turunkan ke 10/menit + cap harian | S |
| FIX-I22 | `city_id` tidak divalidasi terhadap `province_id` → ongkir bisa salah arah (ongkir murah, kirim jauh) menanggung toko | `CheckoutController.php:112-126` | Resolve nama kota/provinsi server-side via `getCities()`; wajibkan `province_id`; simpan nama dari API | M |
| FIX-I23 | Formula berat tidak konsisten: index `?? 100`, verifikasi `?? 0` → estimasi tampil ≠ server | `CheckoutController.php:77-81` vs `:151-154` | Satu helper perhitungan berat dipakai kedua jalur | S |
| FIX-I24 | Checkout tidak meminta/menyimpan `province` & `postal_code` (selalu `''`) → data alamat tidak lengkap untuk ekspedisi | `CheckoutController.php:112-126,299` | Ambil nama provinsi dari resolve `province_id`; `province_id` + `postal_code` required; samakan dengan `AddressController` | M |
| FIX-I25 | Link CTA email PaymentSuccess pakai `config('app.url')` manual → mati bila APP_URL salah; email lain pakai `route()` | `resources/views/emails/payment-success.blade.php:174` | Ganti `route('orders.show', …)` | XS |
| FIX-I26 | `full_address` tanpa `max` → kolom 255, input panjang → 500 di dalam transaksi | `CheckoutController.php:116` | `max:255` (atau ubah kolom ke text) | XS |

---

## MINOR (tunda setelah live — tidak memblokir)

- Total bisa Rp0 (voucher menutup penuh) → guard `total >= 1` atau auto-paid (`CheckoutController.php:265`).
- `markAsFailed` paid+cancel → asimetri kebijakan (terkait FIX-I5).
- Retur `refund` sengaja **tidak restock** — lihat pertanyaan kebijakan di bawah.
- Finish page menampilkan "Pembayaran Berhasil!" hanya dari query param klien (`CheckoutController.php:540-546`) — ambil dari DB.
- `route('checkout/snap-token')` tanpa throttle; dobel-klik bisa buat 2 order pending (self-healing via auto-cancel).
- `voucher_usages` tanpa unique index (defense-in-depth).
- `ResumePaymentTest` skenario tertentu tanpa `Http::fake` → panggil sandbox nyata (flaky).
- `.env.testing` tidak set `APP_TIMEZONE` (UTC) — deployment lupa menulis WIB = hitungan hari meleset ±7 jam. Tambahkan ke `.env.testing` + checklist deploy.
- Default auto-complete tidak konsisten: dashboard `'3'` vs command `7` (`DashboardController.php:110`).
- `resolved_at` diisi saat approve, bukan selesai (`Admin/ReturnController.php:103`).
- Race pengajuan retur ganda → 500 kasar (tangkap QueryException).
- Tracking number: race dua admin bisa kirim tanpa resi (`Admin/OrderController.php:146-155`).
- Payload respons Midtrans & data pelanggan di log (`MidtransService.php:118-122`).
- Enumerasi akun di lupa password; throttle login hanya per-`email` bukan per-IP; hapus akun Google tanpa re-auth (`ProfileController.php:55-61`).
- Wildcard LIKE admin tidak di-escape (`GlobalSearchController.php:42-46`).
- Dead code open-redirect di `AuthenticatedSessionController.php:18-24` (tidak ter-route) — hapus.
- `APP_KEY` tes ter-commit di `.env.testing` repo publik — risiko rendah (produksi generate key baru).
- `X-Forwarded-Proto` dipercaya semua klien di `AppServiceProvider.php:33-35` — tidak konsisten dengan trustProxies loopback.
- Duplikasi `formatRupiah` di banyak blade; email dikirim sinkron (pertimbangkan queue di VPS nanti); cache ongkir tidak memuat hash aturan diskon; `min_purchase` voucher dibanding subtotal saja (komunikasikan di UI).

---

## Pertanyaan kebijakan bisnis (butuh jawaban sebelum/fixed bersamaan)

1. **Retur `resolution_type=refund` tidak me-restock stok** (dites sengaja: `ReturnStockRestoreTest::test_refund_return_completion_does_not_restock`). Barang fisik kembali ke gudang — apakah stok memang sengaja tidak ditambah? (Jika ya, tandai komentar kode; jika tidak, FIX di transisi `selesai`.)
2. **Auto-complete threshold**: minimal berapa hari setelah dikirim layak otomatis selesai? (Saat ini `min:1`; usulan minimal 3–7 hari agar pembeli masih punya jendela konfirmasi/sengketa.)
3. **Order Rp0** (voucher menutup penuh + ongkir 0): langsung dianggap lunas tanpa Snap, atau tolak voucher yang membuat total 0?
4. **Voucher `min_purchase`**: dihitung subtotal produk saja (sekarang) atau subtotal+ongkir? Perlu dikomunikasikan di deskripsi voucher.
5. **Refund parsial dari dashboard Midtrans** (bukan lewat UI retur): tangani otomatis match nominal, atau selalu masuk review admin manual?

---

## Protokol pengerjaan (untuk kedua sesi AI)

1. **TDD:** setiap fix ditutupi test merah dulu bila memungkinkan; `php artisan test` wajib hijau (baseline 665).
2. **Satu fix = satu commit** gaya repo (`fix(midtrans): …`, `fix(shipping): …`).
3. **Tidak push / tidak commit** tanpa instruksi eksplisit pemilik repo.
4. **Jangan** menampilkan/menyalin secret (key Resend tersimpan terpisah di luar repo; `.env` lokal hanya dibaca bila diizinkan).
5. **Koordinasi:** FIX-C3 kemungkinan sedang dikerjakan sesi Claude — konfirmasi pemilik sebelum mulai agar tidak duplikat/menabrak.
6. **Prioritas eksekusi:** C1 → C5 → C2 → C4 → C3 → I1 → I8/I9/I10 → I11/I12 → I15/I16 → sisanya.

## Definisi selesai

- Seluruh FIX-C1…C5 dan FIX-I1…I26 terpasang (atau dikarantina dengan alasan tertulis bila ditolak pemilik).
- Test baru untuk setiap fix lulus; suite penuh hijau (≥665).
- Laporan akhir: daftar commit + perubahan perilaku yang perlu diketahui pemilik toko.

PS C:\laragon\www\elcraft> (git show HEAD:.env.testing) | Where-Object { $_ -match '^(\w+)=(.+)$' } | ForEach-Object { $Matches[1] }
APP_NAME
APP_ENV
APP_KEY
APP_DEBUG
APP_URL
BCRYPT_ROUNDS
CACHE_STORE
SESSION_DRIVER
QUEUE_CONNECTION
MAIL_MAILER
DB_CONNECTION
DB_DATABASE
DB_URL
PS C:\laragon\www\elcraft> 
PS C:\laragon\www\elcraft> $t = ((git show HEAD:.env.testing) | Select-String '^APP_KEY=(.+)$').Matches.Groups[1].Value
>> $e = ((Get-Content .env) | Select-String '^APP_KEY=(.+)$').Matches.Groups[1].Value
>> "APP_KEY tes sama dengan .env asli? " + ($t -eq $e)
>> "DB_URL berisi kredensial (@)? " + ((git show HEAD:.env.testing | Select-String '^DB_URL=').Line -match '@')
>> (git show HEAD:.env.testing) | Select-String '^(APP_ENV|APP_DEBUG|CACHE_STORE|SESSION_DRIVER|QUEUE_CONNECTION|MAIL_MAILER|DB_CONNECTION|DB_DATABASE)=' | ForEach-Object { $_.Line }
APP_KEY tes sama dengan .env asli? True
DB_URL berisi kredensial (@)? False
APP_ENV=testing
APP_DEBUG=true
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
PS C:\laragon\www\elcraft> 
