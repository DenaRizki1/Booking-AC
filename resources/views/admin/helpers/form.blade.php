@php
    $editing = $helper->exists;
    $title = $editing ? $helper->name : 'Tambah helper';
@endphp

<x-layouts.admin
    :title="$title"
    subtitle="Helper tidak punya akun login; admin memilihnya saat assign order."
    :breadcrumbs="[['Master data', null], ['Helper', route('admin.helpers.index')], [$title, null]]"
>
    <x-slot:actions>
        @if ($editing)
            <x-badge :variant="$helper->is_active ? 'success' : 'neutral'">{{ $helper->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
        @endif
        <x-button :href="route('admin.helpers.index')" variant="secondary">{{ $editing ? 'Kembali' : 'Batal' }}</x-button>
        <x-button type="submit" form="helper-form" icon="save">{{ $editing ? 'Simpan perubahan' : 'Simpan helper' }}</x-button>
    </x-slot:actions>

    <form id="helper-form" method="POST" action="{{ $editing ? route('admin.helpers.update', $helper) : route('admin.helpers.store') }}" class="grid items-start gap-6 lg:grid-cols-3" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-card title="Data helper" class="lg:col-span-2">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input name="name" label="Nama lengkap" :value="$helper->name" maxlength="120" required :autofocus="! $editing" />
                <x-input name="phone" type="tel" inputmode="tel" label="No. HP (opsional)" :value="$helper->phone" placeholder="08…" />
                <div class="md:col-span-2">
                    <x-textarea name="notes" label="Catatan internal (opsional)" :value="$helper->notes" rows="3" />
                </div>
            </div>
        </x-card>

        <div class="flex flex-col gap-6">
            <x-card title="Status">
                <x-toggle name="is_active" :checked="(bool) $helper->is_active" label="Aktif" description="Muncul sebagai pilihan saat assign order." />
            </x-card>

            @if ($editing)
                <x-card title="Order">
                    <dl class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-surface-bg px-4 py-3">
                            <dt class="text-xs font-semibold text-muted">Aktif</dt>
                            <dd class="mt-1 text-xl font-extrabold text-ink">{{ $helper->active_orders_count }}</dd>
                        </div>
                        <div class="rounded-xl bg-surface-bg px-4 py-3">
                            <dt class="text-xs font-semibold text-muted">Selesai</dt>
                            <dd class="mt-1 text-xl font-extrabold text-ink">{{ $helper->done_orders_count }}</dd>
                        </div>
                    </dl>
                </x-card>
            @endif
        </div>
    </form>

    @if ($editing)
        @include('admin.partials.staff-leaves', ['leaves' => $leaves, 'action' => route('admin.helpers.leaves.store', $helper)])
    @endif
</x-layouts.admin>
