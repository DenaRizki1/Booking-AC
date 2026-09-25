@php
    $editing = $address->exists;
    $presets = ['Rumah', 'Kantor'];
    $label = old('label', $address->label ?? 'Rumah');
    $choice = in_array($label, $presets, true) ? $label : 'Lainnya';
    $config = [
        'office' => ['lat' => (float) $settings->lat, 'lng' => (float) $settings->lng],
        'freeRadiusKm' => (float) $settings->free_radius_km,
        'maxRadiusKm' => (float) $settings->max_radius_km,
        'feePerKm' => $settings->fee_per_km,
        'lat' => old('lat', $address->lat) !== null ? (float) old('lat', $address->lat) : null,
        'lng' => old('lng', $address->lng) !== null ? (float) old('lng', $address->lng) : null,
        'address' => old('address', $address->address),
    ];
    $back = $redirect ?? route('client.addresses.index');
@endphp

<x-layouts.client :title="$editing ? 'Ubah alamat' : 'Tambah alamat'" :nav="false">
    <x-slot:header></x-slot:header>

    <form
        method="POST"
        action="{{ $editing ? route('client.addresses.update', $address) : route('client.addresses.store') }}"
        x-data="mapPicker(@js($config))"
        class="-mx-4 -mt-5"
        novalidate
    >
        @csrf
        @if ($editing) @method('PUT') @endif
        @if ($redirect) <input type="hidden" name="redirect" value="{{ $redirect }}"> @endif
        <input type="hidden" name="lat" :value="lat">
        <input type="hidden" name="lng" :value="lng">

        {{-- Peta (mockup Client-Alamat) --}}
        <div class="relative h-[46vh] min-h-80 bg-[#E8EDF5]">
            <div x-ref="map" class="absolute inset-0 z-0" aria-label="Peta lokasi" role="application"></div>

            <div class="absolute inset-x-4 top-4 z-[500] flex gap-3">
                <a href="{{ $back }}" class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white text-ink shadow-md hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                    <x-icon name="chevron-left" class="size-6" />
                    <span class="sr-only">Kembali</span>
                </a>
                <div class="relative min-w-0 flex-1">
                    <label for="map-search" class="sr-only">Cari alamat</label>
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted" />
                    <input
                        id="map-search"
                        type="search"
                        x-model="query"
                        x-on:input="onSearchInput"
                        x-on:keydown.enter.prevent="search"
                        placeholder="Cari alamat atau nama jalan"
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

            {{-- Marker tetap di tengah; peta yang digeser --}}
            <div class="pointer-events-none absolute top-1/2 left-1/2 z-[400] -translate-x-1/2 -translate-y-full">
                <p class="absolute bottom-full left-1/2 mb-3 -translate-x-1/2 rounded-lg bg-navy px-3 py-1.5 text-[13px] font-bold whitespace-nowrap text-white">Geser peta untuk atur titik</p>
                <svg width="44" height="54" viewBox="0 0 44 54" aria-hidden="true">
                    <ellipse cx="22" cy="51" rx="7" ry="3" fill="#00084D" fill-opacity=".25"/>
                    <path d="M22 2C11.5 2 3 10.3 3 20.6 3 34 22 50 22 50s19-16 19-29.4C41 10.3 32.5 2 22 2Z" fill="#1D63ED" stroke="#fff" stroke-width="3"/>
                    <circle cx="22" cy="20" r="7" fill="#fff"/>
                </svg>
            </div>

            <button type="button" x-on:click="locate" class="absolute right-4 bottom-10 z-[500] flex size-12 items-center justify-center rounded-2xl bg-white text-primary shadow-md hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25" :class="locating && 'animate-pulse'">
                <x-icon name="locate-fixed" class="size-6" />
                <span class="sr-only">Gunakan lokasi saya</span>
            </button>
        </div>

        {{-- Lembar form --}}
        <div class="relative z-[500] -mt-6 rounded-t-3xl bg-white px-5 pt-3 pb-5">
            <div aria-hidden="true" class="mx-auto mb-4 h-1 w-10 rounded-full bg-line"></div>

            <div
                class="flex items-start gap-3 rounded-2xl px-4 py-3"
                :class="inRange ? 'bg-success-50 text-success-700' : 'bg-danger-50 text-danger-700'"
                role="status"
                aria-live="polite"
            >
                <span x-show="inRange"><x-icon name="check" class="mt-0.5 size-5" /></span>
                <span x-show="!inRange" x-cloak><x-icon name="circle-alert" class="mt-0.5 size-5" /></span>
                <div>
                    <p class="font-bold" x-text="rangeTitle"></p>
                    <p class="text-sm" x-text="rangeDetail"></p>
                </div>
            </div>
            <x-field-error name="lat" />
            <p x-show="message" x-cloak x-text="message" class="mt-2 text-[13px] font-semibold text-warning-900"></p>

            <fieldset class="mt-5" x-data="{ choice: @js($choice), custom: @js($choice === 'Lainnya' ? $label : '') }">
                <legend class="mb-2 text-[13px] font-semibold text-ink-soft">Label</legend>
                <input type="hidden" name="label" :value="choice === 'Lainnya' ? custom : choice">
                <div class="flex flex-wrap gap-2">
                    @foreach (['Rumah', 'Kantor', 'Lainnya'] as $option)
                        <label class="cursor-pointer">
                            <input type="radio" value="{{ $option }}" x-model="choice" class="peer sr-only">
                            <span class="inline-flex min-h-11 items-center rounded-full border-[1.5px] border-line px-5 text-[15px] font-semibold text-ink-soft peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-focus-visible:ring-4 peer-focus-visible:ring-primary/25">{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
                <div x-show="choice === 'Lainnya'" x-cloak class="mt-3">
                    <label for="custom-label" class="sr-only">Nama label</label>
                    <input id="custom-label" type="text" x-model="custom" maxlength="50" placeholder="Mis. Rumah Orang Tua" class="block min-h-12 w-full rounded-xl border-[1.5px] border-line px-3.5 text-[15px] focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15">
                </div>
                <x-field-error name="label" />
            </fieldset>

            <div class="mt-4 space-y-4">
                <x-textarea name="address" label="Detail alamat" rows="2" x-model="address" x-on:input="touchAddress" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan" required />
                <x-input name="landmark" label="Patokan (opsional)" :value="$address->landmark" placeholder="Mis. pagar hitam, sebelah minimarket" />
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                <p class="text-[13px] text-muted">Koordinat: <span x-text="coordinates"></span></p>
                <x-checkbox name="is_default" label="Alamat utama" :checked="(bool) old('is_default', $address->is_default)" />
            </div>

            <div class="sticky bottom-0 -mx-5 mt-4 border-t border-line bg-white px-5 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <x-button type="submit" size="lg" block ::disabled="!inRange">Simpan Alamat</x-button>
            </div>
        </div>
    </form>
</x-layouts.client>
