# Email Verifikasi Resend + Shared Hosting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghubungkan fitur verifikasi email akun yang sudah ada ke Resend SMTP, menutup tiga celah kegagalan pengiriman yang bisa menggagalkan aksi pengguna, dan menyiapkan deploy ke shared hosting Niagahoster untuk `elcraft.id`.

**Architecture:** Fitur verifikasi email sudah lengkap di repository (routes, kontroler, view, subject, tes). Perubahan kode dibatasi pada tiga titik yang membungkus pengiriman dengan `try/catch` + log — mengikuti pola yang sudah dipakai di tujuh email transaksional lain — ditambah satu cabang banner status gagal. Pengiriman memakai transport SMTP bawaan Laravel ke `smtp.resend.com:465`; tidak ada package tambahan. Deployment memakai SSH + Git dengan project di luar root web dan `public_html` sebagai symlink ke `public/`.

**Tech Stack:** Laravel 13 (PHP 8.3+), Laravel Breeze auth, Resend SMTP (port 465, SSL implisit), Vite + Tailwind, PHPUnit 12, shared hosting Niagahoster (hPanel).

**Spec:** `docs/superpowers/specs/2026-10-14-resend-shared-hosting-design.md`

---

## Global Constraints

Nilai-nilai berikut diambil verbatim dari spec dan berlaku di setiap task:

- **Floor PHP:** 8.3 (`composer.json` menuntut `^8.3`). Jangan gunakan sintaks yang hanya tersedia di atasnya.
- **Aturan kegagalan email:** kegagalan pengiriman email tidak boleh menggagalkan aksi utama apa pun (spec §6.1).
- **Format log:** `Log::error('<Label> gagal terkirim', ['to_email' => ..., 'error' => $e->getMessage()])`. Tambahkan penanda lokasi sesuai task.
- **Flash:** kunci `session` adalah `status`; nilai yang sah `verification-link-sent` dan `verification-link-failed`.
- **Resend SMTP:** host `smtp.resend.com`, port `465`, username `resend` (huruf kecil, literal), password adalah API key yang diawali `re_`.
- **Jangan pasang `composer require resend/resend`.** Transport SMTP bawaan sudah cukup.
- **`MAIL_SCHEME` dibiarkan kosong.** `MailManager::createSmtpTransport` memilih `smtps` otomatis untuk port 465.
- **`MAIL_ENCRYPTION` dihapus.** Tidak ada kode PHP yang membacanya (hanya `config/mail.php:42` yang membaca `MAIL_SCHEME`).
- **`APP_URL` produksi** harus `https://elcraft.id`.
- **Jangan commit API key sungguhan** maupun kredensial hosting.
- **Tidak diubah:** routes, kontroler verifikasi, isi email verifikasi, subject, maupun durasi kedaluwarsa link (tetap 60 menit).
- **Jangan sentuh** `tests/Feature/UnexpectedCustomerBehaviorTest.php` — berkas itu bukan bagian dari pekerjaan ini.

## Review Focus

Lima hal paling mungkin menggigit yang tidak sepenuhnya dijangkau tes per task:

1. **Kontrak JSON registrasi berubah diam-diam.** Frontend modal Alpine.js membaca `success` dan `redirect`. Bila penambahan kegagalan memunculkan field baru atau mengubah bentuk respons, modal bisa gagal navigasi. *Tes:* `test_ajax_registration_with_valid_data_returns_json_success_and_logs_in` di `tests/Feature/Auth/RegistrationTest.php` harus tetap hijau tanpa diedit.
2. **Static `toMailUsing` bocor ke tes lain.** `VerifyEmail::$toMailCallback` dan `ResetPassword::$toMailCallback` adalah static. Kebocoran akan merusak `CustomVerifyEmailTest` yang memeriksa subject/view hasil kustomisasi di `AppServiceProvider`. Aman karena `AppServiceProvider::boot()` menulis ulang callback pada setiap pembuatan aplikasi baru, tetapi ini hanya terbukti bila **seluruh suite** dijalankan. *Tes:* jalankan `php artisan test` penuh, bukan hanya berkas yang diubah.
3. **Percabangan "lupa password" berubah.** `PasswordResetLinkController::store` membedakan `RESET_LINK_SENT` dari `USER_NOT_FOUND`. Blok `try/catch` baru tidak boleh menyentuh percabangan itu. *Tes:* `test_forgot_password_for_unknown_email_keeps_existing_branching`.
4. **Banner kegagalan muncul pada jalur sukses.** Flash `verification-link-failed` yang ter-set meski email terkirim akan menakuti pengguna tanpa alasan. *Tes:* `test_registration_success_does_not_flash_failure_status`.
5. **`MAIL_ENCRYPTION` masih tertinggal di `.env` produksi.** Setelah dikhapus dari `.env.example`, berkas `.env` di server (yang tidak ikut git) tetap harus dibersihkan manual, dan `config:cache` dijalankan ulang — kalau tidak, perubahan tidak pernah terbaca. *Verifikasi:* langkah ini ada di **Task 7 Step 4**, di mana `.env` produksi dibuat baru dari `.env.example` sehingga `MAIL_ENCRYPTION` tidak pernah tertulis; karena ini deploy pertama, belum ada `.env` lama yang perlu dibersihkan. Task 8 Step 5 memeriksanya ulang sebelum ditandai selesai.

---

## Catatan lingkup

Plan ini punya dua bagian dengan sifat berbeda:

- **Bagian A (Task 1–5)** — perubahan kode di repository, dikerjakan dengan TDD, seluruhnya bisa dieksekusi otomatis.
- **Bagian B (Task 6–8)** — runbook deployment. Task ini **tidak bisa dieksekusi oleh subagent**: membeli domain, membayar hosting, dan mengubah dashboard Midtrans/Google membutuhkan uang, kredensial, dan akun milik pemilik proyek. Setiap task di Bagian B berupa daftar periksa dengan langkah verifikasi; pemilik proyek yang menjalankannya, dengan panduan di bawah.

---

## Bagian A — Perbaikan ketahanan email

### Task 1: Registrasi tidak gagal saat email verifikasi gagal

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php:46-57`
- Test: `tests/Feature/Auth/RegistrationTest.php`

**Interfaces:**
- Consumes: `Illuminate\Auth\Events\Registered` yang dipicu oleh listener bawaan Laravel `SendEmailVerificationNotification`. Listener itu mengirim `Illuminate\Auth\Notifications\VerifyEmail` secara sinkron pada request yang sama.
- Produces: flash `session('status') = 'verification-link-failed'` bila pengiriman gagal. Task 4 merender flash itu menjadi banner.

- [ ] **Step 1: Tulis tiga tes yang gagal**

Tambahkan ke `tests/Feature/Auth/RegistrationTest.php`, dengan `use Illuminate\Auth\Notifications\VerifyEmail;` di bagian use:

```php
public function test_standard_registration_survives_verification_email_failure(): void
{
    VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
        throw new \RuntimeException('SMTP down (simulasi)');
    });

    $response = $this->post('/register', [
        'name' => 'Resilient User',
        'email' => 'resilient@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('verification.notice'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'resilient@example.com']);
    $this->assertSame('verification-link-failed', session('status'));
}

public function test_ajax_registration_survives_verification_email_failure(): void
{
    VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
        throw new \RuntimeException('SMTP down (simulasi)');
    });

    $response = $this->postJson('/register', [
        'name' => 'Resilient User',
        'email' => 'resilient-json@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success'  => true,
        'redirect' => route('verification.notice'),
    ]);
    $this->assertAuthenticated();
    $this->assertSame('verification-link-failed', session('status'));
}

public function test_registration_success_does_not_flash_failure_status(): void
{
    $response = $this->postJson('/register', [
        'name' => 'Happy Path User',
        'email' => 'happypath@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(200);
    $this->assertAuthenticated();
    $this->assertNull(session('status'));
}
```

- [ ] **Step 2: Jalankan tes untuk memastikan gagal**

Run: `php artisan test --filter=RegistrationTest`
Expected: dua tes pertama **FAIL** — exception `RuntimeException: SMTP down (simulasi)` lolos keluar dari controller sehingga `Auth::login` tidak sempat dijalankan. Tes ketiga **PASS**.

- [ ] **Step 3: Bungkus `event(new Registered($user))` di `RegisteredUserController::store`**

Tambahkan `use Illuminate\Support\Facades\Log;` ke bagian use. Ganti baris `event(new Registered($user));` dengan blok berikut, tanpa menyentuh baris `User::create`, `Auth::login`, maupun bentuk respons:

```php
try {
    event(new Registered($user));
} catch (\Throwable $e) {
    Log::error('Email verifikasi gagal terkirim (registration)', [
        'to_email' => $user->email,
        'error'    => $e->getMessage(),
    ]);

    session()->flash('status', 'verification-link-failed');
}
```

`session()->flash()` dipakai alih-alih `redirect()->with()` karena jalur JSON tidak mengembalikan `RedirectResponse`. Dengan begitu flash tetap terbaca oleh halaman `verification.notice` berikutnya tanpa menambah field respons yang belum dikonsumsi frontend.

- [ ] **Step 4: Jalankan tes untuk memastikan lulus**

Run: `php artisan test --filter=RegistrationTest`
Expected: **PASS** untuk seluruh metode di kelas itu, termasuk empat tes yang sudah ada sebelumnya.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Auth/RegisteredUserController.php tests/Feature/Auth/RegistrationTest.php
git commit -m "fix(auth): registration survives verification email delivery failure"
```

---

### Task 2: Tombol kirim-ulang tidak 500 saat email gagal

**Files:**
- Modify: `app/Http/Controllers/Auth/EmailVerificationNotificationController.php:21-23`
- Test: `tests/Feature/CustomVerifyEmailTest.php`

**Interfaces:**
- Consumes: `$request->user()->sendEmailVerificationNotification()` dari kontrak `MustVerifyEmail`.
- Produces: flash `session('status') = 'verification-link-failed'` pada kegagalan, `'verification-link-sent'` pada sukses. Nilai kedua sudah dirender oleh Task 4.

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan ke `tests/Feature/CustomVerifyEmailTest.php`, dengan `use Illuminate\Auth\Notifications\VerifyEmail;` sudah ada di berkas itu:

```php
public function test_resend_verification_survives_email_failure(): void
{
    VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
        throw new \RuntimeException('SMTP down (simulasi)');
    });

    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertStatus(302);
    $response->assertSessionHas('status', 'verification-link-failed');
}
```

- [ ] **Step 2: Jalankan tes untuk memastikan gagal**

Run: `php artisan test --filter=CustomVerifyEmailTest`
Expected: tes baru **FAIL** dengan status 500, karena `sendEmailVerificationNotification()` melempar tanpa tertangkap. Dua tes lama tetap **PASS**.

- [ ] **Step 3: Bungkus pemanggilan notifikasi di `EmailVerificationNotificationController::store`**

Tambahkan `use Illuminate\Support\Facades\Log;`. Ganti `$request->user()->sendEmailVerificationNotification();` dengan:

```php
try {
    $request->user()->sendEmailVerificationNotification();
} catch (\Throwable $e) {
    Log::error('Email verifikasi gagal terkirim (resend_verification)', [
        'to_email' => $request->user()->email,
        'error'    => $e->getMessage(),
    ]);

    return back()->with('status', 'verification-link-failed');
}
```

Pertahanan guard `hasVerifiedEmail()` di awal metode tidak diubah.

- [ ] **Step 4: Jalankan tes untuk memastikan lulus**

Run: `php artisan test --filter=CustomVerifyEmailTest`
Expected: **PASS** untuk seluruh metode.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Auth/EmailVerificationNotificationController.php tests/Feature/CustomVerifyEmailTest.php
git commit -m "fix(auth): resend verification button survives email delivery failure"
```

---

### Task 3: Halaman lupa password tidak 500 saat email gagal

**Files:**
- Modify: `app/Http/Controllers/Auth/PasswordResetLinkController.php:28-36`
- Test: `tests/Feature/Auth/PasswordResetTest.php`

**Interfaces:**
- Consumes: `Illuminate\Support\Facades\Password::sendResetLink(array $credentials): string` yang mengembalikan `Password::RESET_LINK_SENT` atau `Password::USER_NOT_FOUND`.
- Produces: tidak ada flash baru; hanya `withErrors(['email' => ...])` pada cabang exception.

- [ ] **Step 1: Tulis dua tes yang gagal**

Tambahkan ke `tests/Feature/Auth/PasswordResetTest.php`, dengan `use Illuminate\Auth\Notifications\ResetPassword;` yang sudah ada di berkas itu:

```php
public function test_forgot_password_survives_email_failure(): void
{
    ResetPassword::toMailUsing(function ($notifiable, string $token) {
        throw new \RuntimeException('SMTP down (simulasi)');
    });

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', ['email' => $user->email]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
}

public function test_forgot_password_for_unknown_email_keeps_existing_branching(): void
{
    $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('email');
}
```

- [ ] **Step 2: Jalankan tes untuk memastikan gagal**

Run: `php artisan test --filter=PasswordResetTest`
Expected: `test_forgot_password_survives_email_failure` **FAIL** dengan status 500. Tes kedua **PASS** (jalur `USER_NOT_FOUND` tidak menyentuh transport, jadi tidak ada exception).

- [ ] **Step 3: Bungkus `Password::sendResetLink()` di `PasswordResetLinkController::store`**

Tambahkan `use Illuminate\Support\Facades\Log;`. Ganti pemanggilan `Password::sendResetLink(...)` dengan:

```php
try {
    $status = Password::sendResetLink(
        $request->only('email')
    );
} catch (\Throwable $e) {
    Log::error('Email reset password gagal terkirim (password_reset)', [
        'to_email' => $request->input('email'),
        'error'    => $e->getMessage(),
    ]);

    return back()
        ->withInput($request->only('email'))
        ->withErrors(['email' => 'Email gagal dikirim. Coba lagi beberapa saat lagi.']);
}
```

Percabangan `$status == Password::RESET_LINK_SENT` di bawahnya **tidak disentuh sama sekali** — itulah isi Review Focus nomor 3.

- [ ] **Step 4: Jalankan tes untuk memastikan lulus**

Run: `php artisan test --filter=PasswordResetTest`
Expected: **PASS** untuk seluruh metode, termasuk lima tes yang sudah ada.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Auth/PasswordResetLinkController.php tests/Feature/Auth/PasswordResetTest.php
git commit -m "fix(auth): forgot-password survives email delivery failure"
```

---

### Task 4: Banner status gagal di halaman verifikasi

**Files:**
- Modify: `resources/views/auth/verify-email.blade.php:45-61`
- Test: `tests/Feature/CustomVerifyEmailTest.php`

**Interfaces:**
- Consumes: flash `session('status')` dengan nilai `verification-link-failed`, yang sudah diproduksi oleh Task 1 dan Task 2.
- Produces: markup banner kedua di halaman yang sama. Tidak dikonsumsi task lain.

- [ ] **Step 1: Tulis dua tes yang gagal**

Tambahkan ke `tests/Feature/CustomVerifyEmailTest.php`:

```php
public function test_verification_page_shows_failure_banner(): void
{
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)
        ->withSession(['status' => 'verification-link-failed'])
        ->get('/verify-email');

    $response->assertStatus(200);
    $response->assertSee('Email verifikasi gagal dikirim', false);
}

public function test_verification_page_keeps_success_banner_without_failure_text(): void
{
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)
        ->withSession(['status' => 'verification-link-sent'])
        ->get('/verify-email');

    $response->assertStatus(200);
    $response->assertSee('Tautan verifikasi baru telah dikirimkan', false);
    $response->assertDontSee('Email verifikasi gagal dikirim', false);
}
```

- [ ] **Step 2: Jalankan tes untuk memastikan gagal**

Run: `php artisan test --filter=CustomVerifyEmailTest`
Expected: `test_verification_page_shows_failure_banner` **FAIL** (teks tidak ada di halaman). Tes kedua **PASS**.

- [ ] **Step 3: Tambahkan cabang `@elseif` untuk status gagal**

Di `resources/views/auth/verify-email.blade.php`, ganti blok `@if ... @endif` pada baris 46-61 menjadi tiga cabang. Gaya inline mengikuti banner sukses yang sudah ada; hanya warna yang berubah merah:

```blade
@if (session('status') == 'verification-link-sent')
    <div style="
        margin-bottom: 14px;
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid #BBF7D0;
        background: #F0FDF4;
        color: #15803D;
        font-size: 11.5px;
        line-height: 1.4;
        font-weight: 500;
        text-align: center;
    ">
        Tautan verifikasi baru telah dikirimkan ke alamat email Anda.
    </div>
@elseif (session('status') == 'verification-link-failed')
    <div style="
        margin-bottom: 14px;
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid #FECACA;
        background: #FEF2F2;
        color: #B91C1C;
        font-size: 11.5px;
        line-height: 1.4;
        font-weight: 500;
        text-align: center;
    ">
        Email verifikasi gagal dikirim. Coba lagi beberapa saat lagi.
    </div>
@endif
```

- [ ] **Step 4: Jalankan tes untuk memastikan lulus**

Run: `php artisan test --filter=CustomVerifyEmailTest`
Expected: **PASS** untuk seluruh metode.

- [ ] **Step 5: Jalankan seluruh suite untuk memastikan tidak ada regresi**

Run: `php artisan test`
Expected: seluruh suite **PASS**, tanpa kecuali. Kegagalan di `CustomVerifyEmailTest` yang lama menandakan static `toMailCallback` bocor — lihat Review Focus nomor 2.

- [ ] **Step 6: Commit**

```bash
git add resources/views/auth/verify-email.blade.php tests/Feature/CustomVerifyEmailTest.php
git commit -m "feat(auth): show failure banner on email verification resend"
```

---

### Task 5: `.env.example` disesuaikan untuk Resend

**Files:**
- Modify: `.env.example` (blok `MAIL_*`)

**Interfaces:**
- Consumes: kredensial SMTP Resend dari Global Constraints.
- Produces: bentuk `.env` produksi yang diacu Task 8. Tidak dikonsumsi kode mana pun.

- [ ] **Step 1: Ganti blok `MAIL_*` di `.env.example`**

Cari blok berikut dan ganti seluruhnya:

```ini
MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

menjadi:

```ini
# Transport SMTP Resend. Lihat https://resend.com/docs/dashboard/emails/smtp
# MAIL_PASSWORD berisi API key Resend (diawali re_).
# MAIL_SCHEME sengaja dibiarkan null: Laravel memilih smtps otomatis untuk port 465.
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=re_xxx
MAIL_FROM_ADDRESS="noreply@elcraft.id"
MAIL_FROM_NAME="el Craft"
```

Baris `MAIL_ENCRYPTION` tidak ada di `.env.example` dan tidak boleh ditambahkan.

- [ ] **Step 2: Verifikasi tidak ada kredensial sungguhan yang bocor**

Run: `git diff -- .env.example`
Expected: hanya blok `MAIL_*` yang berubah. `re_xxx` muncul sebagai placeholder, bukan kunci nyata.

- [ ] **Step 3: Verifikasi tidak ada kode PHP yang membaca `MAIL_ENCRYPTION`**

Run: `php artisan tinker --execute="var_dump(config('mail.mailers.smtp'))"`
Expected: array konfigurasi smtp tercetak tanpa kunci `encryption`, dengan `scheme` bernilai `null` dan `host` `smtp.resend.com`.

- [ ] **Step 4: Commit**

```bash
git add .env.example
git commit -m "chore(env): point example mail config at resend smtp"
```

---

## Bagian B — Runbook deployment

Task di bagian ini **tidak bisa dieksekusi subagent**. Masing-masing membutuhkan keputusan pembelian, kredensial, atau akses dashboard milik pemilik proyek. Jalankan berurutan; setiap task punya langkah verifikasi yang harus benar sebelum lanjut.

### Task 6: Beli domain, hosting, dan verifikasi domain di Resend

**Files:**
- Tidak ada perubahan berkas repository.

**Interfaces:**
- Produces: domain `elcraft.id` aktif dengan nameserver menunjuk ke Niagahoster; akses SSH ke server; domain `elcraft.id` berstatus `Verified` di Resend; API key Resend siap dipakai.

- [ ] **Step 1: Beli domain `elcraft.id` dan paket shared hosting di Niagahoster**

Pastikan tiga hal sebelum membayar:
- Paket menyediakan **SSH**. Tanpa SSH, seluruh Task 7 tidak bisa dijalankan.
- PHP yang ditawarkan versi **8.3 atau lebih tinggi**.
- Ada akses **Cron Jobs** di hPanel.

Kisaran anggaran: domain `.id` Rp 210.000–250.000/tahun; hosting promo tahunan sekitar Rp 59.000/bulan dengan kemungkinan naik 2–3× di perpanjangan.

- [ ] **Step 2: Aktifkan SSH di hPanel**

hPanel → dashboard website → menu SSH → Enable. Catat perintah koneksi (format `ssh -p <port> <username>@<host>`) dan username akun, karena dipakai di Task 7.

- [ ] **Step 3: Tambahkan domain ke Resend dan ambil record DNS**

Resend Dashboard → Domains → Add Domain → masukkan `elcraft.id`. Resend menampilkan **dua record TXT** (satu untuk SPF, satu untuk DKIM). Salin keduanya.

- [ ] **Step 4: Pasang record TXT di panel DNS Niagahoster**

hPanel → DNS / Nameservers → tambahkan kedua record TXT persis seperti nilai yang diberikan Resend. Tunggu sampai status domain di Resend berubah menjadi **Verified** (bisa butuh beberapa menit sampai beberapa jam).

- [ ] **Step 5: Verifikasi**

Buka Resend Dashboard → Domains. `elcraft.id` harus menampilkan status **Verified**, bukan `Pending` atau `Failed`.

Jangan lanjut ke Task 7 sebelum langkah ini berhasil — pengiriman dari domain yang belum diverifikasi dibalas `403`.

---

### Task 7: Deploy project ke shared hosting

**Files:**
- Tidak ada perubahan berkas repository.
- Menghasilkan `.env` produksi **di server**, yang tidak ikut git.

**Interfaces:**
- Consumes: akses SSH dan username hosting dari Task 6.
- Produces: `https://elcraft.id` melayani aplikasi Laravel; `php artisan schedule:run` siap dipasang di Task 8.

- [ ] **Step 1: Catat username dan jalur akun hosting**

Dari hPanel → Files → FTP Accounts, baca jalur root. Formatnya `/home/<username>/domains/elcraft.id/public_html` atau `/home/<username>/public_html`. Gantikan `<username>` dan jalur itu pada seluruh perintah di bawah; jangan menyalin literal.

- [ ] **Step 2: Buat struktur direktori dan symlink document root**

```bash
mkdir -p ~/elcraft
ln -s ~/elcraft/public ~/domains/elcraft.id/public_html
```

Bila `public_html` sudah ada berisi berkas default, pindahkan isinya ke arsip dulu: `mv ~/domains/elcraft.id/public_html ~/domains/elcraft.id/public_html.bak`, baru buat symlink-nya.

Verifikasi: `ls -la ~/domains/elcraft.id/` harus menampilkan `public_html` sebagai symlink (baris berisi `->`).

- [ ] **Step 3: Clone project dan pasang dependensi**

```bash
cd ~/elcraft
git clone <URL repo> .
composer install --no-dev --optimize-autoloader
```

- [ ] **Step 4: Buat `.env` produksi melalui SSH**

Salin `.env.example` lalu isi nilainya. Jangan unggah `.env` lewat File Manager.

```bash
cp .env.example .env
php artisan key:generate
```

Isi berikut wajib, sisanya disesuaikan:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://elcraft.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<nama database dari hPanel>
DB_USERNAME=<username database dari hPanel>
DB_PASSWORD=<password database dari hPanel>

MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=<API key Resend, diawali re_>
MAIL_FROM_ADDRESS=noreply@elcraft.id
MAIL_FROM_NAME="el Craft"

GOOGLE_REDIRECT_URI=https://elcraft.id/auth/google/callback
```

Perhatikan: `MAIL_ENCRYPTION` **tidak ditulis sama sekali**. `.env.example` tidak lagi memuatnya.

- [ ] **Step 5: Buat database MySQL dan jalankan migrasi**

Buat database beserta user dan grant di hPanel → Databases → MySQL. Lalu:

```bash
php artisan migrate --force
php artisan storage:link
```

Verifikasi `storage:link`: `ls -la ~/elcraft/public/storage` harus menunjuk ke `~/elcraft/storage/app/public`. Aplikasi memakai `storage/app/public/{products,hero-banners,avatars,returns,reviews}`.

- [ ] **Step 6: Build aset di mesin lokal dan salin ke server**

Di mesin lokal (satu-satunya tempat ada Node):

```bash
npm run build
```

Salin hasil build ke server:

```bash
scp -P <port> -r public/build <username>@<host>:~/elcraft/public/
```

Verifikasi di server: `ls ~/elcraft/public/build/` harus memuat `manifest.json`.

- [ ] **Step 7: Optimalkan dan nyalakan site**

```bash
php artisan optimize
```

Verifikasi:
- `curl -I https://elcraft.id` mengembalikan `HTTP/2 200` atau `302` — bukan `500`.
- `https://elcraft.id/verify-email` (tanpa login) mengalihkan, bukan error 500.
- Berkas `~/elcraft/.env` **tidak** bisa diunduh dari `https://elcraft.id/.env`.

- [ ] **Step 8: Uji pengiriman email sungguhan**

Di server:

```bash
php artisan tinker
>>> $u = \App\Models\User::where('email', '<alamat email pemilik akun Resend>')->first();
>>> $u->sendEmailVerificationNotification();
```

Verifikasi: tidak ada exception, dan email masuk ke inbox (bukan spam). Bila Resend membalas `403`, domain belum `Verified` — kembali ke Task 6 Step 5.

---

### Task 8: Pasang cron dan pengaturan luar

**Files:**
- Tidak ada perubahan berkas repository.

**Interfaces:**
- Consumes: server yang sudah jalan dari Task 7; domain `elcraft.id` sudah `Verified` di Resend.
- Produksi: `orders:auto-cancel-expired` dan `orders:auto-complete` berjalan otomatis; webhook Midtrans dan OAuth Google mengarah ke domain produksi.

- [ ] **Step 1: Pasang cron `schedule:run`**

hPanel → Cron Jobs → Create Cron Job.

| Field | Nilai |
|---|---|
| Schedule | Every minute (`* * * * *`) |
| Command | `php /home/<username>/elcraft/artisan schedule:run >> /dev/null 2>&1` |

Gantikan `<username>` dengan username akun hosting yang dicatat di Task 7 Step 1.

Bila panel menolak interval di bawah 15 menit, pakai `*/15 * * * *` dan catat bahwa `orders:auto-cancel-expired` (per jam) akan berjalan tiap 15 menit.

- [ ] **Step 2: Verifikasi scheduler benar-benar jalan**

```bash
php /home/<username>/elcraft/artisan schedule:list
```

Expected: `orders:auto-cancel-expired` (hourly) dan `orders:auto-complete` (daily) tercantum. Setelah satu jam, `~/elcraft/storage/logs/laravel.log` harus memuat jejak `orders:auto-cancel-expired` dijalankan.

- [ ] **Step 3: Ganti Notification URL di dashboard Midtrans**

Midtrans Dashboard → Settings → Configuration → **Notification URL**, isi:

```
https://elcraft.id/midtrans/webhook
```

Simpan, lalu verifikasi dengan membayar pesanan sandbox. Webhook harus tercatat di `laravel.log` sebagai `Midtrans webhook diterima`, dan `PaymentSuccessMail` terkirim.

Bila webhook tidak tiba, jalur cadangan tetap bekerja: cron per jam melakukan rekonsiliasi via API Midtrans dan mengirim email sukses pembayaran (lihat `MidtransService.php:292`).

- [ ] **Step 4: Ganti Authorized redirect URI di Google Cloud Console**

Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client ID milik aplikasi → **Authorized redirect URIs** → tambahkan:

```
https://elcraft.id/auth/google/callback
```

Simpan. Verifikasi: klik "Masuk dengan Google" di `https://elcraft.id` harus lolos tanpa `redirect_uri_mismatch`.

- [ ] **Step 5: Verifikasi akhir end-to-end**

Jalankan seluruh delapan kriteria penerimaan di §8.1 spec:

| # | Uji | Tanda berhasil |
|---|---|---|
| 1 | Daftar akun baru | Email verifikasi masuk inbox, tombol membuka `https://elcraft.id` |
| 2 | Klik link verifikasi | `email_verified_at` terisi, redirect `/?verified=1` |
| 3 | Belum verifikasi lalu checkout | Ditolak middleware `verified` |
| 4 | Tombol "kirim ulang" | Email kedua terkirim, banner hijau tampil |
| 5 | Login Google | Lolos tanpa email verifikasi |
| 6 | Bayar via Midtrans sandbox | Webhook diterima, `PaymentSuccessMail` terkirim |
| 7 | Cron | `orders:auto-cancel-expired` muncul di log tiap jam |
| 8 | API key Resend dicoret sementara | Registrasi tetap berhasil, banner merah tampil, `laravel.log` memuat konteks error |

Pengujian nomor 8 adalah inti dari seluruh perubahan di Bagian A. Setelah selesai, pulihkan API key yang benar dan jalankan `php artisan config:cache`.

Sebelum menandai task ini selesai, konfirmasi eksplisit bahwa `MAIL_ENCRYPTION` tidak ada di `.env` produksi dan tidak pernah dibaca:

```bash
grep -c MAIL_ENCRYPTION ~/elcraft/.env          # harus 0
php artisan tinker --execute="var_dump(array_key_exists('encryption', config('mail.mailers.smtp')))"  # bool(false)
php artisan config:cache                         # jalankan ulang setelah membersihkan .env
```

- [ ] **Step 6: Catat cara memantau setelah live**

Tidak ada tindakan kode, tetapi catat tiga titik ini di tempat yang mudah dicari (misal `docs/DEPLOY.md`) supaya kegagalan email tidak hilang tanpa jejak:

- **Resend Dashboard → Logs**: status `delivered`, `bounced`, `complained` per email. Alamat yang memantul otomatis masuk suppressed list.
- **`~/elcraft/storage/logs/laravel.log`**: saring kata `gagal terkirim`. Semua kegagalan yang tertangkap `try/catch` dari Bagian A muncul di sini dengan `to_email` dan pesan exception.
- **Kuota gratis Resend**: 100 email/hari (hari kalender UTC), 3.000/bulan, maksimum 3 domain. Saat terlampaui Resend membalas `429`; error itu tertangkap `try/catch` sehingga terlihat di log.

- [ ] **Step 7: Tag rilis**

```bash
git tag -a v1.0.0-live -m "First production deployment: resend smtp on shared hosting"
```

Tag ini menjadi acuan rollback: `git checkout <tag>` → pulihkan `.env` → `php artisan optimize`. Basis data tidak ikut di-rollback.
