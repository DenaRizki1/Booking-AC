@php
    $days = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    $workingDays = array_map('intval', (array) old('working_days', $settings->working_days ?? []));
    $mapConfig = [
        'lat' => (float) old('lat', $settings->lat),
        'lng' => (float) old('lng', $settings->lng),
        'freeRadiusKm' => (float) old('free_radius_km', $settings->free_radius_km),
        'maxRadiusKm' => (float) old('max_radius_km', $settings->max_radius_km),
    ];
@endphp

<x-layouts.admin title="Pengaturan" subtitle="Profil perusahaan, titik kantor, jam operasional, dan kebijakan order." :breadcrumbs="[['Master data', null], ['Pengaturan', null]]">
    <x-slot:actions>
        <x-button type="submit" form="settings-form" icon="save">Simpan</x-button>
    </x-slot:actions>

    <form id="settings-form" method="POST" action="{{ route('admin.settings.update') }}" class="flex flex-col gap-6" novalidate>
        @csrf
        @method('PUT')

        <x-card title="Profil perusahaan" description="Tampil di halaman client, email, dan info pembayaran.">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input name="company_name" label="Nama perusahaan" :value="$settings->company_name" maxlength="120" required />
                <x-input name="phone" label="No. telepon / WhatsApp" :value="$settings->phone" inputmode="tel" placeholder="0812…" />
                <x-input name="email" type="email" label="Email" :value="$settings->email" placeholder="halo@perusahaan.id" />
                <div class="md:col-span-2">
                    <x-textarea name="address" label="Alamat kantor" :value="$settings->address" rows="2" required />
                </div>
            </div>
        </x-card>

        <x-card title="Titik kantor & jangkauan" description="Acuan jarak dan biaya transport. Geser marker atau klik peta untuk memindahkan titik.">
            <div x-data="officeMap(@js($mapConfig))" class="grid items-start gap-6 lg:grid-cols-5">
                <input type="hidden" name="lat" :value="lat">
                <input type="hidden" name="lng" :value="lng">

                <div class="lg:col-span-3">
                    <div class="relative h-[420px] overflow-hidden rounded-2xl border border-line bg-[#E8EDF5]">
                        <div x-ref="map" class="absolute inset-0 z-0" aria-label="Peta titik kantor" role="application"></div>

                        <div class="absolute inset-x-3 top-3 z-[500]">
                            <label for="office-search" class="sr-only">Cari alamat kantor</label>
                            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted" />
                            <input
                                id="office-search"
                                type="search"
                                x-model="query"
                                x-on:input="onSearchInput"
                                x-on:keydown.enter.prevent="search"
                                placeholder="Cari alamat kantor"
                                autocomplete="off"
                                class="h-12 w-full rounded-2xl border-0 bg-white pr-4 pl-11 text-[15px] text-ink shadow-md placeholder:text-muted focus:outline-none focus:ring-4 focus:ring-primary/25"
                            >
                            <ul x-show="results.length" x-cloak class="absolute inset-x-0 top-14 max-h-64 overflow-y-auto rounded-2xl bg-white py-1 shadow-lg">
                                <template x-for="result in results" :key="result.place_id">
                                    <li>
                                        <button type="button" x-on:click="choose(result)" class="flex min-h-12 w-full items-start gap-2 px-4 py-2.5 text-left text-sm text-ink hover:bg-surface-bg">
                                            <x-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-primary" />
                                            <span x-text="result.display_name"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            <p x-show="searching" x-cloak class="absolute top-14 rounded-xl bg-white px-4 py-2 text-sm text-muted shadow">Mencari…</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px] text-muted">
                        <span>Koordinat: <b class="font-semibold text-ink-soft tabular-nums" x-text="coordinates"></b></span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full border-2 border-success bg-success/15"></span> Radius gratis</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full border-2 border-dashed border-primary"></span> Radius maksimal</span>
                        <button type="button" x-on:click="fitRadius" class="ml-auto inline-flex min-h-11 items-center gap-1.5 font-bold text-primary hover:text-primary-hover">
                            <x-icon name="scan" class="size-4" /> Lihat seluruh jangkauan
                        </button>
                    </div>
                    <p x-show="message" x-cloak x-text="message" class="mt-1 text-[13px] font-semibold text-warning-900"></p>
                    <x-field-error name="lat" />
                </div>

                <div class="grid gap-4 lg:col-span-2">
                    <x-input name="free_radius_km" type="number" step="0.1" min="0" label="Radius transport gratis (km)" :value="$settings->free_radius_km" x-model="freeRadius" required />
                    <x-input name="max_radius_km" type="number" step="0.1" min="0" label="Radius maksimal (km)" :value="$settings->max_radius_km" x-model="maxRadius" hint="Alamat di luar radius ini tidak bisa memesan." required />
                    <x-input name="fee_per_km" type="number" step="500" min="0" label="Tarif transport per km (Rp)" :value="$settings->fee_per_km" hint="Dikenakan untuk setiap km (dibulatkan ke atas) di luar radius gratis." required />
                    <div class="flex items-start gap-3 rounded-2xl bg-blue-50 px-4 py-3 text-[13px] text-blue-800">
                        <x-icon name="info" class="mt-0.5 size-4 shrink-0" />
                        <p>Memindahkan titik kantor akan menghitung ulang jarak semua alamat client. Order yang sudah dibuat tidak berubah.</p>
                    </div>
                </div>
            </div>
        </x-card>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-card title="Jam operasional" description="Menentukan slot jadwal yang bisa dipilih client.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="open_time" type="time" label="Jam buka" :value="substr((string) $settings->open_time, 0, 5)" required />
                    <x-input name="close_time" type="time" label="Jam tutup" :value="substr((string) $settings->close_time, 0, 5)" hint="Order harus selesai (termasuk buffer) sebelum jam ini." required />

                    <fieldset class="sm:col-span-2">
                        <legend class="mb-1.5 text-[13px] font-semibold text-ink-soft">Hari kerja <span class="text-danger" aria-hidden="true">*</span></legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($days as $number => $dayLabel)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="working_days[]" value="{{ $number }}" @checked(in_array($number, $workingDays, true)) class="peer sr-only">
                                    <span class="inline-flex min-h-11 min-w-14 items-center justify-center rounded-xl border-[1.5px] border-line px-3 text-sm font-bold text-ink-soft peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-focus-visible:ring-4 peer-focus-visible:ring-primary/25">{{ $dayLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-field-error :messages="array_filter([$errors->first('working_days') ?: $errors->first('working_days.*')])" />
                    </fieldset>

                    <x-input name="slot_interval_minutes" type="number" min="15" step="15" label="Interval pilihan jam (menit)" :value="$settings->slot_interval_minutes" required />
                    <x-input name="travel_buffer_minutes" type="number" min="0" step="5" label="Buffer perjalanan (menit)" :value="$settings->travel_buffer_minutes" hint="Jeda setelah tiap order sebelum teknisi bisa dijadwalkan lagi." required />
                </div>
            </x-card>

            <div class="flex flex-col gap-6">
                <x-card title="Kebijakan order">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input name="cancel_limit_hours" type="number" min="0" label="Batas batal oleh client (jam)" :value="$settings->cancel_limit_hours" hint="Client bisa membatalkan sendiri sampai X jam sebelum jadwal." required />
                        <x-input name="warranty_days" type="number" min="0" label="Garansi default (hari)" :value="$settings->warranty_days" hint="Dipakai bila layanan tidak punya masa garansi sendiri." required />
                    </div>
                </x-card>

                <x-card title="Rekening pembayaran" description="Ditampilkan ke client untuk pembayaran transfer.">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input name="bank_name" label="Nama bank" :value="$settings->bank_name" placeholder="BCA" />
                        <x-input name="bank_account_no" label="No. rekening" :value="$settings->bank_account_no" inputmode="numeric" />
                        <div class="sm:col-span-2">
                            <x-input name="bank_account_name" label="Atas nama" :value="$settings->bank_account_name" />
                        </div>
                    </div>
                </x-card>
            </div>
        </div>

        <div class="flex justify-end">
            <x-button type="submit" size="lg" icon="save">Simpan pengaturan</x-button>
        </div>
    </form>
</x-layouts.admin>
