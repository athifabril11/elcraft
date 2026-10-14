# Desain: Email Verifikasi Akun via Resend di Shared Hosting

*Tanggal: 2026-10-14 | Status: Disetujui | Repo: athifabril11/elcraft*

---

## 1. Ringkasan

Fitur verifikasi email akun **sudah ada lengkap** di repository ini. Yang belum ada adalah sambungan ke penyedia email yang benar-benar mengantarkan pesan ke inbox pengguna, serta fondasi hosting produksi supaya seluruh alur (verifikasi, webhook Midtrans, cron order, OAuth Google) bisa berjalan.

Desain ini menutup dua celah itu: menghubungkan aplikasi ke **Resend SMTP**, dan menyiapkan **deploy ke shared hosting Niagahoster** untuk domain `elcraft.id`, lengkap dengan perbaikan kecil pada penanganan kegagalan pengiriman email.

## 2. Konteks & temuan

### 2.1 Yang sudah ada (tidak diubah)

| Bagian | Lokasi |
|---|---|
| Route verifikasi | `routes/auth.php:60-68` — `verification.notice`, `verification.verify`, `verification.send` |
| Kontroler verifikasi | `app/Http/Controllers/Auth/VerifyEmailController.php`, `EmailVerificationPromptController.php`, `EmailVerificationNotificationController.php` |
| Konten email | `resources/views/emails/verify-email.blade.php` |
| Subject & view email | `app/Providers/AppServiceProvider.php:52` (`VerifyEmail::toMailUsing`) |
| Kontrak verifikasi | `app/Models/User.php` — `implements MustVerifyEmail` |
| Pengguna Google otomatis terverifikasi | `app/Http/Controllers/Auth/GoogleController.php:78` |
| Email transaksional (7 jenis) | `PaymentSuccessMail`, `OrderShippedMail`, `OrderCompletedMail`, `OrderCancelledMail`, `ReturnStatusMail` |
| Jadwal cron | `bootstrap/app.php:47-56` — `orders:auto-complete` (harian), `orders:auto-cancel-expired` (per jam) |
| Tes | `CustomVerifyEmailTest`, `EmailVerificationTest`, `RegistrationTest`, `GoogleLoginTest`, dan tes email transaksional lainnya |

### 2.2 Yang belum ada

1. Package dan konfigurasi untuk mengirim ke Resend (transport `smtp` menunjuk ke Mailtrap sandbox).
2. `APP_URL` menunjuk `http://localhost:8000`, sehingga link verifikasi di email membuka localhost komputer pengguna.
3. Domain `elcraft.id` belum dibeli, hosting belum aktif, domain belum diverifikasi di Resend.
4. Cron `schedule:run` belum dipasang di mana pun.
5. Perlindungan kegagalan pengiriman pada tiga jalur email (lihat §6).

### 2.3 Dua temuan yang mengubah keputusan desain

**a. Resend tanpa domain terverifikasi tidak bisa dipakai di produksi.**
Resend hanya mengizinkan pengiriman dari `onboarding@resend.dev`, dan itu pun hanya boleh dikirim ke alamat email yang terdaftar di akun Resend pengirim. Alamat lain dibalas `403`. Verifikasi domain (record DNS SPF + DKIM) adalah prasyarat mutlak.

**b. hPanel tidak mengizinkan mengubah document root pada shared hosting.**
Dokumen resmi Hostinger menyatakan halaman root tidak dapat diubah untuk paket Web, WordPress, dan Cloud. Niagahoster memakai hPanel yang sama (satu grup dengan Hostinger sejak 2019). Laravel membutuhkan `public/` sebagai document root, sehingga pendekatan penempatan file harus dipilih secara sengaja (lihat §5.2).

## 3. Lingkup

### 3.1 Termasuk

1. Pembelian domain `elcraft.id` dan paket shared hosting Niagahoster dengan akses SSH.
2. Verifikasi domain `elcraft.id` di Resend (DNS SPF + DKIM).
3. Konfigurasi SMTP Resend pada `.env` produksi dan penyelarasan `.env.example`.
4. Deploy aplikasi ke shared hosting melalui SSH + Git.
5. Pemasangan cron `schedule:run`.
6. Perlindungan kegagalan pengiriman pada tiga jalur email (registrasi, kirim-ulang verifikasi, lupa password) + banner status gagal + tes.
7. Pembaruan pengaturan luar: Notification URL Midtrans dan Authorized redirect URI Google.

### 3.2 Tidak termasuk

- Memindahkan email ke antrean (queue). Ditolak: di shared hosting, worker queue harus hidup terus-menerus lewat cron, boros resource, dan rawan putus.
- Menambah jenis email baru (salam, promo, dsb.).
- VPS atau managed Laravel hosting.
- Perubahan pada routes, kontroler verifikasi, isi email verifikasi, subject, maupun durasi kedaluwarsa link (tetap 60 menit).
- Perubahan pada migrasi database.

## 4. Keputusan desain

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Penyedia email | Resend | Permintaan pemilik proyek; free tier mencukupi |
| Protokol | **SMTP** (`smtp.resend.com:465`) | Tidak memerlukan `composer require resend/resend`; transport SMTP sudah bawaan Laravel. Mailer `resend` membutuhkan SDK Resend yang belum terpasang |
| Hosting | Niagahoster shared hosting | Server Jakarta, hPanel, dukungan bahasa Indonesia, sesuai keterbatasan pengalaman server pemilik proyek |
| Domain | `elcraft.id` | Konsisten dengan `MAIL_FROM_ADDRESS` yang sudah dipakai dan identitas produk Indonesia |
| Metode deploy | SSH + Git | Update cepat tanpa mengunggah ulang `vendor/` |
| Penanganan kegagalan email | `try/catch` sinkron + log | Menyamai pola yang sudah dipakai di 7 titik lain di repository |
| Antrean email | Tidak dipakai | Alasan di §3.2 |

## 5. Arsitektur

### 5.1 Alur end-to-end

```
[Registrasi]
  RegisteredUserController::store
    → User::create                       (baris users terisi, belum verified)
    → event(new Registered($user))       (sinkron, pada request yang sama)
      → listener SendEmailVerificationNotification
        → VerifyEmail notification       (subject & view sudah dikustom)
        → SMTP smtp.resend.com:465
        → inbox pengguna

[Verifikasi]
  GET /email/verify/{id}/{hash}  — signed URL, berlaku 60 menit
    → users.email_verified_at terisi
    → redirect /?verified=1

[Checkout]
  middleware `verified` menolak bila belum verifikasi
```

### 5.2 Penempatan file di shared hosting

Karena hPanel tidak dapat mengubah document root, project ditempatkan **di luar** `public_html`, dan `public_html` dibuat sebagai symlink ke `public/` milik project:

```
/home/uXXXX/
├── elcraft/                        ← git clone di sini
│   ├── app/  bootstrap/  config/  database/  routes/
│   ├── resources/  storage/  vendor/  .env
│   └── public/                     ← direktori asli dari git
│
└── domains/elcraft.id/
    └── public_html  →  symlink ke /home/uXXXX/elcraft/public
```

`uXXXX` adalah username akun hosting, terlihat di hPanel → Files → FTP Accounts. Jalur aslinya berbentuk `/home/u12345678/domains/elcraft.id/public_html`; bila akun memakai satu domain utama saja, bisa juga `/home/u12345678/public_html`. Nilai pastinya dibaca dari panel, bukan ditebak.

Mengapa bukan cara yang lebih umum (rename `public` → `public_html`)? Karena `public_path()` Laravel menyelesaikan ke `basePath('public')`. Bila nama foldernya diganti, `php artisan storage:link` dan pembacaan manifest Vite pada `public/build` akan menunjuk ke path yang tidak ada, sehingga perlu symlink tambahan untuk memperbaikinya.

Dengan symlink pada document root, `public/index.php` yang memakai `__DIR__.'/../vendor/autoload.php'` (baris 14) dan `__DIR__.'/../bootstrap/app.php'` (baris 18) tetap benar **tanpa mengedit satu baris pun**.

Manfaat tambahan: `.env`, `storage/`, dan `vendor/` berada di luar root web sehingga tidak dapat terunduh walau konfigurasi server keliru.

**Cadangan** bila shared hosting menolak symlink pada document root: pindahkan seluruh project ke root domain, rename `public` menjadi `public_html`, lalu jalankan `ln -s public_html public` dari root project agar `public_path()` tetap akurat.

### 5.3 Konfigurasi email

`.env` produksi:

```ini
APP_URL=https://elcraft.id

MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=<API key Resend, diawali re_>
MAIL_FROM_ADDRESS=noreply@elcraft.id
MAIL_FROM_NAME="el Craft"
```

Yang dihapus dari konfigurasi yang ada: `MAIL_HOST=sandbox.smtp.mailtrap.io`, `MAIL_PORT=2525`, `MAIL_USERNAME` dan `MAIL_PASSWORD` Mailtrap, serta `MAIL_ENCRYPTION=tls`.

`MAIL_ENCRYPTION` dihapus karena sudah tidak dipakai Laravel 13. `config/mail.php:42` membaca `MAIL_SCHEME`, bukan `MAIL_ENCRYPTION`. `MAIL_SCHEME` sengaja dibiarkan kosong: `MailManager::createSmtpTransport` memilih skema `smtps` secara otomatis ketika port adalah 465.

Kredensial SMTP Resend (dokumen resmi Resend):

| Nilai | Keterangan |
|---|---|
| Host | `smtp.resend.com` |
| Port | `465` (SMTPS, SSL implisit) — atau `587` dengan STARTTLS |
| Username | `resend` (huruf kecil, literal) |
| Password | API key Resend |

`.env.example` disesuaikan bentuknya (host, port, username Resend, `MAIL_PASSWORD=re_xxx` sebagai isi contoh) **tanpa API key sungguhan**. Berkas `.env` tetap masuk `.gitignore`.

### 5.4 Jadwal cron

```
* * * * * php /home/uXXXX/elcraft/artisan schedule:run >> /dev/null 2>&1
```

Dipasang lewat hPanel → Cron Jobs. Perintah `schedule:run` berjalan tiap menit karena Laravel mengevaluasi jadwal di dalamnya.

Catatan: sebagian shared hosting membatasi interval minimal 15 menit. Bila kena batasan itu, `orders:auto-cancel-expired` (per jam) tetap berjalan namun menjadi tiap 15 menit — masih dapat diterima, tetapi harus dicatat sebagai penyimpangan dari niat awal.

## 6. Penanganan kegagalan

### 6.1 Pola yang sudah dipakai di repository

Ketujuh email transaksional memakai bentuk yang persis sama. Contoh dari `OrderController.php:149`:

```php
try {
    Mail::to($order->user->email)->send(new OrderCancelledMail($order, $event, $reason));
} catch (\Throwable $e) {
    Log::error('OrderCancelledMail gagal terkirim', [
        'order_number' => $order->order_number,
        'to_email'     => $order->user->email,
        'error'        => $e->getMessage(),
    ]);
}
```

Terdapat komentar eksplisit di `AutoCancelExpiredOrders.php:226`: *"Email — failure must never break the command loop."*

### 6.2 Tiga titik yang belum diproteksi

| # | Lokasi | Akibat bila Resend gagal |
|---|---|---|
| 1 | `RegisteredUserController.php:46` — `event(new Registered($user))` memicu listener sinkron milik Laravel tanpa `try/catch` | `Auth::login` di baris 48 tidak sempat dijalankan → respons 500. Baris `users` pada baris 40 sudah terinsert tanpa transaksi, sehingga pengguna terdaftar tetapi tidak dapat mendaftar ulang karena batasan unique pada email |
| 2 | `EmailVerificationNotificationController.php:21` | Tombol "kirim ulang" menghasilkan 500 |
| 3 | `PasswordResetLinkController.php:28` — `Password::sendResetLink()` | Halaman "lupa password" menghasilkan 500 |

### 6.3 Perilaku setelah perbaikan

```
Registrasi + pengiriman gagal
  → pengguna tetap terbuat dan tetap login
  → redirect ke verification.notice dengan flash 'verification-link-failed'
  → banner merah tampil
  → pengguna dapat menekan "kirim ulang" kemudian

Tombol kirim-ulang + pengiriman gagal
  → tidak menghasilkan 500
  → flash 'verification-link-failed', banner merah tampil

Lupa password + pengiriman gagal
  → tidak menghasilkan 500
  → kembali ke formulir dengan pesan kegagalan umum
```

Penting untuk jalur lupa password: `Password::sendResetLink()` hanya memanggil transport email bila alamat memang terdaftar. Untuk alamat yang tidak terdaftar, broker mengembalikan `USER_NOT_FOUND` tanpa mengirim apa pun. Karena itu percabangan status yang sudah ada di `PasswordResetLinkController::store` **tidak diubah sama sekali** — hanya satu blok `try/catch` yang membungkus pemanggilan `Password::sendResetLink()`, dengan pesan kegagalan umum pada cabang exception. Desain ini tidak memperkenalkan kanal pembocoran keberadaan akun yang baru, sebab jalur exception hanya tercapai saat transport email benar-benar rusak, bukan saat alamat tidak ditemukan.

Perubahan tampilan: `resources/views/auth/verify-email.blade.php` saat ini hanya memiliki cabang `session('status') == 'verification-link-sent'`. Ditambahkan satu cabang `@elseif` untuk status gagal. Flash `verification-link-failed` dipakai bersama oleh jalur registrasi dan jalur kirim-ulang, sehingga cukup satu cabang banner untuk dua kasus itu.

### 6.4 Konteks logging

Mengikuti pola yang sudah ada: `to_email`, `error` (pesan exception), dan penanda lokasi (`registration`, `resend_verification`, `password_reset`). Tanpa konteks ini, email yang hilang di produksi tidak akan terdeteksi — dari sisi pengguna hanya terasa "emailnya tidak sampai".

## 7. Deployment

### 7.1 Urutan cutover

Urutan ini wajib dan tidak boleh dibalik:

1. Beli domain `elcraft.id` dan paket shared hosting Niagahoster, lalu aktifkan SSH di hPanel. SSH tidak tersedia di semua tier — pastikan paket yang dipilih menyediakannya sebelum membayar, sebab seluruh alur deploy pada desain ini bergantung padanya. Selain itu pastikan PHP yang ditawarkan versi 8.3 atau lebih tinggi.
2. Siapkan struktur direktori dan symlink document root (§5.2).
3. `git clone` project ke `/home/uXXXX/elcraft`.
4. Buat `.env` produksi di server melalui SSH (jangan diunggah lewat File Manager).
5. `composer install --no-dev --optimize-autoloader`
6. `php artisan key:generate` bila `APP_KEY` kosong.
7. Buat database MySQL di hPanel, isi kredensial di `.env`, lalu `php artisan migrate --force`.
8. `php artisan storage:link` — aplikasi memakai `storage/app/public/{products,hero-banners,avatars,returns,reviews}`.
9. Build aset secara lokal (`npm run build`), lalu salin `public/build/` ke server (`scp` atau `rsync`). Node tidak tersedia di shared hosting dan `public/build` ada di `.gitignore`.
10. `php artisan optimize` (cache config, event, route, view — urutan ini diverifikasi berjalan pada basis kode ini, termasuk route closure di `routes/web.php:94` dan `routes/auth.php`).
11. Pasang cron `schedule:run` (§5.4).
12. Verifikasi domain `elcraft.id` di Resend → status harus `Verified`.
13. Set `MAIL_*` dan `APP_URL` di `.env` produksi, lalu `php artisan config:cache`.
14. Uji pengiriman email sungguhan (§8.2).
15. **Baru setelah ini**: ganti Notification URL di dashboard Midtrans dan Authorized redirect URI di Google Cloud Console.

Langkah 15 diletakkan paling akhir dengan sengaja, agar callback mengenai server yang sudah siap.

### 7.2 Pengaturan luar aplikasi

| Pengaturan | Nilai | Di mana |
|---|---|---|
| Notification URL Midtrans | `https://elcraft.id/midtrans/webhook` | Midtrans Dashboard → Settings → Configuration |
| Authorized redirect URI Google | `https://elcraft.id/auth/google/callback` | Google Cloud Console → Credentials → OAuth 2.0 Client ID |
| `GOOGLE_REDIRECT_URI` | `https://elcraft.id/auth/google/callback` | `.env` produksi |
| DNS Resend | 2 record TXT (SPF + DKIM) | Panel DNS Niagahoster |

Catatan: Google tidak mengizinkan redirect URI berupa `http://` kecuali `http://localhost`. HTTPS adalah syarat mutlak, bukan pilihan.

### 7.3 Jebakan config cache

`php artisan config:cache` membekukan isi `.env` ke berkas cache. Mengubah `.env` tanpa menjalankan ulang perintah ini berarti perubahan tidak pernah terbaca — penyebab paling umum dari gejala "berhasil di lokal, gagal di server". Setiap perubahan `.env` di produksi harus diikuti `php artisan config:cache`.

## 8. Verifikasi & pengujian

### 8.1 Kriteria diterima

| # | Pengujian | Tanda berhasil |
|---|---|---|
| 1 | Daftar akun baru | Email verifikasi masuk **inbox** (bukan folder spam); tombol membuka `https://elcraft.id` |
| 2 | Klik link verifikasi | `users.email_verified_at` terisi; redirect ke `/?verified=1` |
| 3 | Belum verifikasi lalu mencoba checkout | Ditolak middleware `verified` |
| 4 | Tombol "kirim ulang" | Email kedua terkirim; banner hijau tampil |
| 5 | Login Google | Lolos tanpa email verifikasi (`GoogleController.php:78`) |
| 6 | Pembayaran Midtrans sandbox | Webhook diterima; `PaymentSuccessMail` terkirim |
| 7 | Cron | `orders:auto-cancel-expired` muncul di log tiap jam |
| 8 | Resend gagal (API key dicoret sementara) | Registrasi **tetap berhasil**; banner merah tampil; `laravel.log` memuat konteks error |

Pengujian nomor 8 adalah inti dari pendekatan ini. Tanpa itu, perbaikan pada §6 tidak terbukti bekerja.

### 8.2 Uji pengiriman nyata

Melalui `php artisan tinker` di server:

```php
$user = \App\Models\User::where('email', '<alamat email pemilik akun Resend>')->first();
$user->sendEmailVerificationNotification();
```

Catatan: pada paket gratis, pengiriman hanya boleh dilakukan dari domain yang sudah diverifikasi ke alamat mana pun. Selama domain `elcraft.id` belum `Verified`, pengiriman dibalas `403`.

### 8.3 Pengujian otomatis

Empat hal diverifikasi:

1. Registrasi tetap **berhasil** (pengguna terdaftar, sudah login, pengalihan benar) meskipun transport email melempar exception.
2. Endpoint kirim-ulang tidak menghasilkan 500 dan menampilkan status gagal.
3. Halaman "lupa password" tidak menghasilkan 500 bila pengiriman gagal, dan percabangan status yang sudah ada tidak berubah.
4. Tes yang sudah ada — `CustomVerifyEmailTest`, `RegistrationTest`, `EmailVerificationTest`, `GoogleLoginTest`, serta tes email transaksional — **tetap hijau**.

Untuk memaksa exception, pengujian menukar mailer dengan transport yang melempar kesalahan. `Notification::fake()` tidak dipakai untuk tujuan ini karena ia justru mencegah pengiriman sama sekali sehingga tidak pernah memicu jalur kegagalan.

### 8.4 Monitoring setelah live

- **Resend Dashboard → Logs**: status `delivered`, `bounced`, `complained` per email. Alamat yang memantul otomatis masuk suppressed list.
- **`storage/logs/laravel.log`**: saring kata `gagal terkirim` untuk menangkap kegagalan yang tertangkap `try/catch`.
- **Batas paket gratis Resend**: 100 email per hari (hari kalender UTC), 3.000 email per bulan, maksimum 3 domain terverifikasi. Saat kuota terlampaui Resend membalas `429`; error ini tertangkap `try/catch` sehingga terlihat di log, bukan hilang diam-diam.

### 8.5 Rollback

Beri tag git sebelum deploy pertama dan simpan salinan `.env` produksi di luar repository. Rollback: `git checkout <tag>` → pulihkan `.env` → `php artisan optimize`. Basis data tidak ikut di-rollback karena tidak ada migrasi destruktif dalam lingkup ini.

## 9. Risiko & mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Domain belum diverifikasi di Resend | Semua email dibalas `403` | Verifikasi DNS pada langkah 12 sebelum mengaktifkan `MAIL_*` di produksi |
| `APP_URL` salah | Link verifikasi dan callback pembayaran mengarah ke localhost | `APP_URL=https://elcraft.id`, lalu `config:cache` |
| Cron tidak terpasang | `orders:auto-cancel-expired` dan `orders:auto-complete` tidak jalan; order menumpuk | Langkah 11 pada §7.1; verifikasi lewat langkah pengujian nomor 7 |
| Kuota harian Resend terlampaui | Email berhenti terkirim hari itu | Pantau Dashboard Resend; naik ke Pro bila lolos 100/hari |
| hPanel menolak symlink document root | Deploy gagal pada langkah struktur direktori | Gunakan cadangan pada §5.2 |
| PHP di hosting lebih rendah dari 8.3 | Aplikasi tidak jalan sama sekali | Pastikan paket hosting menyediakan PHP 8.3 atau lebih tinggi |
| Notification URL Midtrans belum diganti | Webhook tidak pernah tiba | Langkah 15 pada §7.1; jalur cadangan: cron per jam melakukan rekonsiliasi via API Midtrans (`MidtransService.php:292`) |

## 10. Anggaran kasar

| Pos | Kisaran |
|---|---|
| Domain `.id` tahun pertama | Rp 210.000 – 250.000 |
| Shared hosting Niagahoster (promo tahunan) | ± Rp 59.000/bulan, tahun kedua bisa naik 2–3× |
| Resend paket gratis | Rp 0 (3.000 email/bulan) |

Tahun pertama diperkirakan Rp 800.000 – 1.500.000. Harga promo hosting umumnya hanya berlaku tahun pertama; lonjakan perpanjangan adalah hal yang wajar.
