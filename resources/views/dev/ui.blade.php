@php
    use App\Enums\OrderStatus;
@endphp

<x-layouts.base title="UI Kit" class="min-h-screen">
    <div class="mx-auto max-w-5xl space-y-8 px-4 py-10 sm:px-6">
        <header>
            <p class="text-xs font-extrabold tracking-wide text-muted uppercase">Dev only</p>
            <h1 class="text-3xl font-extrabold tracking-tight text-navy">UI Kit</h1>
            <p class="mt-1 text-muted">Semua komponen Blade di <code class="text-ink-soft">resources/views/components</code>. Acuan: <code class="text-ink-soft">mockup/StyleGuide.html</code>.</p>
        </header>

        <x-card title="Warna">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                @foreach ([
                    'primary' => 'bg-primary', 'primary-hover' => 'bg-primary-hover', 'navy' => 'bg-navy', 'blue-50' => 'bg-blue-50',
                    'surface-bg' => 'bg-surface-bg', 'line' => 'bg-line', 'ink' => 'bg-ink', 'muted' => 'bg-muted',
                    'success' => 'bg-success', 'warning' => 'bg-warning', 'danger' => 'bg-danger', 'surface' => 'bg-surface',
                ] as $token => $class)
                    <div>
                        <div class="{{ $class }} h-14 rounded-xl border border-navy/10"></div>
                        <p class="mt-1.5 text-xs font-bold">{{ $token }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card title="Tipografi">
            <div class="space-y-2">
                <p class="text-3xl font-extrabold tracking-tight text-navy">Judul 30 / ExtraBold</p>
                <p class="text-lg font-extrabold">Subjudul 18 / ExtraBold</p>
                <p class="text-[15px] font-medium">Isi 15 / Medium — teks utama form & kartu</p>
                <p class="text-xs font-semibold text-muted">Keterangan 12 / SemiBold — label & meta</p>
                <p class="text-xl font-extrabold text-navy tabular-nums">Rp197.000 – Rp232.000</p>
            </div>
        </x-card>

        <x-card title="Tombol" description="Semua ukuran ≥ 44px (target sentuh).">
            <div class="space-y-4">
                <div class="flex flex-wrap gap-3">
                    <x-button>Utama</x-button>
                    <x-button variant="secondary">Sekunder</x-button>
                    <x-button variant="danger">Tolak</x-button>
                    <x-button variant="danger-solid">Hapus</x-button>
                    <x-button variant="ghost">Ghost</x-button>
                    <x-button disabled>Nonaktif</x-button>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-button size="sm" icon="plus">Kecil</x-button>
                    <x-button icon="save">Sedang</x-button>
                    <x-button size="lg" icon-right="chevron-right">Besar</x-button>
                    <x-button href="#" variant="secondary" icon="map-pin">Tautan</x-button>
                </div>
                <x-button block icon="calendar">Lebar penuh</x-button>
            </div>
        </x-card>

        <div class="grid gap-8 lg:grid-cols-2">
            <x-card title="Form">
                <div class="space-y-4">
                    <x-input name="demo_name" label="Nama lengkap" placeholder="Mis. Budi Santoso" required />
                    <x-input name="demo_phone" type="tel" label="No. HP" icon="phone" placeholder="08xxxxxxxxxx" hint="Format 08… atau +62…" />
                    <x-input name="demo_email" type="email" label="Email" value="salah@" error="Format email tidak valid." />
                    <x-select name="demo_pk" label="PK" placeholder="Pilih PK" :options="['0.5' => '½ PK', '1' => '1 PK', '1.5' => '1½ PK', '2' => '2 PK']" value="1" />
                    <x-textarea name="demo_note" label="Catatan keluhan" placeholder="AC kurang dingin, berisik…" />
                    <x-input name="demo_disabled" label="Nonaktif" value="Tidak bisa diubah" disabled />
                </div>
            </x-card>

            <x-card title="Pilihan">
                <div class="space-y-2">
                    <x-checkbox name="demo_check[]" value="a" label="Wajib helper" description="Admin wajib memilih helper saat assign." checked />
                    <x-checkbox name="demo_check[]" value="b" label="Tampilkan di beranda" />
                    <div class="border-t border-line pt-2">
                        <x-toggle name="demo_active" label="Aktif" checked />
                    </div>
                    <x-toggle name="demo_inactive" label="Nonaktif" description="Kombinasi tidak dilayani." />
                    <div class="border-t border-line pt-3">
                        <x-label>Pesan error mandiri</x-label>
                        <x-field-error :messages="['Harga sampai harus ≥ harga mulai.']" />
                    </div>
                </div>
            </x-card>
        </div>

        <x-card title="Badge">
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    @foreach (OrderStatus::cases() as $status)
                        <x-badge :status="$status" />
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-badge>Netral</x-badge>
                    <x-badge variant="primary">Primary</x-badge>
                    <x-badge variant="navy">Garansi</x-badge>
                    <x-badge variant="success"><x-icon name="circle-check" class="size-3.5" /> Free</x-badge>
                    <x-badge variant="warning">Cuti</x-badge>
                    <x-badge variant="danger">Bentrok</x-badge>
                    <x-badge variant="primary" size="sm">Kecil</x-badge>
                </div>
            </div>
        </x-card>

        <x-card title="Flash">
            <div class="space-y-3">
                <x-flash type="success" message="Order ORD-2409-0015 berhasil dibuat." />
                <x-flash type="error" message="Slot jam 10:00 baru saja terisi. Pilih jam lain." />
                <x-flash type="warning" message="Tanggal cuti bentrok dengan 1 order yang sudah ditugaskan." />
                <x-flash type="info" message="Pembatalan paling lambat 3 jam sebelum jadwal." />
            </div>
        </x-card>

        <div class="grid gap-8 lg:grid-cols-2">
            <x-card title="Modal">
                <x-slot:actions>
                    <x-badge variant="primary" size="sm">Alpine</x-badge>
                </x-slot:actions>
                <p class="mb-4 text-sm text-muted">Esc, klik latar, atau tombol tutup untuk menutup.</p>
                <x-button icon="pencil" x-on:click="$dispatch('open-modal', 'demo')">Buka modal</x-button>
            </x-card>

            <x-card :padding="false" title="Empty state">
                <x-empty-state icon="clipboard-list" title="Belum ada order" description="Order yang Anda buat akan tampil di sini.">
                    <x-button size="sm" icon="plus">Buat order</x-button>
                </x-empty-state>
            </x-card>
        </div>

        <x-card title="Ikon (Lucide)">
            <div class="flex flex-wrap gap-4 text-ink-soft">
                @foreach (['snowflake', 'wrench', 'map-pin', 'calendar', 'user', 'search', 'copy', 'trash-2', 'clipboard-list', 'circle-check'] as $icon)
                    <div class="flex w-20 flex-col items-center gap-1.5">
                        <x-icon :name="$icon" class="size-6" />
                        <span class="text-[11px] text-muted">{{ $icon }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    <x-modal name="demo" title="Edit merk AC">
        <div class="space-y-4">
            <x-input name="modal_name" label="Nama merk" value="Daikin" autofocus />
            <x-toggle name="modal_active" label="Aktif" checked />
        </div>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'demo')">Batal</x-button>
            <x-button icon="save">Simpan</x-button>
        </x-slot:footer>
    </x-modal>
</x-layouts.base>
