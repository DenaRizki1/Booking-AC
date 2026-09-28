@use('App\Support\Format')
@use('Illuminate\Support\Str')

<x-layouts.admin title="Client" subtitle="Pelanggan yang masuk lewat Google." :breadcrumbs="[['Master data', null], ['Client', null]]">
    <form method="GET" action="{{ route('admin.clients.index') }}" class="flex flex-wrap items-end gap-3" role="search">
        <div class="min-w-64 flex-1">
            <x-input name="q" type="search" label="Cari" icon="search" :value="$search" placeholder="Nama, email, atau no. HP" />
        </div>
        <div>
            <x-label for="f-status">Status</x-label>
            <select id="f-status" name="status" class="min-h-12 rounded-xl border-[1.5px] border-line bg-white px-3.5 text-[15px] text-ink focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15">
                <option value="">Semua</option>
                <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <x-button type="submit" variant="secondary" icon="search">Tampilkan</x-button>
        @if ($search !== '' || $status)
            <x-button :href="route('admin.clients.index')" variant="ghost">Reset</x-button>
        @endif
    </form>

    <section class="overflow-hidden rounded-card border border-line bg-white">
        @if ($clients->isEmpty())
            <x-empty-state icon="user-round" :title="$search !== '' || $status ? 'Client tidak ditemukan' : 'Belum ada client'" :description="$search !== '' || $status ? 'Coba kata kunci atau filter lain.' : 'Client terdaftar otomatis saat pertama kali masuk dengan Google.'" />
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[900px] text-left">
                    <thead class="border-b border-line bg-[#F8FAFD] text-xs font-bold tracking-[0.4px] whitespace-nowrap text-muted uppercase">
                        <tr>
                            <th class="px-4 py-2.5">Client</th>
                            <th class="px-4 py-2.5">No. HP</th>
                            <th class="px-4 py-2.5">Alamat</th>
                            <th class="px-4 py-2.5">Order</th>
                            <th class="px-4 py-2.5">Bergabung</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEF2F8] text-[13px]">
                        @foreach ($clients as $client)
                            @php($address = $client->addresses->first())
                            <tr @class(['bg-surface-bg/60' => ! $client->is_active])>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$client->name" :src="$client->avatar" size="sm" />
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-ink">{{ $client->name }}</p>
                                            <p class="whitespace-nowrap text-muted">{{ $client->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap tabular-nums">
                                    @if ($client->phone)
                                        <a href="{{ Format::whatsappUrl($client->phone) }}" target="_blank" rel="noopener" class="font-semibold text-ink hover:text-primary">{{ $client->phone }}</a>
                                    @else
                                        <span class="text-muted">Belum diisi</span>
                                    @endif
                                </td>
                                <td class="max-w-72 px-4 py-3">
                                    @if ($address)
                                        <p class="font-semibold text-ink">{{ $address->label }} <span class="font-normal text-muted">· {{ Format::km($address->distance_km) }}</span></p>
                                        <p class="truncate text-muted" title="{{ $address->address }}">{{ Str::limit($address->address, 60) }}</p>
                                        @if ($client->addresses_count > 1)<p class="text-xs text-muted">+{{ $client->addresses_count - 1 }} alamat lain</p>@endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-semibold text-ink">{{ $client->orders_count }} order</span>
                                    @if ($client->active_orders_count)<span class="text-muted">· {{ $client->active_orders_count }} aktif</span>@endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-muted">{{ Format::date($client->created_at) }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="$client->is_active ? 'success' : 'neutral'" size="sm">{{ $client->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <form method="POST" action="{{ route('admin.clients.toggle', $client) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" @class([
                                            'inline-flex min-h-11 items-center rounded-[10px] px-3 text-[13px] font-bold focus-visible:outline-none focus-visible:ring-4',
                                            'text-danger hover:bg-danger-50 focus-visible:ring-danger/25' => $client->is_active,
                                            'text-primary hover:bg-blue-50 focus-visible:ring-primary/25' => ! $client->is_active,
                                        ])>
                                            {{ $client->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($clients->hasPages())
        <div>{{ $clients->links() }}</div>
    @endif
</x-layouts.admin>
