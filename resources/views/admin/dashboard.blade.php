@use('App\Enums\OrderType')
@use('App\Support\Format')

@php
    $short = fn (string $name) => collect(explode(' ', trim($name)))->take(2)->map(fn ($part, $i) => $i === 0 ? $part : mb_substr($part, 0, 1).'.')->implode(' ');
@endphp

<x-layouts.admin title="Dashboard" :subtitle="now()->translatedFormat('l').', '.now()->translatedFormat('j F Y')">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.orders.index') }}" class="flex flex-col gap-1.5 rounded-card border border-primary bg-primary px-5 py-[18px] text-white hover:bg-primary-hover">
            <span class="text-[13px] font-semibold text-white/85">Menunggu assign</span>
            <span class="text-[28px] font-extrabold tracking-tight tabular-nums">{{ $waitingOrders }}</span>
            <span class="text-xs text-white/85">{{ $waitingToday }} dijadwalkan hari ini</span>
        </a>
        <a href="{{ route('admin.schedule') }}" class="flex flex-col gap-1.5 rounded-card border border-line bg-white px-5 py-[18px] hover:border-primary">
            <span class="text-[13px] font-semibold text-muted">Jadwal hari ini</span>
            <span class="text-[28px] font-extrabold tracking-tight text-navy tabular-nums">{{ $todayCount }}</span>
            <span class="text-xs text-muted">{{ $activeTechnicians }} teknisi aktif{{ $onLeaveTechnicians ? ' · '.$onLeaveTechnicians.' cuti' : '' }}</span>
        </a>
        <a href="{{ route('admin.orders.index', ['tab' => 'belum-lunas']) }}" class="flex flex-col gap-1.5 rounded-card border border-line bg-white px-5 py-[18px] hover:border-primary">
            <span class="text-[13px] font-semibold text-muted">Belum lunas</span>
            <span class="text-[28px] font-extrabold tracking-tight text-navy tabular-nums">{{ Format::rupiah($unpaidTotal) }}</span>
            <span class="text-xs text-muted">{{ $unpaidCount }} order selesai</span>
        </a>
        <div class="flex flex-col gap-1.5 rounded-card border border-line bg-white px-5 py-[18px]">
            <span class="text-[13px] font-semibold text-muted">Rating rata-rata</span>
            <span class="flex items-center gap-1.5 text-[28px] font-extrabold tracking-tight text-navy tabular-nums">
                {{ $reviewCount ? number_format($avgRating, 1, ',', '') : '–' }}
                <x-icon name="star" class="size-5 fill-warning text-warning" />
            </span>
            <span class="text-xs text-muted">dari {{ $reviewCount }} ulasan</span>
        </div>
    </div>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="overflow-hidden rounded-card border border-line bg-white">
            <div class="flex items-center gap-3 px-5 py-[18px]">
                <h2 class="flex-1 text-base font-extrabold text-ink">Order menunggu assign</h2>
                <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center text-[13px] font-bold text-primary">Lihat semua order</a>
            </div>
            @if ($waiting->isEmpty())
                <x-empty-state icon="circle-check" title="Semua order sudah di-assign" description="Order baru dari client akan muncul di sini." class="border-t border-line" />
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left">
                        <thead class="border-y border-line bg-[#F8FAFD] text-xs font-bold tracking-[0.4px] whitespace-nowrap text-muted uppercase">
                            <tr>
                                <th class="px-4 py-2.5">Order</th>
                                <th class="px-4 py-2.5">Client &amp; alamat</th>
                                <th class="px-4 py-2.5">Unit &amp; layanan</th>
                                <th class="px-4 py-2.5">Jadwal</th>
                                <th class="px-4 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEF2F8] text-[13px]">
                            @foreach ($waiting as $order)
                                <tr>
                                    <td class="px-4 py-3.5">
                                        <p class="font-bold whitespace-nowrap text-ink">{{ $order->code }}</p>
                                        @if ($order->scheduled_start->isToday())
                                            <x-badge variant="danger" size="sm" class="mt-1">Hari ini</x-badge>
                                        @elseif ($order->scheduled_start->isPast())
                                            <x-badge variant="danger" size="sm" class="mt-1">Terlewat</x-badge>
                                        @endif
                                        @if ($order->order_type === OrderType::GARANSI)
                                            <x-badge variant="success" size="sm" class="mt-1">Garansi</x-badge>
                                        @endif
                                    </td>
                                    <td class="max-w-56 px-4 py-3.5">
                                        <p class="font-semibold text-ink">{{ $order->client->name }}</p>
                                        <p class="truncate text-xs text-muted">{{ Str::limit($order->address_snapshot, 32) }} · {{ Format::km($order->distance_km) }}</p>
                                    </td>
                                    <td class="max-w-56 px-4 py-3.5">
                                        <p class="font-semibold text-ink">{{ $order->items_count }} unit</p>
                                        <p class="text-xs text-muted">
                                            @if ($order->parentOrder)
                                                Klaim dari {{ $order->parentOrder->code }}
                                            @else
                                                {{ $order->items->pluck('service_name')->unique()->implode(', ') }}
                                            @endif
                                            @if ($order->requires_helper) · <b class="font-bold text-warning-900">wajib helper</b>@endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <p class="font-semibold text-ink">{{ Format::shortDate($order->scheduled_start) }}</p>
                                        <p class="text-xs text-muted tabular-nums">{{ $order->scheduled_start->format('H:i') }}–{{ $order->scheduled_end->format('H:i') }}</p>
                                    </td>
                                    <td class="px-4 py-3.5 text-right"><x-button :href="route('admin.orders.show', $order)" size="sm">Assign</x-button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="flex flex-col gap-5">
            <section class="flex flex-col gap-3.5 rounded-card border border-line bg-white px-5 py-[18px]">
                <div class="flex items-center">
                    <h2 class="flex-1 text-base font-extrabold text-ink">Beban teknisi hari ini</h2>
                    <a href="{{ route('admin.schedule') }}" class="inline-flex min-h-11 items-center text-[13px] font-bold text-primary">Jadwal</a>
                </div>
                @forelse ($technicians as $technician)
                    @if ($technician->on_leave)
                        <div class="flex items-center justify-between text-[13px]">
                            <b class="text-muted">{{ $technician->user->name }}</b>
                            <x-badge size="sm">Cuti</x-badge>
                        </div>
                    @else
                        @php($minutes = (int) $technician->today_minutes)
                        <div class="flex flex-col gap-1.5">
                            <div class="flex justify-between gap-2 text-[13px]">
                                <b class="truncate text-ink">{{ $technician->user->name }}</b>
                                <span class="shrink-0 text-muted tabular-nums">{{ $technician->today_orders }} order · {{ Format::decimal($minutes / 60) }} jam</span>
                            </div>
                            <div class="h-2 rounded bg-[#EEF2F8]">
                                <div class="h-2 rounded bg-primary" style="width: {{ min(100, round($minutes / $operatingMinutes * 100)) }}%"></div>
                            </div>
                        </div>
                    @endif
                @empty
                    <p class="text-sm text-muted">Belum ada teknisi aktif.</p>
                @endforelse
            </section>

            <section class="flex flex-col gap-3 rounded-card border border-line bg-white px-5 py-[18px]">
                <h2 class="text-base font-extrabold text-ink">Ulasan terbaru</h2>
                @forelse ($latestReviews as $review)
                    @if (! $loop->first)<div class="h-px bg-[#EEF2F8]"></div>@endif
                    <div class="flex flex-col gap-1">
                        <div class="flex justify-between gap-2 text-[13px]">
                            <b class="truncate text-ink">{{ $short($review->client->name) }} → {{ $review->technician?->user->name ? $short($review->technician->user->name) : '–' }}</b>
                            <span class="shrink-0 tracking-[1px]" aria-label="{{ $review->rating }} dari 5 bintang">
                                <span class="text-warning">{{ str_repeat('★', $review->rating) }}</span><span class="text-[#D5DEEE]">{{ str_repeat('★', 5 - $review->rating) }}</span>
                            </span>
                        </div>
                        @if ($review->comment)
                            <p class="text-xs leading-relaxed text-ink-soft">{{ Str::limit($review->comment, 120) }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-muted">Belum ada ulasan.</p>
                @endforelse
            </section>
        </div>
    </div>
</x-layouts.admin>
