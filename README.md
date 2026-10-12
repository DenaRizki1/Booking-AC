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

Login memakai **Firebase Authentication** (popup Google di browser, ID token diverifikasi server tanpa service account).

1. Firebase Console → buat project → Authentication → Sign-in method → aktifkan **Google** (T-002).
2. Project settings → Your apps → daftarkan Web app, salin `firebaseConfig`.
3. Authentication → Settings → Authorized domains: tambahkan domain/host yang dipakai (`localhost` sudah ada; tambahkan `127.0.0.1` bila perlu).
4. Isi di `.env`: `FIREBASE_API_KEY`, `FIREBASE_AUTH_DOMAIN`, `FIREBASE_PROJECT_ID`, `FIREBASE_APP_ID` (nilai publik, bukan rahasia), lalu `php artisan config:clear`.

Sebelum kredensial diisi, di `APP_ENV=local` halaman `/masuk` menampilkan tombol **masuk sebagai client demo** (tanpa Google) untuk mencoba tampilan client.

## Deploy produksi

Panduan lengkap (komputer sendiri + Cloudflare Tunnel, Nginx, queue worker, scheduler, backup harian): [deploy/README.md](deploy/README.md).
Contoh environment produksi: [`.env.production.example`](.env.production.example).

## Test & kualitas kode

Test memakai database MySQL terpisah `booking_ac_test` (lihat `phpunit.xml`).

```bash
composer test      # Pest
composer lint      # Laravel Pint (format otomatis)
composer analyse   # Larastan level 5
```
