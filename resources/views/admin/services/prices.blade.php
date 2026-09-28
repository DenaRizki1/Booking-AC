@php
    $config = [
        'cells' => $cells,
        'capacities' => $capacities->map(fn ($capacity) => ['id' => $capacity->id, 'label' => $capacity->label])->values(),
        'types' => $types->map(fn ($type) => ['id' => $type->id, 'name' => $type->name])->values(),
    ];
    $cellErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'cells'))->flatten();
    $cellInput = 'w-full min-w-0 rounded-lg border-[1.5px] border-line bg-white px-2 text-[13px] font-semibold text-ink tabular-nums focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15';
@endphp

<x-layouts.admin
    :title="$service->name"
    subtitle="Harga per kapasitas (PK) × tipe AC. Client melihat rentang harga & estimasi durasi ini saat membuat order."
    :breadcrumbs="[['Layanan & Harga', route('admin.services.index')], [$service->name, route('admin.services.edit', $service)], ['Matriks harga', null]]"
>
    <x-slot:actions>
        <x-badge :variant="$service->is_active ? 'success' : 'neutral'">{{ $service->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
        <x-button :href="route('admin.services.index')" variant="secondary">Batal</x-button>
        <x-button type="submit" form="price-form" icon="save">Simpan perubahan</x-button>
    </x-slot:actions>

    @include('admin.services.partials.tabs', ['active' => 'prices'])

    @if ($capacities->isEmpty() || $types->isEmpty())
        <x-card>
            <x-empty-state icon="air-vent" title="Kapasitas / tipe AC belum ada" description="Tambahkan kapasitas (PK) dan tipe AC terlebih dahulu; matriks akan terbentuk otomatis.">
                <x-button :href="route('admin.ac.index', $capacities->isEmpty() ? 'pk' : 'tipe')" size="sm" icon="plus">Buka Data AC</x-button>
            </x-empty-state>
        </x-card>
    @else
        <form id="price-form" method="POST" action="{{ route('admin.services.prices.update', $service) }}" x-data="priceMatrix(@js($config))" class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_330px]" novalidate>
            @csrf
            @method('PUT')

            <section class="min-w-0 rounded-card border border-line bg-white p-[18px]">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-base font-extrabold text-ink">Harga per PK × Tipe</h2>
                        <p class="text-xs text-muted">Rupiah · estimasi durasi dalam jam · matikan sakelar jika kombinasi tidak dilayani · kosongkan sel = tidak ada harga</p>
                    </div>
                    <span class="text-xs font-bold text-ink-soft"><span x-text="activeCount"></span> dari <span x-text="totalCount"></span> aktif</span>
                </div>

                @if ($cellErrors->isNotEmpty())
                    <div role="alert" class="mb-3 rounded-xl border border-danger/20 bg-danger-50 px-4 py-3 text-[13px] font-semibold text-danger-700">
                        <p class="flex items-center gap-2"><x-icon name="circle-alert" class="size-4" /> Periksa sel berikut:</p>
                        <ul class="mt-1 list-disc pl-10">
                            @foreach ($cellErrors as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="relative overflow-x-auto pb-1">
                    <div class="grid gap-2" style="grid-template-columns: 76px repeat({{ $types->count() }}, minmax(184px, 1fr));">
                        <div></div>
                        @foreach ($types as $type)
                            <div class="flex items-center justify-center gap-1.5 text-center text-xs font-bold text-ink-soft" :class="preview === 'col' && col == {{ $type->id }} && 'text-primary!'">
                                {{ $type->name }}
                                @unless ($type->is_active)<x-badge size="sm">nonaktif</x-badge>@endunless
                            </div>
                        @endforeach

                        @foreach ($capacities as $capacity)
                            <div class="flex flex-col justify-center text-sm font-extrabold text-ink" :class="preview === 'row' && row == {{ $capacity->id }} && 'text-primary!'">
                                {{ $capacity->label }}
                                @unless ($capacity->is_active)<span class="text-[10px] font-bold text-muted uppercase">nonaktif</span>@endunless
                            </div>

                            @foreach ($types as $type)
                                @php
                                    $key = $capacity->id.'-'.$type->id;
                                    $field = "cells[{$capacity->id}][{$type->id}]";
                                    $cellLabel = $capacity->label.' '.$type->name;
                                    $hasError = $errors->has("cells.{$capacity->id}.{$type->id}");
                                @endphp
                                <div
                                    @class(['flex flex-col gap-2 rounded-xl border-[1.5px] p-2.5 transition-colors', 'ring-2 ring-danger' => $hasError])
                                    :class="isTarget({{ $capacity->id }}, {{ $type->id }})
                                        ? 'border-primary bg-[#F2F6FE]'
                                        : (cells['{{ $key }}'].active ? 'border-line bg-white' : 'border-dashed border-line-strong bg-[#F8FAFD]')"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <input type="text" inputmode="numeric" name="{{ $field }}[min]" x-model="cells['{{ $key }}'].min" x-on:blur="formatCell('{{ $key }}', 'min')" placeholder="mulai" aria-label="{{ $cellLabel }}: harga mulai" class="h-9 {{ $cellInput }}">
                                        <span class="text-[#9AA5BA]" aria-hidden="true">–</span>
                                        <input type="text" inputmode="numeric" name="{{ $field }}[max]" x-model="cells['{{ $key }}'].max" x-on:blur="formatCell('{{ $key }}', 'max')" placeholder="sampai" aria-label="{{ $cellLabel }}: harga sampai" class="h-9 {{ $cellInput }}">
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs text-muted">
                                        <input type="text" inputmode="decimal" name="{{ $field }}[hours]" x-model="cells['{{ $key }}'].hours" placeholder="0" aria-label="{{ $cellLabel }}: estimasi jam" class="h-8 w-14! {{ $cellInput }}">
                                        jam
                                        <button type="button" x-show="filled(cells['{{ $key }}'])" x-on:click="clear('{{ $key }}')" class="ml-1 flex size-8 items-center justify-center rounded-lg text-muted hover:bg-surface-bg hover:text-danger" title="Kosongkan sel">
                                            <x-icon name="eraser" class="size-3.5" /><span class="sr-only">Kosongkan {{ $cellLabel }}</span>
                                        </button>
                                        <span class="flex-1"></span>
                                        <input type="hidden" name="{{ $field }}[active]" :value="cells['{{ $key }}'].active ? 1 : 0">
                                        <label class="inline-flex min-h-8 cursor-pointer items-center" title="Dilayani">
                                            <input type="checkbox" role="switch" x-model="cells['{{ $key }}'].active" class="peer sr-only">
                                            <span aria-hidden="true" class="relative h-5 w-8 shrink-0 rounded-full bg-line-strong transition-colors after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-3 peer-focus-visible:ring-4 peer-focus-visible:ring-primary/25"></span>
                                            <span class="sr-only">{{ $cellLabel }} dilayani</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </section>

            <div class="flex flex-col gap-4 xl:sticky xl:top-6">
                {{-- Isi cepat --}}
                <section class="flex flex-col gap-3.5 rounded-card border border-blue-200 bg-white p-[18px]">
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-9 items-center justify-center rounded-[10px] bg-blue-50 text-primary"><x-icon name="wand-sparkles" class="size-5" /></span>
                        <div>
                            <h2 class="text-base font-extrabold text-ink">Isi cepat</h2>
                            <p class="text-xs text-muted">Isi sekali, terapkan ke banyak sel</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <x-label for="quick-min">Harga mulai</x-label>
                            <input id="quick-min" type="text" inputmode="numeric" x-model="quick.min" x-on:blur="formatQuick('min')" placeholder="75.000" class="min-h-11 {{ $cellInput }} px-3 text-sm">
                        </div>
                        <div>
                            <x-label for="quick-max">Harga sampai</x-label>
                            <input id="quick-max" type="text" inputmode="numeric" x-model="quick.max" x-on:blur="formatQuick('max')" placeholder="90.000" class="min-h-11 {{ $cellInput }} px-3 text-sm">
                        </div>
                    </div>
                    <div>
                        <x-label for="quick-hours">Estimasi durasi (jam)</x-label>
                        <input id="quick-hours" type="text" inputmode="decimal" x-model="quick.hours" placeholder="1,5" class="min-h-11 {{ $cellInput }} px-3 text-sm">
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-[13px] font-semibold text-ink-soft">Terapkan ke</span>
                        <button type="button" x-on:click="apply('all')" x-on:mouseenter="preview = 'all'" x-on:focus="preview = 'all'" x-on:mouseleave="preview = null" x-on:blur="preview = null" class="flex min-h-11 items-center justify-between rounded-xl bg-primary px-3.5 text-sm font-bold text-white hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                            Semua kombinasi <span class="text-xs opacity-85"><span x-text="targetCount('all')"></span> sel</span>
                        </button>
                        <div class="flex gap-2" x-on:mouseenter="preview = 'row'" x-on:mouseleave="preview = null" x-on:focusin="preview = 'row'" x-on:focusout="preview = null">
                            <label for="quick-row" class="sr-only">Baris PK</label>
                            <select id="quick-row" x-model.number="row" class="min-h-11 min-w-0 flex-1 rounded-xl border-[1.5px] border-blue-200 bg-white px-3 text-sm font-semibold text-ink focus:border-primary focus:outline-none">
                                @foreach ($capacities as $capacity)<option value="{{ $capacity->id }}">Baris PK: {{ $capacity->label }}</option>@endforeach
                            </select>
                            <button type="button" x-on:click="apply('row')" class="min-h-11 shrink-0 rounded-xl border-[1.5px] border-blue-200 bg-white px-3.5 text-sm font-bold text-primary hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">Terapkan</button>
                        </div>
                        <div class="flex gap-2" x-on:mouseenter="preview = 'col'" x-on:mouseleave="preview = null" x-on:focusin="preview = 'col'" x-on:focusout="preview = null">
                            <label for="quick-col" class="sr-only">Kolom tipe</label>
                            <select id="quick-col" x-model.number="col" class="min-h-11 min-w-0 flex-1 rounded-xl border-[1.5px] border-blue-200 bg-white px-3 text-sm font-semibold text-ink focus:border-primary focus:outline-none">
                                @foreach ($types as $type)<option value="{{ $type->id }}">Kolom tipe: {{ $type->name }}</option>@endforeach
                            </select>
                            <button type="button" x-on:click="apply('col')" class="min-h-11 shrink-0 rounded-xl border-[1.5px] border-blue-200 bg-white px-3.5 text-sm font-bold text-primary hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">Terapkan</button>
                        </div>
                    </div>
                    <p x-show="quickError" x-cloak x-text="quickError" role="alert" class="text-[13px] font-semibold text-danger"></p>
                    <p class="text-xs leading-relaxed text-ink-soft">Sel bergaris biru = target pilihan saat ini. Setelah diterapkan, sel masih bisa diedit satu per satu. Perubahan baru tersimpan setelah klik <b>Simpan perubahan</b>.</p>
                </section>

                {{-- Ringkasan pengaturan layanan --}}
                <section class="flex flex-col gap-3 rounded-card border border-line bg-white p-[18px] text-[13px]">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-extrabold text-ink">Pengaturan layanan</h2>
                        <a href="{{ route('admin.services.edit', $service) }}" class="inline-flex min-h-11 items-center font-bold text-primary">Ubah</a>
                    </div>
                    <div class="flex items-center justify-between">
                        <span><b>Wajib helper</b><br><span class="text-xs text-muted">Admin harus memilih helper</span></span>
                        <x-badge :variant="$service->requires_helper ? 'warning' : 'neutral'" size="sm">{{ $service->requires_helper ? 'Ya' : 'Tidak' }}</x-badge>
                    </div>
                    <div class="h-px bg-[#EEF2F8]"></div>
                    <div class="flex items-center justify-between">
                        <span><b>Garansi</b><br><span class="text-xs text-muted">Kosong = default pengaturan</span></span>
                        <span class="font-bold">{{ $service->warranty_days ?? $defaultWarranty }} hari</span>
                    </div>
                </section>
            </div>
        </form>
    @endif
</x-layouts.admin>
