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
php artisan migrate

# 4. Jalankan
npm run dev          # Vite
php artisan serve    # http://127.0.0.1:8000 (atau lewat Herd: http://booking-ac.test)
```

Sesuaikan `DB_USERNAME` / `DB_PASSWORD` di `.env` bila MySQL Anda memakai password.

## Test & kualitas kode

Test memakai database MySQL terpisah `booking_ac_test` (lihat `phpunit.xml`).

```bash
composer test      # Pest
composer lint      # Laravel Pint (format otomatis)
composer analyse   # Larastan level 5
```
