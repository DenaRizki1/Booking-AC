# Booking AC — Panduan untuk Claude

Aplikasi web booking servis AC dengan 3 interface: **Client** (login Google), **Admin**, **Teknisi**.

## Dokumen acuan (baca sebelum mengerjakan task)
- `docs/TASKS.md` — daftar task development (T-xxx), dependensi & kriteria selesai
- `docs/WORKFLOW.md` — aturan bisnis, state machine status order, flowchart
- `docs/ERD.md` — skema database lengkap (kamus data, index, query kunci)
- `mockup/*.html` — desain UI acuan (buka `mockup/index.html`); `mockup/StyleGuide.html` untuk warna & tipografi

## Stack
Laravel 12 · Blade + Tailwind CSS v4 + Alpine.js · MySQL 8 · Leaflet (OpenStreetMap) · Pest · Notifikasi database + mail
Dev lokal: Laravel Herd (`http://booking-ac.test`), database `booking_ac`.

## Alur kerja saat diminta "Kerjakan T-xxx"
1. Baca detail task di `docs/TASKS.md`; pastikan task di kolom "Butuh" sudah dicentang. Jika belum, beri tahu dan jangan lanjut tanpa persetujuan.
2. Kerjakan hanya lingkup task itu. Jika menemukan hal yang perlu diputuskan (aturan bisnis tidak jelas), tanyakan.
3. Tulis/perbarui test (Pest) sesuai poin "Selesai jika".
4. Jalankan: `php artisan test`, `vendor/bin/pint`, dan (setelah T-104) `composer analyse`. Semua harus lulus.
5. Centang task di `docs/TASKS.md` (`[ ]` → `[x]`) dan di bagian ringkasan.
6. Commit: `T-xxx: <judul task>`. Satu task = satu commit.
7. Jika skema atau aturan berubah, perbarui `docs/ERD.md` / `docs/WORKFLOW.md` di commit yang sama.

## Konvensi kode
- Nama tabel/kolom/kelas/variabel dalam **bahasa Inggris** (sesuai ERD); teks UI, pesan validasi, dan email dalam **Bahasa Indonesia**.
- Nilai enum persis seperti ERD bagian 7 (`OrderStatus::MENUNGGU`, dst.), didefinisikan di `app/Enums`.
- Logika bisnis di `app/Services` (DistanceService, OrderEstimator, ScheduleService, OrderStatusService); controller tetap tipis.
- Perubahan status order **hanya** lewat `OrderStatusService` (mengisi timestamp, menulis `order_status_logs`, memancarkan event).
- Validasi input di `FormRequest`; akses data lewat Policy.
- Uang = integer rupiah; durasi = menit; koordinat = DECIMAL(10,7); timezone `Asia/Jakarta`.
- Snapshot harga/alamat/nama layanan ke `orders`/`order_items` saat order dibuat.
- UI mengikuti mockup & komponen Blade di `resources/views/components`; target sentuh ≥ 44px; ikon SVG (Lucide), tanpa emoji.

## Larangan
- Jangan commit `.env`, kredensial Google/SMTP, atau file di `vendor/`, `node_modules/`.
- Jangan jalankan `migrate:fresh` / `db:wipe` selain di environment lokal.
- Jangan mengubah struktur folder `docs/` dan `mockup/`.
- Folder `Claude outputs/` hanya salinan hasil sesi Cowork: abaikan dan masukkan ke `.gitignore` (di T-101).

## Catatan T-101
Folder proyek sudah berisi `docs/`, `mockup/`, dan `CLAUDE.md`, jadi `composer create-project` tidak bisa langsung ke folder ini. Buat project di folder sementara, lalu pindahkan isinya ke root proyek tanpa menimpa ketiga item tersebut.
