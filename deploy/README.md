# Deploy Booking AC — komputer sendiri + Cloudflare Tunnel

Skema: **Client → Cloudflare (HTTPS) → `cloudflared` (tunnel) → Nginx `127.0.0.1:8080` → PHP-FPM → Laravel**.
Tidak perlu membuka port router / IP publik; sertifikat HTTPS diurus Cloudflare.
Langkah di bawah untuk **macOS (Homebrew)**; padanan Linux ditandai *Linux:*.

> Komputer server harus **menyala terus** (matikan sleep: System Settings → Energy → "Prevent automatic sleeping"),
> dan sebaiknya memakai UPS. Bila komputer mati, situs tidak bisa diakses.

## 1. Paket yang dibutuhkan

```bash
brew install php@8.2 mysql nginx composer node cloudflared
brew services start mysql
brew services start php@8.2        # PHP-FPM di 127.0.0.1:9000
```
*Linux:* `apt install php8.2-fpm php8.2-{mysql,gd,mbstring,xml,curl,zip,intl} mysql-server nginx supervisor` + cloudflared dari repo Cloudflare.

## 2. Database

```sql
CREATE DATABASE booking_ac CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'booking_ac'@'localhost' IDENTIFIED BY '<password-kuat>';
GRANT ALL PRIVILEGES ON booking_ac.* TO 'booking_ac'@'localhost';
```

## 3. Kode aplikasi

```bash
git clone <repo> ~/booking-ac && cd ~/booking-ac
cp .env.production.example .env        # isi semua nilai <...>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # data awal + akun admin; DemoSeeder hanya jalan di APP_ENV=local
                              # → SEGERA ganti password admin: php artisan user:password admin@booking-ac.test
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

> Jangan jalankan `migrate:fresh` / `db:wipe` di produksi.

Izin folder: user yang menjalankan PHP-FPM harus bisa menulis `storage/` dan `bootstrap/cache/`.

## 4. Nginx

Salin `deploy/nginx/booking-ac.conf` ke `/opt/homebrew/etc/nginx/servers/`, sesuaikan `root`, lalu:

```bash
nginx -t && brew services restart nginx
curl -I http://127.0.0.1:8080        # harus 302 ke /masuk
```

## 5. Cloudflare Tunnel (HTTPS)

1. Domain sudah memakai DNS Cloudflare.
2. ```bash
   cloudflared tunnel login
   cloudflared tunnel create booking-ac
   cloudflared tunnel route dns booking-ac <domain-anda>
   ```
3. Salin `deploy/cloudflared/config.yml.example` → `~/.cloudflared/config.yml`, isi `TUNNEL-ID` & domain.
4. Jalankan sebagai service agar hidup otomatis: `sudo cloudflared service install`.
5. Di dashboard Cloudflare → SSL/TLS: mode **Full**; aktifkan **Always Use HTTPS**.

Aplikasi sudah dikonfigurasi untuk skema ini: proxy lokal dipercaya (`trustProxies` 127.0.0.1) sehingga IP client
(untuk rate limit) & HTTPS terbaca benar, URL dipaksa `https` di produksi, cookie `SESSION_SECURE_COOKIE=true`,
dan HSTS dikirim otomatis saat diakses lewat HTTPS.

## 6. Queue worker & scheduler

Notifikasi email & hitung ulang jarak memakai antrean; backup & pengingat memakai scheduler.

```bash
sed "s#/Users/USER#$HOME#g" deploy/macos/id.booking-ac.queue.plist     > ~/Library/LaunchAgents/id.booking-ac.queue.plist
sed "s#/Users/USER#$HOME#g" deploy/macos/id.booking-ac.scheduler.plist > ~/Library/LaunchAgents/id.booking-ac.scheduler.plist
launchctl load -w ~/Library/LaunchAgents/id.booking-ac.queue.plist
launchctl load -w ~/Library/LaunchAgents/id.booking-ac.scheduler.plist
```
*Linux:* `deploy/linux/booking-ac-worker.conf` (Supervisor) + baris di `deploy/linux/crontab`.

Setiap deploy ulang: `php artisan queue:restart` agar worker memuat kode baru.

## 7. Backup database harian

`php artisan backup:database` berjalan otomatis **tiap hari 02:00** (scheduler), menyimpan
`storage/app/private/backups/booking_ac-YYYYMMDD-HHMMSS.sql.gz` dan menghapus backup > 14 hari.
Salin folder itu secara berkala ke tempat lain (Google Drive / hard disk eksternal) — backup di komputer yang sama
tidak melindungi dari kerusakan disk. Pulihkan: `gunzip -c file.sql.gz | mysql -u booking_ac -p booking_ac`.

## 8. Google login produksi

Firebase Console → Authentication → Settings → **Authorized domains** → tambahkan `<domain-anda>`
(tanpa `https://`). Pastikan provider Google sudah aktif di Sign-in method. Isi `FIREBASE_API_KEY`,
`FIREBASE_AUTH_DOMAIN`, `FIREBASE_PROJECT_ID`, `FIREBASE_APP_ID` di `.env`, lalu `php artisan config:cache`.
Tidak perlu redirect URI manual dan tidak perlu file service account.

## 9. Deploy ulang (update kode)

```bash
cd ~/booking-ac
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart
php artisan up
```

## Checklist sebelum go-live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
- [ ] Password admin bawaan sudah diganti (`php artisan user:password admin@booking-ac.test`)
- [ ] Identitas perusahaan, titik kantor, rekening diisi di **Pengaturan** (T-004)
- [ ] Login Google diuji di domain produksi
- [ ] Upload foto & bukti bayar berhasil (cek `storage:link`)
- [ ] `launchctl list | grep booking-ac` menampilkan queue & scheduler
- [ ] Backup muncul di `storage/app/private/backups` keesokan harinya
