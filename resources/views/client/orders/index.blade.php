@use('App\Http\Controllers\Client\OrderController')
@use('App\Support\Format')

<x-layouts.client title="Order saya">
    <x-slot:actions>
        <x-button :href="route('client.orders.create')" size="sm" icon="plus">Buat</x-button>
    </x-slot:actions>

    <nav class="-mx-4 mb-4 flex border-b border-line bg-white px-4" aria-label="Filter order">
        @foreach (OrderController::TABS as $key => [$label])
            <a
                href="{{ route('client.orders.index', ['tab' => $key]) }}"
                @if ($tab === $key) aria-current="page" @endif
                @class([
                    'inline-flex min-h-12 flex-1 items-center justify-center gap-1.5 border-b-[2.5px] text-[15px] font-bold',
                    'border-primary text-primary' => $tab === $key,
                    'border-transparent text-muted hover:text-ink' => $tab !== $key,
                ])
            >
                {{ $label }}
                @if ($counts[$key] > 0)
                    <span class="rounded-full bg-surface-bg px-1.5 text-xs text-ink-soft">{{ $counts[$key] }}</span>
                @endif
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
        <ul class="space-y-3">
            @foreach ($orders as $order)
                <li>
                    <a href="{{ route('client.orders.show', $order) }}" class="block rounded-2xl border-[1.5px] border-line bg-white p-4 hover:border-primary focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex items-center gap-2 font-extrabold text-ink">
                                    {{ $order->code }}
                                    @if ($order->order_type === \App\Enums\OrderType::GARANSI)
                                        <x-badge variant="navy" size="sm">Garansi</x-badge>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-sm text-muted">{{ Format::schedule($order->scheduled_start, $order->scheduled_end, true) }}</p>
                            </div>
                            <x-badge :status="$order->status" size="sm" />
                        </div>
                        <p class="mt-3 truncate text-[15px] text-ink-soft">
                            {{ $order->items_count }} unit · {{ $order->items->pluck('service_name')->unique()->implode(', ') }}
                        </p>
                        <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                            <span class="truncate text-muted">{{ $order->technician ? 'Teknisi '.$order->technician->user->name : 'Belum ada teknisi' }}</span>
                            <span class="shrink-0 font-bold text-ink tabular-nums">
                                {{ $order->grand_total !== null ? Format::rupiah($order->grand_total) : Format::rupiahRange($order->est_price_min + $order->transport_fee, $order->est_price_max + $order->transport_fee) }}
                            </span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.client>
