# Booking AC

Aplikasi web booking servis AC dengan 3 interface: **Client** (login Google), **Admin**, dan **Teknisi**.

Dokumen perencanaan: [docs/WORKFLOW.md](docs/WORKFLOW.md) · [docs/ERD.md](docs/ERD.md) · [docs/TASKS.md](docs/TASKS.md) · mockup UI di [mockup/index.html](mockup/index.html).

## Stack

Laravel 12 · Blade + Tailwind CSS v4 + Alpine.js · MySQL 8+ · Leaflet (OpenStreetMap) · Pest

## Kebutuhan

- PHP ≥ 8.2 (ekstensi `pdo_mysql`), Composer 2
- Node.js ≥ 20 + npm
- MySQL 8+ (Homebrew / DBngin / Herd Pro)
- Opsional: Laravel Herd agar aplikasi terbuka di `http://booking-ac.test`

## Setup lokal

```bash
# 1. Dependensi
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (utf8mb4)
mysql -u root -e "CREATE DATABASE booking_ac CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE booking_ac_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed   # data awal (+ data demo bila APP_ENV=local)

# 4. Jalankan
npm run dev          # Vite
php artisan serve    # http://127.0.0.1:8000 (atau lewat Herd: http://booking-ac.test)
```

Sesuaikan `DB_USERNAME` / `DB_PASSWORD` di `.env` bila MySQL Anda memakai password.
Reset database lokal kapan saja: `php artisan migrate:fresh --seed` (**hanya di lokal**).

## Akun login

| Peran | Email | Password | Keterangan |
|---|---|---|---|
| Admin | `admin@booking-ac.test` | `password` | Dibuat `AdminSeeder` di semua environment — **ganti password di produksi** |
| Teknisi (demo) | `rudi@`, `ahmad@`, `dimas@`, `yoga@booking-ac.test` | `password` | Hanya lokal (`DemoSeeder`) |
| Client (demo) | mis. `dena.rizki@example.com` | – | Client masuk lewat Google; akun demo hanya untuk data |

Data demo: 4 teknisi, 2 helper, 5 client, 20 order (semua status, termasuk 1 klaim garansi) dengan jadwal relatif terhadap hari ini.
Identitas perusahaan masih placeholder **SejukAC** (T-004) dan bisa diubah di menu Pengaturan admin.

## Login Google (client)

1. Buat OAuth Client ID (Web) di Google Cloud Console (T-002).
2. Authorized redirect URI: `http://127.0.0.1:8000/auth/google/callback` (atau `http://booking-ac.test/auth/google/callback` bila memakai Herd).
3. Isi di `.env`: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, dan `GOOGLE_REDIRECT_URI` bila berbeda dari `APP_URL/auth/google/callback`.

Sebelum kredensial diisi, di `APP_ENV=local` halaman `/masuk` menampilkan tombol **masuk sebagai client demo** (tanpa Google) untuk mencoba tampilan client.

## Test & kualitas kode

Test memakai database MySQL terpisah `booking_ac_test` (lihat `phpunit.xml`).

```bash
composer test      # Pest
composer lint      # Laravel Pint (format otomatis)
composer analyse   # Larastan level 5
```
