@use('App\Support\Format')

<x-layouts.admin title="Teknisi" subtitle="Tim teknisi beserta akun login untuk panel teknisi." :breadcrumbs="[['Master data', null], ['Teknisi', null]]">
    <x-slot:actions>
        <x-button :href="route('admin.technicians.create')" icon="plus">Tambah teknisi</x-button>
    </x-slot:actions>

    <section class="overflow-hidden rounded-card border border-line bg-white">
        @if ($technicians->isEmpty())
            <x-empty-state icon="wrench" title="Belum ada teknisi" description="Tambahkan teknisi agar order bisa di-assign. Setiap teknisi mendapat akun login sendiri.">
                <x-button :href="route('admin.technicians.create')" size="sm" icon="plus">Tambah teknisi</x-button>
            </x-empty-state>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[820px] text-left">
                    <thead class="border-b border-line bg-[#F8FAFD] text-xs font-bold tracking-[0.4px] whitespace-nowrap text-muted uppercase">
                        <tr>
                            <th class="px-4 py-2.5">Teknisi</th>
                            <th class="px-4 py-2.5">Kontak</th>
                            <th class="px-4 py-2.5">Rating</th>
                            <th class="px-4 py-2.5">Order</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEF2F8] text-[13px]">
                        @foreach ($technicians as $technician)
                            <tr @class(['bg-surface-bg/60' => ! $technician->is_active])>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$technician->user->name" :src="$technician->photoUrl()" size="sm" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.technicians.edit', $technician) }}" class="text-sm font-bold text-ink hover:text-primary">{{ $technician->user->name }}</a>
                                            @if ($technician->notes)
                                                <p class="max-w-64 truncate text-xs text-muted">{{ $technician->notes }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold whitespace-nowrap text-ink">{{ $technician->user->email }}</p>
                                    <p class="text-muted tabular-nums">{{ $technician->user->phone ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($technician->review_count > 0)
                                        <span class="inline-flex items-center gap-1 font-bold text-ink"><x-icon name="star" class="size-4 fill-warning text-warning" /> {{ number_format((float) $technician->avg_rating, 1, ',', '') }}</span>
                                        <span class="text-muted">({{ $technician->review_count }} ulasan)</span>
                                    @else
                                        <span class="text-muted">Belum ada ulasan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-semibold text-ink">{{ $technician->active_orders_count }} aktif</span>
                                    <span class="text-muted">· {{ $technician->done_orders_count }} selesai</span>
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="$technician->is_active ? 'success' : 'neutral'" size="sm">{{ $technician->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-1">
                                        <form method="POST" action="{{ route('admin.technicians.toggle', $technician) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="inline-flex min-h-11 items-center rounded-[10px] px-3 text-[13px] font-bold text-ink-soft hover:bg-surface-bg hover:text-ink focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                                                {{ $technician->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.technicians.edit', $technician) }}" class="flex size-11 items-center justify-center rounded-[10px] text-primary hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                                            <x-icon name="pencil" class="size-4" /><span class="sr-only">Ubah {{ $technician->user->name }}</span>
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

    <p class="text-[13px] text-muted">Teknisi masuk lewat halaman login staf (<span class="font-semibold text-ink-soft">{{ route('login') }}</span>) dengan email & password dari admin. Teknisi nonaktif tidak bisa login dan tidak muncul saat assign.</p>
</x-layouts.admin>
