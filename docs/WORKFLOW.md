# Booking AC — Workflow & Flowchart

Dokumen perencanaan aplikasi web booking servis AC dengan 3 interface: **Client**, **Admin**, dan **Teknisi**.

**Tech stack:** Laravel 12 (Blade) · MySQL · Leaflet (OpenStreetMap) · Notifikasi email

> Flowchart ditulis dengan Mermaid — tampil otomatis di GitHub/GitLab, VS Code (ekstensi *Markdown Preview Mermaid Support*), atau https://mermaid.live.

---

## 1. Aktor & Hak Akses

| Aktor | Login | Tugas utama |
|---|---|---|
| **Client** | Google (OAuth) | Kelola alamat, buat order (multi unit), pantau status, lihat laporan, beri rating & ulasan, klaim garansi |
| **Admin** | Email + password | Kelola master data & pengaturan, terima/tolak order, assign teknisi + helper, konfirmasi pembayaran |
| **Teknisi** | Email/username + password (akun dibuat admin) | Terima tugas, update status pengerjaan, kirim laporan foto + catatan |
| **Helper** | *Tidak punya akun* | Hanya data master; dipilih admin untuk mendampingi teknisi |

---

## 2. Aturan Bisnis

### Order & unit
1. Client wajib punya minimal 1 alamat (dengan titik koordinat) sebelum bisa order.
2. **Satu order bisa berisi lebih dari 1 unit AC.** Setiap unit punya: merk, PK, tipe (Inverter / Low Watt / Standar), dan layanan masing-masing.
3. Satu order = satu alamat + satu jadwal (tanggal & jam mulai). Semua unit dikerjakan berurutan oleh tim yang sama.
4. Helper input client:
   - **Salin unit** — duplikat unit sebelumnya (mis. 3 AC kamar yang sama).
   - **Terapkan ke semua unit** — ubah layanan/merk/PK/tipe sekaligus untuk semua unit di order.

### Harga & durasi (berbeda per PK & tipe)
5. Harga dan estimasi durasi disimpan per kombinasi **Layanan × PK × Tipe** (tabel harga layanan).
6. Setiap kombinasi punya: `harga_mulai`, `harga_sampai`, `estimasi_jam`.
7. Kombinasi yang belum diberi harga / nonaktif → layanan **tidak tampil** untuk unit tersebut.
8. Helper input admin di matriks harga:
   - **Terapkan ke semua** — isi 1 nilai ke seluruh kombinasi PK × Tipe untuk layanan itu.
   - **Terapkan ke baris / kolom** — isi 1 nilai untuk semua tipe di PK tertentu, atau semua PK di tipe tertentu.
   - Setelah itu admin tinggal mengedit sel yang berbeda.
9. Estimasi order:
   - Harga = Σ harga unit (`Σ harga_mulai – Σ harga_sampai`) + biaya transport (1× per order)
   - Durasi = Σ `estimasi_jam` semua unit

### Jarak (koordinat Company Profile → koordinat alamat client)
10. `jarak ≤ radius_gratis` → transport Rp0.
11. `radius_gratis < jarak ≤ radius_maks` → transport = `(jarak − radius_gratis) × tarif_per_km`.
12. `jarak > radius_maks` → **order tidak bisa dibuat** (di luar jangkauan).
13. Jarak dihitung dengan rumus Haversine (garis lurus). Bisa di-upgrade ke OSRM / routing jarak jalan nanti.

### Tim & jadwal
14. Satu order dikerjakan **1 teknisi** (pakai aplikasi) + **opsional 1 helper** (tanpa aplikasi).
15. Layanan bisa ditandai **"wajib helper"** (mis. Instalasi, Bongkar Pasang). Jika ada unit dengan layanan tersebut, admin wajib memilih helper.
16. Admin boleh menumpuk beberapa order untuk satu teknisi/helper di hari yang sama, **asalkan jam tidak bentrok**.
17. Rentang waktu order = `jam_mulai` s/d `jam_mulai + Σ estimasi_jam + buffer_perjalanan`.
18. Teknisi/helper dianggap **free** jika: aktif, tidak cuti, dan tidak punya order lain yang rentang waktunya beririsan.
19. Saat client memilih jam, sistem hanya menampilkan slot di mana **minimal 1 teknisi free** (dan 1 helper free bila order butuh helper), dalam jam operasional.

### Pembatalan
20. Client bisa membatalkan order sendiri **paling lambat 3 jam sebelum jadwal** (status MENUNGGU / DITUGASKAN / DIKONFIRMASI). Nilai 3 jam diatur di Pengaturan.
21. Lewat dari batas itu, tombol batal hilang → client harus menghubungi admin; hanya admin yang bisa membatalkan.

### Pembayaran
22. Bayar **setelah pekerjaan selesai** (tunai atau transfer).
23. Teknisi mengisi **harga final per unit** di laporan (boleh di luar estimasi jika ada sparepart/tambahan, wajib diberi keterangan).
24. Admin menandai order **Lunas** setelah menerima pembayaran.

### Laporan, ulasan & garansi
25. Laporan teknisi: foto (min. 1 per unit, sebelum/sesudah) + catatan (opsional).
26. Laporan tampil di halaman detail order client.
27. Client memberi **rating 1–5 + ulasan** setelah order selesai (1× per order). Rating teknisi = rata-rata semua ulasannya.
28. **Garansi** berlaku **7 hari** sejak order SELESAI (default; bisa diubah admin di Pengaturan, dan bisa di-override per layanan).
29. Selama masa garansi, client bisa **Klaim Garansi** untuk unit tertentu → dibuat **order garansi** (terhubung ke order asal) dengan biaya layanan & transport **Rp0**, lalu masuk alur assign seperti biasa (disarankan teknisi yang sama).

---

## 3. Status Order (State Machine)

```mermaid
stateDiagram-v2
    [*] --> MENUNGGU: Client simpan order / klaim garansi
    MENUNGGU --> DITUGASKAN: Admin assign teknisi (+ helper)
    MENUNGGU --> DITOLAK: Admin tolak
    MENUNGGU --> DIBATALKAN: Client batal (≥ 3 jam sebelum jadwal)
    DITUGASKAN --> DIKONFIRMASI: Teknisi terima tugas
    DITUGASKAN --> MENUNGGU: Teknisi tolak / admin ganti teknisi
    DITUGASKAN --> DIBATALKAN: Client (≥ 3 jam) / Admin
    DIKONFIRMASI --> DIBATALKAN: Client (≥ 3 jam) / Admin
    DIKONFIRMASI --> DALAM_PERJALANAN: Teknisi berangkat
    DALAM_PERJALANAN --> DIKERJAKAN: Teknisi tiba & mulai
    DIKERJAKAN --> SELESAI: Teknisi kirim laporan + harga final
    SELESAI --> LUNAS: Admin konfirmasi pembayaran
    SELESAI --> [*]: Order garansi (Rp0) langsung tutup
    LUNAS --> [*]
    DITOLAK --> [*]
    DIBATALKAN --> [*]
```

| Status | Siapa yang mengubah | Keterangan |
|---|---|---|
| MENUNGGU | Sistem | Order baru / klaim garansi, menunggu admin |
| DITUGASKAN | Admin | Teknisi (+ helper) sudah dipilih |
| DIKONFIRMASI | Teknisi | Teknisi menerima tugas |
| DALAM_PERJALANAN | Teknisi | Menuju lokasi client |
| DIKERJAKAN | Teknisi | Pekerjaan berlangsung |
| SELESAI | Teknisi | Laporan terkirim; masa garansi mulai dihitung |
| LUNAS | Admin | Pembayaran diterima |
| DITOLAK / DIBATALKAN | Admin / Client | Order berakhir tanpa dikerjakan |

> Ulasan boleh diberikan sejak status **SELESAI**.

---

## 4. Flowchart Client

### 4.1 Login & Alamat

```mermaid
flowchart TD
    A([Buka aplikasi]) --> B[Login dengan Google]
    B --> C{Akun sudah ada?}
    C -- Belum --> D[Buat akun client otomatis<br/>nama, email, foto dari Google]
    C -- Sudah --> E[Masuk dashboard]
    D --> F[Lengkapi no. HP]
    F --> E
    E --> G{Sudah punya alamat?}
    G -- Belum --> H[Tambah alamat]
    H --> I["Pilih titik di peta Leaflet / GPS<br/>(lat, lng)"]
    I --> J[Isi detail alamat, label, patokan]
    J --> K[Hitung jarak ke kantor]
    K --> L{Jarak ≤ radius maks?}
    L -- Tidak --> M[Tampilkan peringatan<br/>alamat di luar jangkauan]
    M --> H
    L -- Ya --> N[Simpan alamat]
    N --> O([Siap order])
    G -- Sudah --> O
```

### 4.2 Membuat Order (Multi Unit)

```mermaid
flowchart TD
    A([Klik Buat Order]) --> B{Punya alamat valid?}
    B -- Tidak --> B1[Arahkan ke Tambah Alamat] --> B
    B -- Ya --> C[Pilih alamat]
    C --> D[Tambah unit AC]
    D --> E[Pilih merk, PK, tipe]
    E --> F["Pilih layanan<br/>(hanya yang punya harga utk PK × tipe ini)"]
    F --> G[Tampilkan estimasi harga & durasi unit]
    G --> H{Tambah unit lagi?}
    H -- "Ya, unit baru" --> D
    H -- "Ya, salin unit" --> H1[Duplikat unit terakhir] --> G
    H -- Tidak --> I{"Pakai helper<br/>Terapkan ke semua unit?"}
    I -- Ya --> I1[Set layanan/merk/PK/tipe<br/>ke semua unit sekaligus] --> I2[Hitung ulang estimasi] --> J
    I -- Tidak --> J[Pilih tanggal]
    J --> K["Sistem cek slot jam dengan teknisi free<br/>(+ helper bila perlu),<br/>durasi = Σ estimasi_jam + buffer"]
    K --> L{Ada slot tersedia?}
    L -- Tidak --> J
    L -- Ya --> M[Pilih jam]
    M --> N[Isi catatan keluhan - opsional]
    N --> O["Ringkasan: daftar unit, Σ estimasi harga,<br/>transport, total durasi"]
    O --> P{Konfirmasi?}
    P -- Ubah --> D
    P -- Ya --> Q[Simpan order + item unit<br/>status = MENUNGGU]
    Q --> R[Notifikasi ke admin]
    R --> S([Halaman detail order])
```

### 4.3 Pantau Order, Batal, Laporan & Ulasan

```mermaid
flowchart TD
    A([Menu Order Saya]) --> B[Daftar order + status]
    B --> C[Buka detail order]
    C --> D{Status?}
    D -- "MENUNGGU / DITUGASKAN / DIKONFIRMASI" --> E{"Sekarang ≤ jadwal − 3 jam?"}
    E -- Ya --> E1[Tombol Batalkan aktif] --> E2[Isi alasan → DIBATALKAN]
    E -- Tidak --> E3[Info: hubungi admin untuk batal]
    D -- "DITUGASKAN s/d DIKERJAKAN" --> F[Lihat teknisi, helper,<br/>progres status]
    D -- "SELESAI / LUNAS" --> G[Lihat laporan per unit:<br/>foto, catatan, harga final]
    G --> H{Sudah beri ulasan?}
    H -- Belum --> I[Beri rating 1-5 + ulasan]
    I --> J[Simpan → update rating teknisi]
    H -- Sudah --> K[Tampilkan ulasan]
    G --> L{Masih masa garansi?}
    L -- Ya --> M([Tombol Klaim Garansi → lihat 7.])
    L -- Tidak --> N[Tampilkan: garansi berakhir]
```

---

## 5. Flowchart Admin

### 5.1 Terima & Assign Order

```mermaid
flowchart TD
    A([Notifikasi order baru]) --> B[Buka daftar order MENUNGGU]
    B --> C[Lihat detail: daftar unit, layanan,<br/>alamat + peta, jarak, jadwal, total durasi]
    C --> D{Order valid?}
    D -- Tidak --> E[Tolak + alasan<br/>status = DITOLAK]
    E --> Z([Notifikasi client])
    D -- Ya --> F["Sistem tampilkan teknisi FREE<br/>pada rentang jam order"]
    F --> G{Ada teknisi free?}
    G -- Tidak --> H[Hubungi client untuk<br/>reschedule tanggal/jam]
    H --> C
    G -- Ya --> I["Pilih teknisi<br/>(order garansi: sarankan teknisi order asal)"]
    I --> J{Ada unit dengan layanan<br/>wajib helper?}
    J -- Ya --> K[Pilih helper free - wajib]
    J -- Tidak --> K2[Pilih helper - opsional]
    K --> L
    K2 --> L{"Cek bentrok jadwal teknisi & helper<br/>(validasi server)"}
    L -- Bentrok --> F
    L -- Aman --> M[Simpan assignment<br/>status = DITUGASKAN]
    M --> N([Notifikasi teknisi & client])
```

### 5.2 Konfirmasi Pembayaran

```mermaid
flowchart TD
    A([Order status SELESAI]) --> B{Order garansi?}
    B -- Ya --> B1([Tidak ada tagihan, order ditutup])
    B -- Tidak --> C[Cek laporan & harga final per unit]
    C --> D{Pembayaran diterima?}
    D -- Belum --> E[Tunggu / follow up client]
    E --> D
    D -- Ya --> F["Pilih metode (tunai / transfer)<br/>+ upload bukti - opsional"]
    F --> G[Status = LUNAS]
    G --> H([Client bisa lihat status lunas])
```

### 5.3 Input Matriks Harga Layanan (dengan helper)

```mermaid
flowchart TD
    A([Master Layanan → pilih layanan]) --> B["Tampil matriks harga<br/>baris = PK, kolom = Tipe"]
    B --> C{Pilih cara isi}
    C -- Terapkan ke semua --> D["Isi harga_mulai, harga_sampai, estimasi_jam<br/>→ semua sel terisi"]
    C -- Terapkan ke baris PK --> E[Isi 1 nilai → semua tipe di PK itu]
    C -- Terapkan ke kolom tipe --> F[Isi 1 nilai → semua PK di tipe itu]
    C -- Per sel --> G[Edit sel satu per satu]
    D --> H[Edit sel yang berbeda]
    E --> H
    F --> H
    G --> H
    H --> I{"Validasi: harga_mulai ≤ harga_sampai,<br/>estimasi_jam &gt; 0"}
    I -- Gagal --> H
    I -- OK --> J[Centang aktif/nonaktif per sel]
    J --> K([Simpan matriks])
```

Contoh matriks **Cuci AC** (harga dalam ribuan Rp · jam):

| PK \ Tipe | Standar | Low Watt | Inverter |
|---|---|---|---|
| 0.5 – 1 PK | 75–90 · 1 | 75–90 · 1 | 90–110 · 1 |
| 1.5 PK | 90–110 · 1 | 90–110 · 1 | 110–130 · 1.5 |
| 2 PK | 110–130 · 1.5 | 110–130 · 1.5 | 130–160 · 1.5 |

### 5.4 Master Data & Pengaturan

- **Company Profile** — nama, alamat, titik koordinat (pilih di peta Leaflet), jam operasional
- **Pengaturan** — radius gratis, radius maks, tarif/km, buffer perjalanan, **batas pembatalan (default 3 jam)**, **masa garansi default (7 hari)**
- **Layanan** — nama, deskripsi, wajib helper (ya/tidak), garansi hari (kosong = pakai default), aktif, **matriks harga PK × Tipe**
- **Merk AC** — Daikin, Panasonic, LG, Sharp, Gree, dll.
- **PK AC** — 0.5, 0.75, 1, 1.5, 2, 2.5 …
- **Tipe AC** — Inverter, Low Watt, Standar
- **Teknisi** — nama, no. HP, foto, akun login, aktif, hari cuti
- **Helper** — nama, no. HP, aktif, hari cuti (tanpa akun login)
- **Client** — lihat daftar client & alamatnya (read-only / nonaktifkan)

---

## 6. Flowchart Teknisi

```mermaid
flowchart TD
    A([Login teknisi]) --> B["Daftar tugas<br/>(hari ini & mendatang, urut jam)"]
    B --> C[Buka detail tugas: client, alamat, peta,<br/>daftar unit & layanan, nama helper]
    C --> D{Status DITUGASKAN}
    D -- Tolak + alasan --> E[Kembali ke MENUNGGU<br/>notifikasi admin]
    D -- Terima --> F[Status = DIKONFIRMASI]
    F --> G[Hari H: klik Berangkat<br/>status = DALAM_PERJALANAN]
    G --> H[Buka navigasi Google Maps / OSM]
    H --> I[Tiba, klik Mulai<br/>status = DIKERJAKAN]
    I --> J[Kerjakan unit berikutnya]
    J --> K["Laporan unit: foto sebelum & sesudah (min. 1),<br/>catatan - opsional,<br/>harga final + keterangan tambahan"]
    K --> L{Masih ada unit?}
    L -- Ya --> J
    L -- Tidak --> M[Review total harga final]
    M --> N[Kirim laporan<br/>status = SELESAI]
    N --> O([Notifikasi client & admin])
```

---

## 7. Flowchart Klaim Garansi

```mermaid
flowchart TD
    A([Client buka order SELESAI / LUNAS]) --> B{"Hari ini ≤ tanggal_selesai + garansi_hari?"}
    B -- Tidak --> X([Garansi berakhir])
    B -- Ya --> C[Klik Klaim Garansi]
    C --> D[Pilih unit yang bermasalah]
    D --> E[Isi keluhan + foto - opsional]
    E --> F[Pilih tanggal & jam]
    F --> G["Buat order baru jenis GARANSI<br/>parent_order_id = order asal<br/>harga & transport = Rp0"]
    G --> H[Status = MENUNGGU → notifikasi admin]
    H --> I[Admin assign - sarankan teknisi order asal]
    I --> J[Alur teknisi sama seperti order biasa]
    J --> K([SELESAI → order garansi ditutup, tanpa tagihan])
```

> Jika saat pengecekan ternyata kerusakan **bukan** tanggung jawab garansi, teknisi bisa mengisi harga final > 0 dengan keterangan, dan order diperlakukan seperti order biasa (butuh konfirmasi LUNAS).

---

## 8. Alur End-to-End (Sequence)

```mermaid
sequenceDiagram
    autonumber
    actor C as Client
    participant S as Sistem
    actor A as Admin
    actor T as Teknisi

    C->>S: Login Google
    C->>S: Tambah alamat + titik koordinat
    S-->>C: Jarak OK (dalam radius)
    C->>S: Tambah unit 1..n (merk, PK, tipe, layanan)
    S-->>C: Estimasi per unit + total + transport + durasi
    C->>S: Pilih tanggal & jam → simpan order
    S->>A: Notifikasi order baru (MENUNGGU)
    A->>S: Cek teknisi & helper free
    A->>S: Assign teknisi (+ helper)
    S->>T: Notifikasi tugas baru (DITUGASKAN)
    S->>C: Info teknisi & helper
    T->>S: Terima tugas (DIKONFIRMASI)
    T->>S: Berangkat → Mulai kerja
    S->>C: Update status
    T->>S: Laporan per unit: foto + catatan + harga final (SELESAI)
    S->>C: Laporan tersedia, garansi 7 hari aktif
    C->>T: Bayar (tunai/transfer)
    A->>S: Konfirmasi pembayaran (LUNAS)
    C->>S: Rating + ulasan
    S->>S: Update rata-rata rating teknisi
    opt Dalam masa garansi
        C->>S: Klaim garansi (order GARANSI, Rp0)
        S->>A: Notifikasi klaim garansi
    end
```

---

## 9. Logika Cek Jadwal Bentrok

Dipakai untuk teknisi **dan** helper, di dua tempat: (1) menampilkan slot jam ke client, (2) validasi saat admin assign.

```mermaid
flowchart TD
    A([Input: orang, tanggal, jam_mulai, daftar unit]) --> B["durasi = Σ estimasi_jam unit<br/>mulai_baru = jam_mulai<br/>selesai_baru = jam_mulai + durasi + buffer"]
    B --> C{Aktif & tidak cuti<br/>di tanggal tsb?}
    C -- Tidak --> X([TIDAK FREE])
    C -- Ya --> D["Ambil order orang tsb di tanggal itu<br/>(status bukan DITOLAK/DIBATALKAN)"]
    D --> E{"Ada order dengan<br/>mulai_baru &lt; selesai_lama<br/>DAN selesai_baru &gt; mulai_lama ?"}
    E -- Ya --> X
    E -- Tidak --> F{selesai_baru ≤ jam tutup?}
    F -- Tidak --> X
    F -- Ya --> Y([FREE])
```

Contoh (buffer 30 menit):

| Order | Isi | Jam | Rentang terkunci |
|---|---|---|---|
| #1 | 2 unit cuci AC (2 × 1 jam) | 09:00 | 09:00 – 11:30 |
| #2 | 1 unit perbaikan (2 jam) | 11:30 | 11:30 – 14:00 ✅ tidak bentrok |
| #3 | 1 unit cuci AC (1 jam) | 13:00 | ❌ bentrok dengan #2 |

Contoh query MySQL (cek bentrok teknisi):

```sql
SELECT COUNT(*) FROM orders
WHERE technician_id = :tech_id
  AND schedule_date = :date
  AND status NOT IN ('DITOLAK','DIBATALKAN')
  AND start_time < :new_end
  AND end_time   > :new_start;
-- > 0 berarti bentrok
```

---

## 10. Perhitungan Jarak & Biaya Transport

```mermaid
flowchart TD
    A([Koordinat kantor & alamat client]) --> B[Hitung jarak - Haversine, km]
    B --> C{jarak > radius_maks?}
    C -- Ya --> D([Di luar jangkauan - order diblokir])
    C -- Tidak --> E{jarak ≤ radius_gratis?}
    E -- Ya --> F([Transport = Rp0])
    E -- Tidak --> G(["Transport = (jarak − radius_gratis) × tarif_per_km<br/>dibulatkan ke atas per km"])
```

Contoh order 2 unit:

| Unit | Layanan | Estimasi |
|---|---|---|
| Daikin 1 PK Standar | Cuci AC | Rp75.000 – Rp90.000 |
| LG 1.5 PK Inverter | Cuci AC | Rp110.000 – Rp130.000 |
| Transport (12 km, gratis 5 km, Rp3.000/km) | | Rp21.000 |
| **Total estimasi** | | **Rp206.000 – Rp241.000** |

> Harga, estimasi jam, jarak, dan transport di-**snapshot** ke order/item saat order dibuat, supaya tidak berubah jika master harga/pengaturan diubah kemudian.

---

## 11. Struktur Data (ERD — MySQL)

> **Versi lengkap & terbaru ada di [ERD.md](ERD.md)** (kamus data, index, query kunci). Diagram di bawah hanya ringkasan awal.

```mermaid
erDiagram
    USERS ||--o{ CUSTOMER_ADDRESSES : punya
    USERS ||--o{ ORDERS : membuat
    USERS ||--o| TECHNICIANS : "profil teknisi"
    TECHNICIANS ||--o{ ORDERS : ditugaskan
    HELPERS ||--o{ ORDERS : mendampingi
    TECHNICIANS ||--o{ STAFF_LEAVES : cuti
    HELPERS ||--o{ STAFF_LEAVES : cuti
    SERVICES ||--o{ SERVICE_PRICES : harga
    AC_CAPACITIES ||--o{ SERVICE_PRICES : ""
    AC_TYPES ||--o{ SERVICE_PRICES : ""
    CUSTOMER_ADDRESSES ||--o{ ORDERS : lokasi
    ORDERS ||--|{ ORDER_ITEMS : berisi
    SERVICES ||--o{ ORDER_ITEMS : ""
    AC_BRANDS ||--o{ ORDER_ITEMS : ""
    AC_CAPACITIES ||--o{ ORDER_ITEMS : ""
    AC_TYPES ||--o{ ORDER_ITEMS : ""
    ORDERS ||--o{ ORDERS : "klaim garansi"
    ORDERS ||--o| WORK_REPORTS : laporan
    WORK_REPORTS ||--o{ REPORT_PHOTOS : foto
    ORDER_ITEMS ||--o{ REPORT_PHOTOS : "foto unit"
    ORDERS ||--o| REVIEWS : ulasan
    ORDERS ||--o{ ORDER_STATUS_LOGS : riwayat

    USERS {
        bigint id PK
        string name
        string email UK
        string google_id "null utk admin/teknisi"
        string phone
        enum role "client|admin|teknisi"
        string password "null utk client"
    }
    SETTINGS {
        bigint id PK
        string company_name
        text address
        decimal lat "DECIMAL(10,7)"
        decimal lng "DECIMAL(10,7)"
        time open_time
        time close_time
        decimal free_radius_km
        decimal max_radius_km
        int fee_per_km
        int travel_buffer_min
        int cancel_limit_hours "default 3"
        int warranty_days "default 7"
    }
    CUSTOMER_ADDRESSES {
        bigint id PK
        bigint user_id FK
        string label
        text address
        string landmark
        decimal lat
        decimal lng
        decimal distance_km
        bool is_default
    }
    SERVICES {
        bigint id PK
        string name
        text description
        bool requires_helper
        int warranty_days "null = pakai default"
        bool is_active
    }
    SERVICE_PRICES {
        bigint id PK
        bigint service_id FK
        bigint capacity_id FK
        bigint type_id FK
        int price_min
        int price_max
        decimal est_hours
        bool is_active
    }
    TECHNICIANS {
        bigint id PK
        bigint user_id FK
        string phone
        string photo
        bool is_active
        decimal avg_rating
    }
    HELPERS {
        bigint id PK
        string name
        string phone
        bool is_active
    }
    STAFF_LEAVES {
        bigint id PK
        string staff_type "technician|helper"
        bigint staff_id
        date date
    }
    ORDERS {
        bigint id PK
        string code UK
        enum order_type "REGULER|GARANSI"
        bigint parent_order_id FK "null jika reguler"
        bigint user_id FK
        bigint address_id FK
        bigint technician_id FK
        bigint helper_id FK
        date schedule_date
        time start_time
        time end_time
        decimal total_est_hours
        int est_price_min
        int est_price_max
        decimal distance_km
        int transport_fee
        int final_price
        text customer_note
        enum status
        text cancel_reason
        datetime completed_at
        enum payment_status "BELUM|LUNAS|TIDAK_PERLU"
        enum payment_method "TUNAI|TRANSFER"
    }
    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        bigint brand_id FK
        bigint capacity_id FK
        bigint type_id FK
        bigint service_id FK
        int est_price_min
        int est_price_max
        decimal est_hours
        int final_price
        text price_note
        text work_note
        date warranty_until
    }
    WORK_REPORTS {
        bigint id PK
        bigint order_id FK
        text note
        datetime submitted_at
    }
    REPORT_PHOTOS {
        bigint id PK
        bigint report_id FK
        bigint order_item_id FK
        string path
        enum kind "before|after"
    }
    REVIEWS {
        bigint id PK
        bigint order_id FK
        bigint technician_id FK
        tinyint rating
        text comment
    }
    ORDER_STATUS_LOGS {
        bigint id PK
        bigint order_id FK
        string from_status
        string to_status
        bigint changed_by FK
        text note
        datetime created_at
    }
```

---

## 12. Catatan Implementasi Laravel 12

| Kebutuhan | Rekomendasi |
|---|---|
| Login Google (client) | `laravel/socialite` |
| Login admin & teknisi | Auth bawaan Laravel (session), guard sama dengan kolom `role` |
| Hak akses | Middleware `role:client/admin/teknisi` + Policy (mis. teknisi hanya lihat order miliknya) |
| Peta & titik koordinat | Leaflet + tile OpenStreetMap; marker bisa di-drag; tombol "Lokasi saya" (Geolocation API) |
| Cari alamat di peta | Nominatim (OSM) geocoding — perhatikan batas 1 request/detik |
| Hitung jarak | Fungsi Haversine di PHP (service class) — hasil disimpan di `customer_addresses.distance_km` |
| Upload foto laporan | Storage `public` disk + kompres/resize (mis. `intervention/image`) |
| Status order | Enum PHP 8.1 (`OrderStatus`) + satu service `OrderStatusService` yang memvalidasi transisi & menulis `order_status_logs` |
| Cek bentrok | Service `ScheduleService::isFree($staff, $date, $start, $end)` + DB transaction/lock saat assign |
| Batas batal & garansi | Ambil dari tabel `settings` (cache), cek di Policy/Service |
| Notifikasi | Laravel Notifications — channel `database` (in-app) + `mail` (email, via queue) |
| Frontend | Laravel Blade + komponen Blade; interaktivitas ringan dengan Alpine.js; Tailwind CSS |
| Interface teknisi | Mobile-first (responsif); bisa dijadikan PWA nanti |

---

## 13. Daftar Halaman per Interface

**Client**
- Login (Google)
- Dashboard (order aktif + tombol Buat Order)
- Alamat Saya (list, tambah/edit dengan peta Leaflet, set default)
- Buat Order (wizard: alamat → unit AC [tambah / salin / terapkan ke semua] → jadwal → ringkasan)
- Order Saya (list + filter status)
- Detail Order (timeline status, teknisi & helper, laporan per unit, ulasan, batal, klaim garansi)
- Profil

**Admin**
- Dashboard (order baru, order hari ini, klaim garansi, pendapatan, rating rata-rata)
- Order (list + filter status/tanggal/jenis, detail, assign teknisi + helper, tolak, batal, konfirmasi bayar)
- Jadwal (timeline per teknisi & helper per hari)
- Master: Layanan (+ matriks harga), Merk, PK, Tipe AC, Teknisi, Helper, Client
- Company Profile & Pengaturan (koordinat, radius, tarif, jam operasional, buffer, batas batal, garansi)
- Ulasan (daftar rating & ulasan)

**Teknisi** (mobile-first)
- Login
- Tugas Hari Ini / Mendatang
- Detail Tugas (Terima/Tolak, Berangkat, Mulai, navigasi Maps)
- Form Laporan per unit (foto, catatan, harga final)
- Riwayat Tugas & Rating Saya

---

## 14. Notifikasi (disarankan)

| Event | Penerima | Channel |
|---|---|---|
| Order baru / klaim garansi | Admin | In-app + email |
| Teknisi ditugaskan | Teknisi, Client | In-app + email |
| Teknisi tolak tugas | Admin | In-app + email |
| Teknisi berangkat / mulai | Client | In-app + email |
| Laporan selesai | Client, Admin | In-app + email |
| Order ditolak / dibatalkan | Client / Admin / Teknisi | In-app + email |
| Pengingat H-1 jadwal | Client, Teknisi | Email (scheduler) |

---

## 15. Keputusan yang Sudah Ditetapkan

| # | Topik | Keputusan |
|---|---|---|
| 1 | Unit per order | Multi unit dalam 1 order |
| 2 | Harga | Berbeda per Layanan × PK × Tipe, dengan helper input "Terapkan ke semua / baris / kolom" |
| 3 | Pembatalan client | Paling lambat 3 jam sebelum jadwal (bisa diatur admin) |
| 4 | Tim | 1 teknisi (pakai aplikasi) + 1 helper (tanpa aplikasi) |
| 5 | Garansi | 7 hari, bisa diatur admin (global & per layanan) |
| 6 | Stack | Laravel 12 (Blade) · MySQL · Leaflet |
| 7 | Notifikasi | In-app + email |

**Berikutnya:** sample UI (mockup) sebelum mulai coding.
