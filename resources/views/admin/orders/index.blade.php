@use('App\Enums\OrderStatus')
@use('App\Enums\OrderType')
@use('App\Http\Controllers\Admin\OrderController')
@use('App\Support\Format')

@php
    $hasDetail = Route::has('admin.orders.show');
    $filtered = collect($filters)->except('tab')->filter()->isNotEmpty();
    $today = now()->toDateString();
@endphp

<x-layouts.admin title="Order" subtitle="Semua order client: assign, pantau progres, dan konfirmasi pembayaran." :breadcrumbs="[['Order', null]]">
    <nav class="-mb-2 flex gap-2 overflow-x-auto pb-2" aria-label="Status order">
        @foreach (OrderController::TABS as $key => [$label])
            <a
                href="{{ route('admin.orders.index', array_merge(collect($filters)->except('tab')->all(), ['tab' => $key])) }}"
                @if ($tab === $key) aria-current="page" @endif
                @class([
                    'inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border-[1.5px] px-4 text-sm font-bold whitespace-nowrap',
                    'border-primary bg-primary text-white' => $tab === $key,
                    'border-line bg-white text-ink-soft hover:text-ink' => $tab !== $key,
                ])
            >
                {{ $label }}
                <span @class([
                    'rounded-full px-2 text-xs tabular-nums',
                    'bg-white/20' => $tab === $key,
                    'bg-warning text-navy' => $tab !== $key && $key === 'menunggu' && $tabCounts[$key] > 0,
                    'bg-surface-bg' => $tab !== $key && ! ($key === 'menunggu' && $tabCounts[$key] > 0),
                ])>{{ $tabCounts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.orders.index') }}" class="grid gap-3 rounded-card border border-line bg-white p-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))_auto]" role="search">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <x-input name="q" type="search" label="Cari" icon="search" :value="$filters['q'] ?? null" placeholder="Kode order, nama / no. HP client" />
        <x-input name="from" type="date" label="Jadwal dari" :value="$filters['from'] ?? null" />
        <x-input name="to" type="date" label="Sampai" :value="$filters['to'] ?? null" />
        <div>
            <x-label for="f-type">Jenis</x-label>
            <select id="f-type" name="type" class="min-h-12 w-full rounded-xl border-[1.5px] border-line bg-white px-3.5 text-[15px] text-ink focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15">
                <option value="">Semua jenis</option>
                @foreach (OrderType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-label for="f-technician">Teknisi</x-label>
            <select id="f-technician" name="technician" class="min-h-12 w-full rounded-xl border-[1.5px] border-line bg-white px-3.5 text-[15px] text-ink focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15">
                <option value="">Semua teknisi</option>
                <option value="none" @selected(($filters['technician'] ?? null) === 'none')>Belum ada teknisi</option>
                @foreach ($technicians as $technician)
                    <option value="{{ $technician->id }}" @selected(($filters['technician'] ?? null) === (string) $technician->id)>{{ $technician->user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <x-button type="submit" variant="secondary" icon="filter">Terapkan</x-button>
            @if ($filtered)
                <x-button :href="route('admin.orders.index', ['tab' => $tab])" variant="ghost">Reset</x-button>
            @endif
        </div>
    </form>

    <section class="overflow-hidden rounded-card border border-line bg-white">
        @if ($orders->isEmpty())
            <x-empty-state icon="clipboard-list" title="Tidak ada order" :description="$filtered ? 'Tidak ada order yang cocok dengan filter.' : 'Belum ada order di tab ini.'" />
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[1040px] text-left">
                    <thead class="border-b border-line bg-[#F8FAFD] text-xs font-bold tracking-[0.4px] whitespace-nowrap text-muted uppercase">
                        <tr>
                            <th class="px-4 py-2.5">Order</th>
                            <th class="px-4 py-2.5">Client &amp; alamat</th>
                            <th class="px-4 py-2.5">Unit &amp; layanan</th>
                            <th class="px-4 py-2.5">Jadwal</th>
                            <th class="px-4 py-2.5">Teknisi</th>
                            <th class="px-4 py-2.5 text-right">Total</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEF2F8] text-[13px]">
                        @foreach ($orders as $order)
                            @php($url = $hasDetail ? route('admin.orders.show', $order) : null)
                            <tr class="align-top hover:bg-surface-bg/60">
                                <td class="px-4 py-3">
                                    @if ($url)
                                        <a href="{{ $url }}" class="font-bold whitespace-nowrap text-ink hover:text-primary">{{ $order->code }}</a>
                                    @else
                                        <span class="font-bold whitespace-nowrap text-ink">{{ $order->code }}</span>
                                    @endif
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @if ($order->scheduled_start->toDateString() === $today && $order->status->isActive())
                                            <x-badge variant="danger" size="sm">Hari ini</x-badge>
                                        @endif
                                        @if ($order->order_type === OrderType::GARANSI)
                                            <x-badge variant="success" size="sm">Garansi</x-badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="max-w-60 px-4 py-3">
                                    <p class="font-semibold text-ink">{{ $order->client->name }}</p>
                                    <p class="truncate text-xs text-muted" title="{{ $order->address_snapshot }}">{{ Str::limit($order->address_snapshot, 40) }} · {{ Format::km($order->distance_km) }}</p>
                                </td>
                                <td class="max-w-60 px-4 py-3">
                                    <p class="font-semibold text-ink">{{ $order->items_count }} unit</p>
                                    <p class="text-xs text-muted">
                                        {{ $order->items->pluck('service_name')->unique()->implode(', ') }}
                                        @if ($order->requires_helper) · <b class="font-bold text-warning-900">wajib helper</b>@endif
                                    </p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="font-semibold text-ink">{{ Format::shortDate($order->scheduled_start) }}</p>
                                    <p class="text-xs text-muted tabular-nums">{{ $order->scheduled_start->format('H:i') }}–{{ $order->scheduled_end->format('H:i') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($order->technician)
                                        <p class="font-semibold whitespace-nowrap text-ink">{{ $order->technician->user->name }}</p>
                                        @if ($order->helper)<p class="text-xs whitespace-nowrap text-muted">+ {{ $order->helper->name }}</p>@endif
                                    @else
                                        <span class="text-muted">Belum ada</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-bold whitespace-nowrap text-ink tabular-nums">{{ Format::orderTotal($order) }}</td>
                                <td class="px-4 py-3"><x-badge :status="$order->status" size="sm" /></td>
                                <td class="px-4 py-2 text-right">
                                    @if ($url)
                                        @if ($order->status === OrderStatus::MENUNGGU)
                                            <x-button :href="$url" size="sm">Assign</x-button>
                                        @else
                                            <x-button :href="$url" size="sm" variant="secondary">Detail</x-button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($orders->hasPages())
        <div>{{ $orders->links() }}</div>
    @endif
</x-layouts.admin>
