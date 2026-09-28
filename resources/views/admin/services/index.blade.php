@use('Illuminate\Support\Str')

<x-layouts.admin title="Layanan & Harga" subtitle="Layanan yang bisa dipesan client beserta matriks harganya." :breadcrumbs="[['Layanan & Harga', null]]">
    <x-slot:actions>
        <x-button :href="route('admin.services.create')" icon="plus">Tambah layanan</x-button>
    </x-slot:actions>

    <div x-data="{ deleting: { name: '', url: '' } }" class="flex flex-col gap-5">
        <section class="overflow-hidden rounded-card border border-line bg-white">
            @if ($services->isEmpty())
                <x-empty-state icon="tag" title="Belum ada layanan" description="Tambahkan layanan seperti Cuci AC atau Perbaikan, lalu isi matriks harganya.">
                    <x-button :href="route('admin.services.create')" size="sm" icon="plus">Tambah layanan</x-button>
                </x-empty-state>
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left">
                        <thead class="border-b border-line bg-[#F8FAFD] text-xs font-bold tracking-[0.4px] whitespace-nowrap text-muted uppercase">
                            <tr>
                                <th class="w-28 px-4 py-2.5">Urutan</th>
                                <th class="px-4 py-2.5">Layanan</th>
                                <th class="px-4 py-2.5">Harga aktif</th>
                                <th class="px-4 py-2.5">Garansi</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEF2F8] text-[13px]">
                            @foreach ($services as $service)
                                <tr @class(['bg-surface-bg/60' => ! $service->is_active])>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center">
                                            @foreach (['up' => 'chevron-up', 'down' => 'chevron-down'] as $direction => $icon)
                                                <form method="POST" action="{{ route('admin.services.move', $service) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                                    <button
                                                        type="submit"
                                                        @disabled(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last))
                                                        class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface-bg hover:text-ink disabled:opacity-30 disabled:hover:bg-transparent focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25"
                                                    >
                                                        <x-icon :name="$icon" class="size-4" />
                                                        <span class="sr-only">{{ $direction === 'up' ? 'Naikkan' : 'Turunkan' }} {{ $service->name }}</span>
                                                    </button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary">
                                                <x-icon :name="$service->icon ?: 'wrench'" class="size-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <a href="{{ route('admin.services.edit', $service) }}" class="text-sm font-bold text-ink hover:text-primary">{{ $service->name }}</a>
                                                <p class="max-w-md truncate text-xs text-muted">
                                                    @if ($service->requires_helper)<b class="font-bold text-warning-900">Wajib helper</b> · @endif
                                                    {{ Str::limit($service->description ?? '', 80) ?: '—' }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if ($service->active_prices_count === 0)
                                            <x-badge variant="warning" size="sm">Belum ada harga</x-badge>
                                        @else
                                            <span class="font-semibold text-ink tabular-nums">{{ $service->active_prices_count }}</span>
                                            <span class="text-muted">dari {{ $service->prices_count }} kombinasi</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-ink-soft">
                                        {{ $service->warranty_days ?? $defaultWarranty }} hari
                                        @if ($service->warranty_days === null)<span class="text-muted">(default)</span>@endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :variant="$service->is_active ? 'success' : 'neutral'" size="sm">{{ $service->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center justify-end gap-1">
                                            <form method="POST" action="{{ route('admin.services.toggle', $service) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="inline-flex min-h-11 items-center rounded-[10px] px-3 text-[13px] font-bold text-ink-soft hover:bg-surface-bg hover:text-ink focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                                                    {{ $service->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                            <a href="{{ route('admin.services.edit', $service) }}" class="flex size-11 items-center justify-center rounded-[10px] text-primary hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                                                <x-icon name="pencil" class="size-4" /><span class="sr-only">Ubah {{ $service->name }}</span>
                                            </a>
                                            <button type="button" x-on:click="deleting = { name: @js($service->name), url: @js(route('admin.services.destroy', $service)) }; $dispatch('open-modal', 'service-delete')" class="flex size-11 items-center justify-center rounded-[10px] text-danger hover:bg-danger-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-danger/25">
                                                <x-icon name="trash-2" class="size-4" /><span class="sr-only">Hapus {{ $service->name }}</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <p class="text-[13px] text-muted">Urutan di atas = urutan layanan di beranda dan form order client. Layanan hanya bisa dipesan bila aktif dan punya minimal satu harga aktif.</p>

        <x-modal name="service-delete" title="Hapus layanan?">
            <p class="text-sm text-ink-soft"><b class="text-ink" x-text="deleting.name"></b> tidak akan tampil lagi untuk client. Riwayat order yang memakai layanan ini tetap tersimpan.</p>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'service-delete')">Batal</x-button>
                <form method="POST" :action="deleting.url">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="danger-solid" icon="trash-2" class="w-full">Hapus</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    </div>
</x-layouts.admin>
