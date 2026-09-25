@php
    $config = [
        'catalog' => $catalog,
        'addresses' => $addresses,
        'addressId' => $addressId,
        'units' => $units,
        'estimateUrl' => route('client.orders.estimate'),
        'draftUrl' => route('client.orders.draft'),
    ];
    $segment = 'min-h-11 flex-1 rounded-[10px] border-[1.5px] px-2 text-sm font-bold focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25';
@endphp

<x-layouts.client title="Buat Order" :nav="false">
    <x-slot:header>
        <header class="sticky top-0 z-20 border-b border-line bg-white px-4 pt-3 pb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('client.home') }}" class="flex size-12 items-center justify-center rounded-xl border-[1.5px] border-line text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                    <x-icon name="chevron-left" class="size-6" /><span class="sr-only">Kembali</span>
                </a>
                <h1 class="text-xl font-extrabold tracking-tight text-ink">Buat Order</h1>
            </div>
            <div class="mt-4"><x-order-stepper :step="1" /></div>
        </header>
    </x-slot:header>

    <form method="POST" action="{{ route('client.orders.units') }}" x-data="orderUnits(@js($config))" class="-mb-6" novalidate>
        @csrf
        <input type="hidden" name="customer_address_id" :value="addressId">
        <template x-for="(unit, i) in units" :key="unit.uid">
            <div>
                <input type="hidden" :name="`units[${i}][ac_brand_id]`" :value="unit.ac_brand_id ?? ''">
                <input type="hidden" :name="`units[${i}][ac_capacity_id]`" :value="unit.ac_capacity_id ?? ''">
                <input type="hidden" :name="`units[${i}][ac_type_id]`" :value="unit.ac_type_id ?? ''">
                <input type="hidden" :name="`units[${i}][service_id]`" :value="unit.service_id ?? ''">
            </div>
        </template>

        @if ($errors->any())
            <x-flash type="error" :message="$errors->first()" class="mb-4" />
        @endif

        {{-- Alamat --}}
        <div class="mb-4">
            <div class="flex min-h-11 items-center gap-2">
                <x-icon name="map-pin" class="size-5 shrink-0 text-primary" />
                <p class="min-w-0 flex-1 truncate text-[15px]" x-show="address">
                    <strong class="font-bold text-ink" x-text="address?.label"></strong>
                    <span class="text-ink-soft" x-text="`· ${address?.address} · ${address?.distance}`"></span>
                </p>
                <button type="button" x-on:click="pickingAddress = !pickingAddress" class="min-h-11 px-1 text-[15px] font-bold text-primary">Ubah</button>
            </div>
            <p x-show="address && !address.in_range" x-cloak class="text-[13px] font-semibold text-danger">Alamat ini di luar jangkauan layanan. Pilih alamat lain.</p>

            <div x-show="pickingAddress" x-cloak x-transition class="mt-2 rounded-2xl border-[1.5px] border-line bg-white p-2">
                <template x-for="option in addresses" :key="option.id">
                    <button type="button" x-on:click="addressId = option.id; pickingAddress = false" class="flex min-h-12 w-full items-center gap-3 rounded-xl px-3 py-2 text-left hover:bg-surface-bg" :class="option.id === addressId && 'bg-blue-50'">
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-ink" x-text="option.label"></span>
                            <span class="block truncate text-[13px] text-muted" x-text="`${option.address} · ${option.distance}`"></span>
                        </span>
                        <span x-show="!option.in_range" class="text-xs font-bold text-danger">Di luar jangkauan</span>
                    </button>
                </template>
                <a href="{{ route('client.addresses.create', ['redirect' => route('client.orders.create', absolute: false)]) }}" class="flex min-h-12 items-center gap-2 rounded-xl px-3 text-[15px] font-semibold text-primary hover:bg-surface-bg">
                    <x-icon name="plus" class="size-5" /> Tambah alamat baru
                </a>
            </div>
        </div>

        {{-- Unit --}}
        <div class="space-y-3">
            <template x-for="(unit, i) in units" :key="unit.uid">
                <section class="rounded-3xl border-[1.5px] bg-white" :class="open === i ? 'border-primary p-4' : 'border-line p-3'">
                    {{-- Ringkas (tertutup) --}}
                    <button type="button" x-show="open !== i" x-on:click="open = i" class="flex min-h-14 w-full items-center gap-3 text-left">
                        <span class="rounded-lg bg-surface-bg px-2.5 py-1 text-[13px] font-bold text-ink-soft" x-text="`UNIT ${i + 1}`"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-bold text-ink" x-text="unitTitle(unit)"></span>
                            <span class="block truncate text-[13px]" :class="line(i)?.error ? 'text-danger' : 'text-muted'" x-text="line(i)?.error ?? unitSummary(unit, i)"></span>
                        </span>
                        <x-icon name="chevron-down" class="size-5 text-muted" />
                    </button>

                    {{-- Lengkap (terbuka) --}}
                    <div x-show="open === i">
                        <div class="mb-4 flex items-center justify-between">
                            <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-[13px] font-extrabold text-primary" x-text="`UNIT ${i + 1}`"></span>
                            <div class="flex gap-2">
                                <button type="button" x-on:click="copyUnit(i)" class="flex size-11 items-center justify-center rounded-xl border-[1.5px] border-line text-ink-soft hover:bg-surface-bg" title="Salin unit">
                                    <x-icon name="copy" class="size-5" /><span class="sr-only">Salin unit</span>
                                </button>
                                <button type="button" x-on:click="removeUnit(i)" :disabled="units.length === 1" class="flex size-11 items-center justify-center rounded-xl border-[1.5px] border-line text-ink-soft hover:bg-danger-50 hover:text-danger disabled:opacity-40" title="Hapus unit">
                                    <x-icon name="trash-2" class="size-5" /><span class="sr-only">Hapus unit</span>
                                </button>
                            </div>
                        </div>

                        <label class="mb-2 block text-xs font-bold tracking-wide text-muted uppercase" :for="`brand-${unit.uid}`">Merk</label>
                        <div class="relative mb-4">
                            <select :id="`brand-${unit.uid}`" x-model.number="unit.ac_brand_id" class="block min-h-12 w-full cursor-pointer appearance-none rounded-xl border-[1.5px] border-line bg-white pr-11 pl-3.5 text-[15px] font-semibold text-ink focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15">
                                <option value="">Pilih merk</option>
                                <template x-for="brand in catalog.brands" :key="brand.id">
                                    <option :value="brand.id" x-text="brand.name" :selected="brand.id === unit.ac_brand_id"></option>
                                </template>
                            </select>
                            <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 size-5 -translate-y-1/2 text-muted" />
                        </div>

                        <p class="mb-2 text-xs font-bold tracking-wide text-muted uppercase">Kapasitas (PK)</p>
                        <div class="mb-4 flex gap-2" role="group" aria-label="Kapasitas (PK)">
                            <template x-for="capacity in catalog.capacities" :key="capacity.id">
                                <button type="button" x-on:click="select(unit, 'ac_capacity_id', capacity.id)" :aria-pressed="unit.ac_capacity_id === capacity.id" :aria-label="capacity.label" class="{{ $segment }}" :class="unit.ac_capacity_id === capacity.id ? 'border-primary bg-primary text-white' : 'border-line bg-white text-ink-soft'" x-text="capacity.short"></button>
                            </template>
                        </div>

                        <p class="mb-2 text-xs font-bold tracking-wide text-muted uppercase">Tipe</p>
                        <div class="mb-4 flex gap-2" role="group" aria-label="Tipe AC">
                            <template x-for="type in catalog.types" :key="type.id">
                                <button type="button" x-on:click="select(unit, 'ac_type_id', type.id)" :aria-pressed="unit.ac_type_id === type.id" class="{{ $segment }}" :class="unit.ac_type_id === type.id ? 'border-primary bg-primary text-white' : 'border-line bg-white text-ink-soft'" x-text="type.name"></button>
                            </template>
                        </div>

                        <label class="mb-2 block text-xs font-bold tracking-wide text-muted uppercase" :for="`service-${unit.uid}`">Layanan</label>
                        <div class="relative">
                            <select :id="`service-${unit.uid}`" x-model.number="unit.service_id" class="block min-h-12 w-full cursor-pointer appearance-none rounded-xl border-[1.5px] border-line bg-white pr-11 pl-3.5 text-[15px] font-semibold text-ink focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15">
                                <option value="">Pilih layanan</option>
                                <template x-for="service in servicesFor(unit)" :key="service.id">
                                    <option :value="service.id" x-text="service.name" :selected="service.id === unit.service_id"></option>
                                </template>
                            </select>
                            <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 size-5 -translate-y-1/2 text-muted" />
                        </div>
                        <p x-show="servicesFor(unit).length === 0" x-cloak class="mt-2 text-[13px] font-semibold text-danger">Belum ada layanan untuk PK & tipe ini.</p>

                        <div class="mt-4 flex min-h-12 items-center justify-between rounded-xl bg-surface-bg px-4" x-show="line(i)?.available" x-cloak>
                            <span class="flex items-center gap-2 text-[15px] text-ink-soft"><x-icon name="clock" class="size-5" /> <span x-text="line(i)?.duration_label"></span></span>
                            <span class="text-[17px] font-extrabold text-ink tabular-nums" x-text="line(i)?.price_label"></span>
                        </div>
                        <p class="mt-3 text-[13px] font-semibold text-danger" x-show="line(i)?.error" x-cloak x-text="line(i)?.error"></p>
                    </div>
                </section>
            </template>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-3">
            <x-button variant="secondary" icon="plus" x-on:click="addUnit()">Tambah unit</x-button>
            <x-button variant="secondary" icon="copy" x-on:click="copyUnit()">Salin unit</x-button>
        </div>

        {{-- Terapkan ke semua unit --}}
        <div x-show="units.length > 1" x-cloak class="mt-4 rounded-2xl border-[1.5px] border-dashed border-blue-200 p-4">
            <p class="flex items-center gap-2 font-bold text-ink"><x-icon name="sliders-horizontal" class="size-5 text-primary" /> Terapkan ke semua unit</p>
            <div class="mt-3 flex gap-2">
                <label class="sr-only" for="apply-field">Bagian</label>
                <select id="apply-field" x-model="applyField" x-on:change="applyValue = ''" class="min-h-12 w-[34%] rounded-xl border-[1.5px] border-line bg-white px-3 text-[15px] font-semibold">
                    <option value="service_id">Layanan</option>
                    <option value="ac_brand_id">Merk</option>
                    <option value="ac_capacity_id">PK</option>
                    <option value="ac_type_id">Tipe</option>
                </select>
                <label class="sr-only" for="apply-value">Nilai</label>
                <select id="apply-value" x-model="applyValue" class="min-h-12 min-w-0 flex-1 rounded-xl border-[1.5px] border-line bg-white px-3 text-[15px] font-semibold">
                    <option value="">Pilih</option>
                    <template x-for="option in applyOptions" :key="option.id">
                        <option :value="option.id" x-text="option.name"></option>
                    </template>
                </select>
                <button type="button" x-on:click="applyToAll()" :disabled="!applyValue" class="min-h-12 rounded-xl bg-navy px-4 text-[15px] font-bold text-white disabled:opacity-40">Terapkan</button>
            </div>
            <p class="mt-2 text-[13px] text-muted" x-text="`Bisa untuk Layanan, Merk, PK, atau Tipe — berlaku ke ${units.length} unit.`"></p>
        </div>

        {{-- Estimasi & lanjut --}}
        <div class="sticky bottom-0 -mx-4 mt-6 border-t border-line bg-white px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <div class="mb-3 flex items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[13px] text-muted" x-text="`Estimasi · ${units.length} unit · ${estimate?.total?.duration_label ?? '–'}`"></p>
                    <p class="text-2xl font-extrabold tracking-tight text-navy tabular-nums" x-text="estimate?.total?.price_min ? estimate.total.price_label : 'Rp0'"></p>
                </div>
                <p class="shrink-0 text-right text-[13px] text-muted" x-show="estimate?.transport_label" x-cloak>+ transport<br><span x-text="estimate?.transport_fee === 0 ? 'gratis' : estimate?.transport_label"></span></p>
            </div>
            <x-button type="submit" size="lg" block icon-right="chevron-right" ::disabled="!canContinue">Lanjut pilih jadwal</x-button>
            <p class="mt-2 text-center text-[13px] text-muted" x-show="!canContinue && !loading" x-cloak>Lengkapi merk, PK, tipe & layanan setiap unit.</p>
        </div>
    </form>
</x-layouts.client>
