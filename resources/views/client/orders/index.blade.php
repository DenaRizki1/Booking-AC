@use('App\Enums\OrderType')
@use('App\Http\Controllers\Client\OrderController')
@use('App\Support\Format')

@php
    $total = fn ($order) => $order->grand_total !== null
        ? Format::rupiah($order->grand_total)
        : Format::rupiahRange($order->est_price_min + $order->transport_fee, $order->est_price_max + $order->transport_fee);
@endphp

<x-layouts.client title="Order saya" subtitle="Pantau status, laporan, dan pembayaran order Anda.">
    <x-slot:actions>
        <x-button :href="route('client.orders.create')" icon="plus">Buat Order</x-button>
    </x-slot:actions>

    <nav class="mb-5 flex gap-2 overflow-x-auto" aria-label="Filter order">
        @foreach (OrderController::TABS as $key => [$label])
            <a
                href="{{ route('client.orders.index', ['tab' => $key]) }}"
                @if ($tab === $key) aria-current="page" @endif
                @class([
                    'inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border-[1.5px] px-4 text-[15px] font-bold',
                    'border-primary bg-primary text-white' => $tab === $key,
                    'border-line bg-white text-ink-soft hover:text-ink' => $tab !== $key,
                ])
            >
                {{ $label }}
                <span @class(['rounded-full px-2 text-xs', 'bg-white/20' => $tab === $key, 'bg-surface-bg' => $tab !== $key])>{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($orders->isEmpty())
        <x-card>
            <x-empty-state icon="clipboard-list" :title="match ($tab) { 'aktif' => 'Tidak ada order aktif', 'selesai' => 'Belum ada order selesai', default => 'Tidak ada order dibatalkan' }" description="Order yang Anda buat akan tampil di sini.">
                @if ($tab === 'aktif')
                    <x-button :href="route('client.orders.create')" size="sm" icon="plus">Buat order</x-button>
                @endif
            </x-empty-state>
        </x-card>
    @else
        {{-- Desktop: tabel --}}
        <div class="hidden overflow-hidden rounded-card border border-line bg-white lg:block">
            <table class="w-full text-left">
                <thead class="border-b border-line bg-[#F8FAFD] text-[11px] font-bold tracking-wide text-muted uppercase">
                    <tr>
                        <th class="px-5 py-3">Order</th>
                        <th class="px-5 py-3">Jadwal</th>
                        <th class="px-5 py-3">Unit & layanan</th>
                        <th class="px-5 py-3">Teknisi</th>
                        <th class="px-5 py-3 text-right">Total</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EEF2F8] text-sm">
                    @foreach ($orders as $order)
                        <tr class="cursor-pointer hover:bg-surface-bg" onclick="window.location='{{ route('client.orders.show', $order) }}'">
                            <td class="px-5 py-4">
                                <a href="{{ route('client.orders.show', $order) }}" class="font-extrabold whitespace-nowrap text-ink hover:text-primary">{{ $order->code }}</a>
                                @if ($order->order_type === OrderType::GARANSI)
                                    <x-badge variant="navy" size="sm" class="ml-1">Garansi</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-ink-soft">{{ Format::date($order->scheduled_start) }}<span class="block text-[13px] text-muted">{{ $order->scheduled_start->format('H:i') }}–{{ $order->scheduled_end->format('H:i') }}</span></td>
                            <td class="max-w-64 truncate px-5 py-4 text-ink-soft">{{ $order->items_count }} unit · {{ $order->items->pluck('service_name')->unique()->implode(', ') }}</td>
                            <td class="px-5 py-4 text-ink-soft">{{ $order->technician?->user->name ?? '–' }}</td>
                            <td class="px-5 py-4 text-right font-bold whitespace-nowrap text-ink tabular-nums">{{ $total($order) }}</td>
                            <td class="px-5 py-4"><x-badge :status="$order->status" size="sm" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Layar kecil: kartu --}}
        <ul class="grid gap-3 md:grid-cols-2 lg:hidden">
            @foreach ($orders as $order)
                <li>
                    <a href="{{ route('client.orders.show', $order) }}" class="block rounded-2xl border-[1.5px] border-line bg-white p-4 hover:border-primary">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-extrabold text-ink">{{ $order->code }}</p>
                                <p class="mt-0.5 text-sm text-muted">{{ Format::schedule($order->scheduled_start, $order->scheduled_end, true) }}</p>
                            </div>
                            <x-badge :status="$order->status" size="sm" />
                        </div>
                        <p class="mt-3 truncate text-[15px] text-ink-soft">{{ $order->items_count }} unit · {{ $order->items->pluck('service_name')->unique()->implode(', ') }}</p>
                        <p class="mt-1 text-right text-sm font-bold text-ink">{{ $total($order) }}</p>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.client>
