@use('App\Models\Service')

@php
    $editing = $service->exists;
    $icon = old('icon', $service->icon ?? Service::ICONS[0]);
@endphp

<x-layouts.admin
    :title="$editing ? $service->name : 'Tambah layanan'"
    :subtitle="$editing ? 'Slug: '.$service->slug : 'Slug dibuat otomatis dari nama layanan.'"
    :breadcrumbs="[['Layanan & Harga', route('admin.services.index')], [$editing ? $service->name : 'Tambah layanan', null]]"
>
    <x-slot:actions>
        @if ($editing)
            <x-badge :variant="$service->is_active ? 'success' : 'neutral'">{{ $service->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
        @endif
        <x-button :href="route('admin.services.index')" variant="secondary">Batal</x-button>
        <x-button type="submit" form="service-form" icon="save">{{ $editing ? 'Simpan perubahan' : 'Simpan layanan' }}</x-button>
    </x-slot:actions>

    @if ($editing)
        @include('admin.services.partials.tabs', ['active' => 'info'])
    @endif

    <form id="service-form" method="POST" action="{{ $editing ? route('admin.services.update', $service) : route('admin.services.store') }}" class="grid items-start gap-6 lg:grid-cols-3" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-card title="Info layanan" class="lg:col-span-2">
            <div class="flex flex-col gap-4">
                <x-input name="name" label="Nama layanan" :value="$service->name" maxlength="80" placeholder="Mis. Cuci AC" required :autofocus="! $editing" />
                <x-textarea name="description" label="Deskripsi" :value="$service->description" rows="3" hint="Tampil di beranda client. Jelaskan singkat apa yang dikerjakan." />

                <fieldset>
                    <legend class="mb-1.5 text-[13px] font-semibold text-ink-soft">Ikon</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (Service::ICONS as $option)
                            <label class="cursor-pointer" title="{{ $option }}">
                                <input type="radio" name="icon" value="{{ $option }}" @checked($icon === $option) class="peer sr-only">
                                <span class="flex size-12 items-center justify-center rounded-xl border-[1.5px] border-line text-ink-soft peer-checked:border-primary peer-checked:bg-blue-50 peer-checked:text-primary peer-focus-visible:ring-4 peer-focus-visible:ring-primary/25 hover:bg-surface-bg">
                                    <x-icon :name="$option" class="size-6" />
                                    <span class="sr-only">{{ $option }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-field-error name="icon" />
                </fieldset>
            </div>
        </x-card>

        <x-card title="Pengaturan layanan">
            <div class="flex flex-col divide-y divide-[#EEF2F8]">
                <div class="pb-3">
                    <x-toggle name="requires_helper" :checked="(bool) $service->requires_helper" label="Wajib helper" description="Admin harus memilih helper saat assign." />
                </div>
                <div class="py-4">
                    <x-input name="warranty_days" type="number" min="0" max="365" label="Garansi (hari)" :value="$service->warranty_days" :placeholder="$defaultWarranty" :hint="'Kosongkan = default pengaturan ('.$defaultWarranty.' hari).'" />
                </div>
                <div class="pt-3">
                    <x-toggle name="is_active" :checked="(bool) $service->is_active" label="Aktif" description="Bisa dipesan client (bila ada harga aktif)." />
                </div>
            </div>
        </x-card>
    </form>
</x-layouts.admin>
