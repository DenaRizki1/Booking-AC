<x-layouts.admin title="Helper" subtitle="Pendamping teknisi untuk layanan yang wajib helper (tanpa akun login)." :breadcrumbs="[['Master data', null], ['Helper', null]]">
    <x-slot:actions>
        <x-button :href="route('admin.helpers.create')" icon="plus">Tambah helper</x-button>
    </x-slot:actions>

    <section class="overflow-hidden rounded-card border border-line bg-white">
        @if ($helpers->isEmpty())
            <x-empty-state icon="users" title="Belum ada helper" description="Helper dipilih admin saat assign order untuk layanan yang wajib helper, mis. Instalasi.">
                <x-button :href="route('admin.helpers.create')" size="sm" icon="plus">Tambah helper</x-button>
            </x-empty-state>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[680px] text-left">
                    <thead class="border-b border-line bg-[#F8FAFD] text-xs font-bold tracking-[0.4px] whitespace-nowrap text-muted uppercase">
                        <tr>
                            <th class="px-4 py-2.5">Helper</th>
                            <th class="px-4 py-2.5">No. HP</th>
                            <th class="px-4 py-2.5">Order</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEF2F8] text-[13px]">
                        @foreach ($helpers as $helper)
                            <tr @class(['bg-surface-bg/60' => ! $helper->is_active])>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$helper->name" size="sm" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.helpers.edit', $helper) }}" class="text-sm font-bold text-ink hover:text-primary">{{ $helper->name }}</a>
                                            @if ($helper->notes)
                                                <p class="max-w-72 truncate text-xs text-muted">{{ $helper->notes }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-ink-soft tabular-nums">{{ $helper->phone ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-semibold text-ink">{{ $helper->active_orders_count }} aktif</span>
                                    <span class="text-muted">· {{ $helper->done_orders_count }} selesai</span>
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="$helper->is_active ? 'success' : 'neutral'" size="sm">{{ $helper->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-1">
                                        <form method="POST" action="{{ route('admin.helpers.toggle', $helper) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="inline-flex min-h-11 items-center rounded-[10px] px-3 text-[13px] font-bold text-ink-soft hover:bg-surface-bg hover:text-ink focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                                                {{ $helper->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.helpers.edit', $helper) }}" class="flex size-11 items-center justify-center rounded-[10px] text-primary hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                                            <x-icon name="pencil" class="size-4" /><span class="sr-only">Ubah {{ $helper->name }}</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.admin>
