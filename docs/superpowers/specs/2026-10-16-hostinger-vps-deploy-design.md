# Design: Deploy elcraft ke Hostinger VPS

Status: **draft — menunggu pembelian VPS + jawaban open questions di bawah.**
Sumber keputusan: sesi eksplorasi 2026-10-16. User memilih Hostinger VPS (sebelumnya shared hosting, lalu hosting gratis — keduanya dievaluasi dan digugurkan: Hostinger tanpa free tier, Freenom tutup, Render gratis tanpa MySQL gratis + sleep 15 mnt). User berbayar via **QRIS** (tidak ada kartu/PayPal); Hostinger Indonesia menerima QRIS/OVO/DANA/VA untuk akun ber-mata-uang IDR. User tidak berpengalaman mengelola VPS — **saya yang setup dan kelola via SSH**; tali penyelamat user = web terminal hPanel.

## Keputusan

| Area | Keputusan | Alasan |
|---|---|---|
| Provider/plan | Hostinger **VPS KVM 1** (1 vCPU, 4 GB RAM, 50 GB NVMe, 4 TB BW), term 12–24 bln (promo), garansi uang kembali 30 hari **mencakup KVM** | Cukup untuk Laravel trial; jalur "coba-coba" aman via refund |
| Pembayaran | Brand Hostinger Indonesia, akun IDR, **QRIS** | Satu-satunya metode yang user punya |
| Region/OS | Datacenter **Singapore**, **Ubuntu 24.04 LTS** | Latensi terbaik dari ID; LTS + PHP 8.3 & MySQL 8 bawaan (tanpa PPA) |
| Stack | nginx + **PHP 8.3-FPM** (noble) + **MySQL 8** + **Node 22** (NodeSource, untuk Vite 8) + certbot (python3-certbot-nginx) + ufw + fail2ban | Laravel 13 butuh PHP ≥8.3; Vite 8 butuh Node 20.19+/22.12+ |
| App path | `/var/www/elcraft` (satu direktori, deploy via `git pull --ff-only`) | YAGNI untuk trial — tanpa skema releases/symlink |
| Sumber kode | Clone `https://github.com/athifabril11/elcraft` (repo **publik**) | Tanpa deploy key / GitHub auth di server |
| Build aset | `npm ci && npm run build` di server (public/build tidak di-commit) | Vite build lokal tidak terpakai; build di server selalu sinkron |
| Database prod | MySQL `elcraft` + user khusus; `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` = **database** | Sesuai .env.example; migrasi cache+jobs sudah ada |
| Antrean | **systemd unit** `elcraft-queue.service` (`queue:work` sebagai www-data, Restart=always) | Database queue butuh worker; systemd lebih andal dari cron untuk worker |
| Jadwal | Cron `schedule:run` (www-data) | Standar Laravel; saat ini belum ada scheduled task |
| HTTPS | certbot `--nginx`, domain `elcraft.id` + `www` redirect, HTTP-01 | Butuh DNS A record → IP VPS |
| Email | Resend SMTP (sudah terintegrasi, Task 1-B selesai) | Tanpa MTA lokal |
| Midtrans | **Tetap sandbox** (`MIDTRANS_IS_PRODUCTION=false`) di deploy pertama | Trial; flip ke production terpisah setelah live + update URL webhook di dashboard |
| Backup | mysqldump harian gzip → `/root/backups` (retensi 7 hari) + backup mingguan bawaan Hostinger + snapshot manual sebelum perubahan berisiko | Cukup untuk trial |
| Akses admin | SSH root dari mesin Windows ini; keypair ed25519 lokal dipasang; **ufw/penyegaran hardening hanya setelah key login terverifikasi** | Mencegah lockout; web terminal hPanel = lifeline |
| Artefak | File server (nginx conf, systemd unit, backup script) dibuat langsung di server; isi persis ada di implementation plan. `scripts/deploy.sh` dibuat di repo lalu di-scp — **tidak commit/push tanpa instruksi user** | Aturan sesi: jangan commit kecuali diminta; push dilarang selama sesi lain hidup |
| `.env` produksi | Dibangun **utuh di mesin lokal** dari `.env.example` + override + secret dari `.env` lokal, lalu di-scp ke server (password DB disuntik server-side dari `/root/elcraft-db.env`) | phpdotenv immutable: nilai pertama menang — append override tidak mengubah `DB_CONNECTION=sqlite`/key kosong |

## Yang dibeli user (checklist)

1. Hostinger VPS **KVM 1**, OS **Ubuntu 24.04**, region **Singapore**, term 12/24 bulan.
2. Domain **elcraft.id** bila belum punya (± Rp100–150rb/th; bisa di Hostinger/Niagahoster, bayar QRIS).
3. Setelah aktif: kirim **IP publik + password root** ke saya.

## Open questions (harus terjawab sebelum eksekusi)

1. **Domain**: `elcraft.id` sudah terdaftar? DNS-nya di registrar mana (butuh akses ubah A record → IP VPS)? — *mengblok Task 4 (HTTPS)*.
2. **Secret**: boleh saya ambil nilai Resend / Midtrans (sandbox) / RajaOngkir / Google OAuth dari `.env` lokal dan kirim ke server tanpa ditampilkan? — bila tidak, user mengisi manual.
3. **VPS**: sudah dibeli? IP + root password kapan dikirim?
4. **User action**: setelah live, tambahkan `https://elcraft.id/auth/google/callback` ke Authorized redirect URIs di Google Console; update URL webhook Midtrans bila nanti flip production.

## Batasan & risiko

- Saya kelola via SSH; **jangan pernah menutup web terminal hPanel** selama sesi hardening.
- Tidak ada perintah destruktif (reboot, ufw deny, hapus data, flip production) tanpa persetujuan eksplisit.
- Secret tidak pernah ditampilkan/di-commit; `.env` hanya di server (600, milik www-data).
- Commit/push repo hanya bila user minta.

## Definisi selesai

- `https://elcraft.id` → HTTP 200, sertifikat valid, redirect www→apex (atau sebaliknya, konsisten).
- `php artisan about` jalan di server; `migrate` sukses; `elcraft-queue` active; dump backup terakhir ada.
- Jalur `deploy.sh` (pull→install→build→migrate→optimize→restart worker) sukses dijalankan sekali penuh sebagai rehearsal.
