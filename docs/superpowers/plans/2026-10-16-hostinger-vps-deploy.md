# Deploy elcraft ke Hostinger VPS — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menjalankan aplikasi Laravel elcraft di production pada Hostinger VPS KVM 1 (Ubuntu 24.04, Singapore) dengan HTTPS di `https://elcraft.id`, termasuk MySQL, queue worker, backup, dan hardening dasar — dikelola via SSH dari mesin Windows ini.

**Architecture:** Satu VPS unmanaged; stack manual nginx + PHP 8.3-FPM + MySQL 8 + Node 22. Aplikasi di `/var/www/elcraft` di-clone dari GitHub (repo publik), build aset Vite di server, deploy ulang lewat `git pull` + script. Queue database dijalankan oleh systemd; jadwal Laravel via cron. Akses: SSH root dengan keypair ed25519 dari mesin lokal; tali penyelamat user = web terminal hPanel + snapshot.

**Tech Stack:** Ubuntu 24.04 LTS, nginx, PHP 8.3-FPM, MySQL 8, Node 22 (NodeSource), Composer 2, certbot (python3-certbot-nginx), ufw, fail2ban, systemd, Laravel 13 / Vite 8 (milik aplikasi).

**Spec:** `docs/superpowers/specs/2026-10-16-hostinger-vps-deploy-design.md`

## Global Constraints

- Plan beli: Hostinger VPS **KVM 1**, OS **Ubuntu 24.04 LTS**, datacenter **Singapore**, bayar QRIS via brand IDR.
- Domain & URL publik: **`https://elcraft.id`** (www → redirect ke apex), sertifikat Let's Encrypt.
- PHP **8.3** (paket noble, tanpa PPA), Node **22.x** (Vite 8 menolak Node < 20.19).
- Produksi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://elcraft.id`; `SESSION_DRIVER`/`QUEUE_CONNECTION`/`CACHE_STORE` = `database`; Midtrans **sandbox** (`MIDTRANS_IS_PRODUCTION=false`).
- Secret (DB password, API keys) tidak pernah ditampilkan di output, tidak pernah di-commit; `.env` hanya di server, izin 600, milik `www-data`.
- **Commit/push hanya bila user minta secara eksplisit.** Artefak server dibuat langsung di VPS; `scripts/deploy.sh` di-working-tree lalu di-scp.
- Tidak ada aksi destruktif (reboot, `ufw deny`, hapus data, flip production) tanpa persetujuan user.
- `ufw` **baru diaktifkan di Task 6**, setelah login via key terverifikasi, dan **OpenSSH selalu diizinkan lebih dulu**.
- Snapshot hPanel diambil user sebelum Task 6 (hardening) — pengingat di task tersebut.
- Push ke GitHub dari mesin ini dilarang selama sesi lain hidup; server meng-clone/`pull` dari GitHub apa adanya (perubahan lokal yang belum di-push tidak terlihat server — deploy.sh di-scp langsung).

## Review Focus

Lima hal yang paling mungkin menggagalkan deployment orang yang menjalankan plan ini, dan tes yang menempelkannya ke task:

1. **Kunci SSH terpasang tapi tidak terverifikasi sebelum ufw aktif** → Task 6 Step wajib: sesi SSH kedua dengan key sukses **sebelum** `ufw --force enable`; kalau gagal, `ufw disable` via web terminal hPanel.
2. **.env keliru** — phpdotenv immutable (nilai pertama menang): meng-append override setelah `cp .env.example .env` **tidak mengubah** `DB_CONNECTION=sqlite` atau key kosong lain → Task 3 membangun `.env` utuh secara lokal lalu scp (satu sumber, tanpa duplikat), lalu urutan wajib: .env lengkap → `key:generate` → `migrate` → `optimize`; verifikasi `php artisan about` exit 0.
3. **`storage/` & `bootstrap/cache/` bukan milik www-data** → Task 3 Step chown + verifikasi `sudo -u www-data php artisan about` (bukan hanya artisan sebagai root).
4. **Build aset gagal (Node terlalu tua / manifest hilang)** → Task 2 memverifikasi `node -v` ≥ 22; Task 3 memverifikasi `public/build/manifest.json` ada dan `curl` mengambil satu aset hashed sukses.
5. **Queue worker mati setelah reboot atau deploy lupa restart** → Task 5 `systemctl enable` + verifikasi `is-active`; Task 7 `deploy.sh` selalu merestart unit dan dijalankan sekali penuh sebagai rehearsal.

---

### Task 1: Akses awal — SSH key, kredensial VPS, MCP VPS

**Files:**
- Create (lokal): `C:\Users\Windows 11\.ssh\id_ed25519_elcraft` (+ `.pub`) — di luar repo, jangan pernah masuk git
- Modify (lokal): `~/.config/opencode/opencode.json` (tambah `mcp.hostinger-vps`)
- Tidak ada perubahan file aplikasi

**Interfaces:**
- Consumes: pembelian VPS oleh user (IP publik, password root); `opencode.json` yang sudah berisi `mcp.hostinger` (hasil sesi sebelumnya, backup `opencode.json.bak`).
- Produces: sesi `ssh root@<IP>` yang bisa dipakai task berikutnya; tool MCP `hostinger-vps` (opsional, untuk status/reboot/snapshot via API).

- [ ] **Step 1: Minta user 3 hal** — IP publik VPS, password root, dan izin memasang `hostinger-vps-mcp`. (Blokir: tanpa ini task berhenti.)

- [ ] **Step 2: Generate keypair ed25519 di mesin lokal**

```powershell
ssh-keygen -t ed25519 -f "$env:USERPROFILE\.ssh\id_ed25519_elcraft" -N '""' -C "win11-elcraft-deploy"
```

Expected: file `id_ed25519_elcraft` dan `id_ed25519_elcraft.pub` terbentuk.

- [ ] **Step 3: Login pertama ke VPS dengan password**

```powershell
ssh -o PreferredAuthentication=password root@<IP>
```

Expected: shell root Ubuntu; `lsb_release -ds` → `Ubuntu 24.04 LTS`.

- [ ] **Step 4: Pasang pubkey ke VPS** (dari mesin lokal, PowerShell):

```powershell
type "$env:USERPROFILE\.ssh\id_ed25519_elcraft.pub" | ssh root@<IP> "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
```

- [ ] **Step 5: Verifikasi login tanpa password** — buka sesi baru:

```powershell
ssh -i "$env:USERPROFILE\.ssh\id_ed25519_elcraft" root@<IP> "echo key-login-ok"
```

Expected: `key-login-ok` tanpa diminta password.

- [ ] **Step 6: Tambah MCP VPS ke `~/.config/opencode/opencode.json`** — sisipkan di objek `mcp`, meniru pola `mcp.hostinger` yang ada:

```json
"hostinger-vps": {
  "type": "local",
  "command": [
    "cmd.exe",
    "/c",
    "C:\\Program Files\\nodejs\\npx.cmd",
    "-y",
    "--package=@hostinger/mcp@latest",
    "hostinger-vps-mcp"
  ]
}
```

Expected: `node -e "JSON.parse(require('fs').readFileSync(require('os').homedir()+'/.config/opencode/opencode.json','utf8'))"` tanpa error; backup `opencode.json.bak` tetap ada.

- [ ] **Step 7: Minta user restart opencode, lalu verifikasi**

```powershell
opencode mcp list
```

Expected: `✓ hostinger-vps connected`. (Gagal non-kritis: lanjut tanpa MCP — semua task bisa jalan via SSH.)

- [ ] **Step 8: Minta user mengambil snapshot pertama di hPanel** (VPS → Snapshots → Take snapshot). Expected: snapshot berstatus `completed` — ini tali penyelamat sebelum Task 6.

---

### Task 2: Fondasi server — MySQL, PHP-FPM, nginx, Node, Composer, certbot

**Files:**
- Server only (tidak di repo): `/etc/nginx/sites-available/elcraft`, symlink `sites-enabled`, `/root/elcraft-db.env`

**Interfaces:**
- Consumes: sesi SSH root (Task 1).
- Produces: database MySQL `elcraft` + user `elcraft` (kredensial di `/root/elcraft-db.env`); PHP-FPM socket `/run/php/php8.3-fpm.sock`; virtualhost nginx root `/var/www/elcraft/public` (sementara 502/app belum ada — normal); `node -v` ≥ 22; bin `composer`, `certbot`.

- [ ] **Step 1: Update paket & set timezone**

```bash
apt-get update && apt-get upgrade -y
timedatectl set-timezone Asia/Jakarta
```

Expected: `timedatectl` menunjukkan `Asia/Jakarta`.

- [ ] **Step 2: Install MySQL 8, PHP 8.3-FPM + ekstensi, nginx, tools**

```bash
DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server nginx php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-opcache unzip curl
```

Expected: `php -v` → 8.3.x; `mysql --version` → 8.0.x; `systemctl is-active nginx php8.3-fpm mysql` → `active active active`.

- [ ] **Step 3: Install Composer 2**

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

Expected: `composer --version` → Composer 2.x.

- [ ] **Step 4: Install Node 22 (NodeSource)**

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs
```

Expected: `node -v` → `v22.x.x` (wajib; Vite 8 gagal di bawah 20.19).

- [ ] **Step 5: Install paket certbot (izin dulu, terbit nanti di Task 4)**

```bash
DEBIAN_FRONTEND=noninteractive apt-get install -y certbot python3-certbot-nginx
```

Expected: `certbot --version` → certbot 2.x.

- [ ] **Step 6: Buat database + user MySQL, simpan kredensial root-only**

```bash
DB_PASS=$(openssl rand -hex 24)
mysql <<SQL
CREATE DATABASE elcraft CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'elcraft'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON elcraft.* TO 'elcraft'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
printf 'DB_DATABASE=elcraft\nDB_USERNAME=elcraft\nDB_PASSWORD=%s\n' "$DB_PASS" > /root/elcraft-db.env
chmod 600 /root/elcraft-db.env
```

Expected: `mysql -u elcraft -p"$(grep DB_PASSWORD /root/elcraft-db.env | cut -d= -f2)" -h 127.0.0.1 -e "SELECT 1"` → `1`. Password tidak pernah muncul di transcript.

- [ ] **Step 7: Tulis virtualhost nginx** — `/etc/nginx/sites-available/elcraft`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name elcraft.id www.elcraft.id;
    root /var/www/elcraft/public;
    index index.php;

    client_max_body_size 32M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

Lalu:

```bash
ln -sf /etc/nginx/sites-available/elcraft /etc/nginx/sites-enabled/elcraft
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

Expected: `nginx -t` → syntax ok; `curl -sI -H "Host: elcraft.id" http://127.0.0.1/ | head -1` → `HTTP/1.1 502 Bad Gateway` (normal: app belum ada).

---

### Task 3: Deploy aplikasi pertama

**Files:**
- Server: `/var/www/elcraft` (clone), `/var/www/elcraft/.env` (600, www-data)
- Lokal: `.env` project (hanya dibaca untuk menyalin secret — tidak ditampilkan, tidak diubah)

**Interfaces:**
- Consumes: DB creds `/root/elcraft-db.env` (Task 2); nginx root `/var/www/elcraft/public` (Task 2); secret di `.env` lokal (izin user, open question #2 design doc).
- Produces: aplikasi hidup di HTTP (via Host header); `APP_KEY` produksi baru (bukan key lokal); `public/build/` ter-build; `storage:link` ada.

- [ ] **Step 1: Clone repo publik**

```bash
git clone https://github.com/athifabril11/elcraft /var/www/elcraft
cd /var/www/elcraft
```

Expected: `git log --oneline -1` menampilkan commit terakhir GitHub.

- [ ] **Step 2: Bangun `.env` produksi utuh di mesin lokal (satu sumber, tanpa duplikat key), lalu scp** — phpdotenv immutable: nilai pertama menang, jadi `.env` tidak boleh dibuat dengan cara append. Dari `C:\laragon\www\elcraft` di PowerShell (nilai secret tidak pernah di-`Write-Output`):

```powershell
$p = "$env:TEMP\elcraft.env"
Copy-Item .env.example $p -Force
$sec = @{}
Get-Content .env -ErrorAction Stop | ForEach-Object { if ($_ -match '^([A-Z0-9_]+)=(.*)$') { $sec[$Matches[1]] = $Matches[2] } }
foreach ($k in 'MAIL_PASSWORD','MIDTRANS_SERVER_KEY_SANDBOX','MIDTRANS_CLIENT_KEY_SANDBOX','RAJAONGKIR_API_KEY','GOOGLE_CLIENT_ID','GOOGLE_CLIENT_SECRET') {
  $v = if ($sec.ContainsKey($k)) { $sec[$k] } else { '' }
  (Get-Content $p -Raw) -replace "(?m)^$k=.*", "$k=$v" | Set-Content $p -NoNewline
}
$o = [ordered]@{ APP_NAME='"el Craft"'; APP_ENV='production'; APP_DEBUG='false'; APP_URL='https://elcraft.id'; LOG_LEVEL='error'; DB_CONNECTION='mysql'; DB_HOST='127.0.0.1'; DB_PORT='3306'; DB_DATABASE='elcraft'; DB_USERNAME='elcraft'; DB_PASSWORD=placeholder; MIDTRANS_IS_PRODUCTION='false' }
foreach ($k in $o.Keys) { (Get-Content $p -Raw) -replace "(?m)^$k=.*", "$k=$($o[$k])" | Set-Content $p -NoNewline }
scp -i "$env:USERPROFILE\.ssh\id_ed25519_elcraft" $p root@<IP>:/var/www/elcraft/.env
```

Bila `.env` lokal tidak ada atau kunci tertentu kosong, lanjut dengan nilai kosong dan catat untuk open question #2 design doc. `SESSION_DRIVER`/`QUEUE_CONNECTION`/`CACHE_STORE` tidak di-override: `.env.example` sudah `database`.

- [ ] **Step 3: Suntik password DB server-side + verifikasi tanpa membocorkan secret** (server):

```bash
cd /var/www/elcraft
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(grep '^DB_PASSWORD=' /root/elcraft-db.env | cut -d= -f2-)|" .env
chmod 600 .env && chown www-data:www-data .env
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|DB_CONNECTION|DB_HOST|DB_DATABASE)=' .env
grep -c '^DB_PASSWORD=placeholder$' .env || true
grep -cE '^MAIL_PASSWORD=re_' .env
```

Expected: blok APP_ / DB_ sesuai produksi (`production`, `false`, `https://elcraft.id`, `mysql`, `127.0.0.1`, `elcraft`); jumlah `DB_PASSWORD=placeholder` = **0**; jumlah `MAIL_PASSWORD=re_` = **1** (bila key Resend ada). Nilai secret tidak pernah tercetak.

- [ ] **Step 4: Generate APP_KEY produksi di server**

```bash
cd /var/www/elcraft && sudo -u www-data php artisan key:generate --force
```

Expected: `php artisan about` (sebagai `sudo -u www-data`) exit 0.

- [ ] **Step 5: Install dependensi PHP (tanpa dev) + build aset**

```bash
cd /var/www/elcraft
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Expected: `public/build/manifest.json` ada; `ls public/build/assets/*.css | head -1` menunjukkan file hashed.

- [ ] **Step 6: Hak akses, storage link, migrate, optimize**

```bash
cd /var/www/elcraft
chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan storage:link
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
```

Expected: migrate mencetak daftar migration `DONE`; `php artisan optimize` tanpa error.

- [ ] **Step 7: Verifikasi HTTP lokal**

```bash
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: elcraft.id" http://127.0.0.1/
curl -s -H "Host: elcraft.id" http://127.0.0.1/ | grep -o '<title>[^<]*</title>'
```

Expected: `200` dan tag judul halaman elcraft.

---

### Task 4: DNS & HTTPS (Let's Encrypt)

**Files:**
- Server: konfigurasi certbot mengubah `/etc/nginx/sites-available/elcraft`

**Interfaces:**
- Consumes: virtualhost dengan `server_name elcraft.id www.elcraft.id` (Task 2); aplikasi hidup (Task 3).
- Produces: sertifikat valid; HTTP→HTTPS redirect aktif.

- [ ] **Step 1: Minta user input** — (a) email untuk registrasi Let's Encrypt; (b) status DNS: `elcraft.id` terdaftar di mana, lalu arahkan **A record `@` → IP VPS** dan **CNAME `www` → elcraft.id** (hPanel/registrar masing-masing). Tunggu propagasi.

- [ ] **Step 2: Verifikasi DNS merata**

```bash
dig +short elcraft.id A
dig +short www.elcraft.id CNAME   # bila `dig` tidak ada: apt-get install -y dnsutils
```

Expected: A record = IP VPS; www mengarah ke elcraft.id. (Propagasi bisa menit sampai jam — jangan lanjut sebelum cocok.)

- [ ] **Step 3: Terbitkan sertifikat**

```bash
certbot --nginx -d elcraft.id -d www.elcraft.id --redirect --non-interactive --agree-tos -m <email-user>
```

Expected: `Congratulations! ... successfully enabled HTTPS`.

- [ ] **Step 4: Paksakan www → apex** — tambahkan blok ini **di atas** blok `server` utama di `/etc/nginx/sites-available/elcraft` (certbot menambah blok 443 sendiri; blok www tetap di awal file):

```nginx
server {
    listen 80;
    listen [::]:80;
    listen 443 ssl;
    server_name www.elcraft.id;
    ssl_certificate /etc/letsencrypt/live/elcraft.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/elcraft.id/privkey.pem;
    return 301 https://elcraft.id$request_uri;
}
```

(Path sertifikat baru ada **setelah** Step 3 — karena itu blok ini dipasang setelah certbot, bukan sebelumnya.)

Lalu `nginx -t && systemctl reload nginx`. Expected: `curl -sI https://www.elcraft.id/ | head -1` → `HTTP/2 301` dan `curl -sI https://www.elcraft.id/ | grep -i '^location:'` menunjuk `https://elcraft.id/`. (Sertifikat www sudah tercakup `-d www.elcraft.id` di Step 3.)

- [ ] **Step 5: Verifikasi publik**

```bash
curl -sI https://elcraft.id/ | head -3
curl -sI http://elcraft.id/ | head -3
```

Expected: HTTPS → `HTTP/2 200` (atau 301 ke versi https stabil); HTTP → `301` ke https. Dari mesin lokal: buka `https://elcraft.id` di browser → halaman elcraft, gembok HTTPS aktif.

---

### Task 5: Queue worker, scheduler, backup

**Files:**
- Server: `/etc/systemd/system/elcraft-queue.service`, `/etc/cron.d/elcraft`, `/root/backup-elcraft.sh`

**Interfaces:**
- Consumes: aplikasi + `.env` lengkap (Task 3), MySQL `elcraft` (Task 2).
- Produces: unit `elcraft-queue` active & enabled; cron scheduler berjalan; dump harian tersimpan di `/root/backups`.

- [ ] **Step 1: Buat systemd unit queue** — `/etc/systemd/system/elcraft-queue.service`:

```ini
[Unit]
Description=Laravel queue worker elcraft
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/elcraft
ExecStart=/usr/bin/php /var/www/elcraft/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now elcraft-queue
```

Expected: `systemctl is-enabled elcraft-queue` → `enabled`; `is-active` → `active`.

- [ ] **Step 2: Cron scheduler Laravel** — buat `/etc/cron.d/elcraft`:

```
* * * * * www-data cd /var/www/elcraft && php artisan schedule:run >> /dev/null 2>&1
```

Expected: `run-parts` bersih; `sudo -u www-data php artisan schedule:list` tanpa error.

- [ ] **Step 3: Script backup harian** — `/root/backup-elcraft.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
mkdir -p /root/backups
TS=$(date +%F-%H%M)
mysqldump --single-transaction --routines --events elcraft | gzip > /root/backups/elcraft-$TS.sql.gz
find /root/backups -name 'elcraft-*.sql.gz' -mtime +7 -delete
```

```bash
chmod 700 /root/backup-elcraft.sh
/root/backup-elcraft.sh
printf '15 2 * * * root /root/backup-elcraft.sh\n' > /etc/cron.d/elcraft-backup
```

Expected: `ls -lh /root/backups/` menunjukkan satu `.sql.gz` tidak kosong (uji restore ringan: `zcat … | head -5` menampilkan `-- MySQL dump`).

- [ ] **Step 4: Verifikasi end-to-end worker** — dari mesin lokal: login user biasa di situs (atau admin) hingga memicu job, lalu di server:

```bash
mysql elcraft -e "SELECT COUNT(*) FROM jobs;"   -- antrean terkuras
journalctl -u elcraft-queue -n 20 --no-pager
```

Expected: `jobs` = 0 setelah job diproses; log worker menunjukkan memproses job tanpa exception.

---

### Task 6: Hardening — firewall, fail2ban, kunci saja

**Files:**
- Server: aturan ufw, konfigurasi fail2ban default, `sshd_config` (opsional)

**Interfaces:**
- Consumes: login key terverifikasi (Task 1 Step 5); semua layanan sudah jalan (Task 2–5); snapshot user sudah completed (Task 1 Step 8).
- Produces: firewall aktif (22/80/443 saja), fail2ban aktif, opsional password auth nonaktif.

- [ ] **Step 1: Pengingat snapshot** — konfirmasi ke user: snapshot hPanel terakhir `completed`. **Jangan lanjut tanpa ini.**

- [ ] **Step 2: Aktifkan ufw dengan OpenSSH dulu**

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
```

Expected: `ufw status verbose` → `active`, tiga rule tercantum.

- [ ] **Step 3: Verifikasi tidak terkunci** — buka **sesi SSH kedua** dengan key dari mesin lokal (`ssh -i … root@<IP> "echo still-alive"`) **sebelum** menutup sesi lama. Expected: `still-alive`. Gagal → user jalankan `ufw disable` di web terminal hPanel, lalu debug.

- [ ] **Step 4: Install & aktifkan fail2ban**

```bash
DEBIAN_FRONTEND=noninteractive apt-get install -y fail2ban
systemctl enable --now fail2ban
fail2ban-client status sshd
```

Expected: jail `sshd` aktif.

- [ ] **Step 5: Opsional — matikan password auth** (hanya dengan persetujuan eksplisit user): tambahkan `PasswordAuthentication no` ke `/etc/ssh/sshd_config.d/` via drop-in, `systemctl reload sshd`, lalu verifikasi sesi key baru sukses dan `ssh root@<IP>` dengan password **ditolak**. Bila ragu satu langkah pun: lewati — web terminal tetap jalan.

- [ ] **Step 6: Verifikasi situs masih hidup** — `curl -sI https://elcraft.id/ | head -1` → 200; `systemctl is-active nginx php8.3-fpm mysql elcraft-queue` → semua `active`.

---

### Task 7: Deploy rutin — script, rehearsal, serah-terima

**Files:**
- Create (lokal, di-repo tapi **tidak di-commit** tanpa instruksi): `scripts/deploy.sh`
- Server: `/root/deploy-elcraft.sh` (salinan via scp)

**Interfaces:**
- Consumes: seluruh Task 1–6 (akses key, app path, DB, worker).
- Produces: jalur deploy ulang yang bisa diulang kapan pun: pull → install → build → migrate → optimize → restart worker.

- [ ] **Step 1: Tulis `scripts/deploy.sh` di repo lokal:**

```bash
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/elcraft
git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci
npm run build
chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
systemctl restart elcraft-queue
echo "Deploy OK $(date -Is)"
```

- [ ] **Step 2: scp ke server & tandai executable**

```powershell
scp -i "$env:USERPROFILE\.ssh\id_ed25519_elcraft" scripts\deploy.sh root@<IP>:/root/deploy-elcraft.sh
```

Server: `chmod 700 /root/deploy-elcraft.sh`.

- [ ] **Step 3: Rehearsal penuh** — jalankan `/root/deploy-elcraft.sh`. Expected: berakhir `Deploy OK …` tanpa error; `curl -sI https://elcraft.id/ | head -1` → 200; `systemctl is-active elcraft-queue` → `active`.

- [ ] **Step 4: Serah-terima ke user** — rangkum dalam pesan penutup: cara deploy (kirim perintah `/root/deploy-elcraft.sh` atau minta saya), cara membaca log (`journalctl -u elcraft-queue`, `storage/logs/laravel.log`), cara restart (`systemctl restart elcraft-queue` / nginx), tali penyelamat (web terminal hPanel + snapshot), deadline refund 30 hari, dan daftar tindak lanjut user: konfirmasi Google Console redirect URI, flip Midtrans ke production hanya setelah yakin.

- [ ] **Step 5: (Bila user minta commit)** commit `scripts/deploy.sh` + dokumen plan/design ini dengan pesan `chore: add deployment assets for hostinger vps`. **Push hanya bila user izinkan** (sesi lain masih hidup).
