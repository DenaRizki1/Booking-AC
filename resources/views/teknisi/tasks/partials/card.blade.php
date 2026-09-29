@use('App\Enums\OrderStatus')
@use('App\Support\Format')

@php
    $done = in_array($order->status, [OrderStatus::SELESAI, OrderStatus::LUNAS], true);
    $highlight = in_array($order->status, [OrderStatus::DALAM_PERJALANAN, OrderStatus::DIKERJAKAN], true);
@endphp

<article @class([
    'flex flex-col gap-2.5 rounded-[18px] border bg-white p-3.5',
    'border-line opacity-70' => $done,
    'border-[1.5px] border-primary shadow-[0_8px_22px_rgba(29,99,237,0.14)]' => $highlight,
    'border-line' => ! $done && ! $highlight,
])>
    <div class="flex items-center justify-between gap-2">
        <span class="text-[15px] font-extrabold text-ink tabular-nums">
            @if (! $order->scheduled_start->isToday()){{ Format::shortDate($order->scheduled_start) }} · @endif{{ $order->scheduled_start->format('H:i') }} – {{ $order->scheduled_end->format('H:i') }}
        </span>
        @include('teknisi.tasks.partials.status')
    </div>

    <a href="{{ route('teknisi.tasks.show', $order) }}" class="-m-1 rounded-xl p-1 hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
        @if ($done)
            <p class="text-[13px] text-ink-soft">{{ $order->code }} · {{ $order->client->name }} · {{ $order->items_count }} unit {{ $order->items->pluck('service_name')->unique()->implode(', ') }}</p>
        @else
            <p class="text-base font-extrabold text-ink">{{ $order->client->name }}</p>
            <p class="text-xs text-muted">
                {{ $order->code }}
                @if ($order->order_type === \App\Enums\OrderType::GARANSI) · <b class="font-bold text-success-700">Garansi</b>@endif
                @if ($order->scheduled_start->lt(today())) · <b class="font-bold text-danger">Terlewat</b>@endif
            </p>
            <p class="mt-2 flex items-start gap-1.5 text-xs text-ink-soft">
                <x-icon name="map-pin" class="mt-px size-4 shrink-0 text-muted" />
                <span>{{ Str::limit($order->address_snapshot, 70) }} · {{ Format::km($order->distance_km) }}</span>
            </p>
            <ul class="mt-2 flex flex-col gap-1 rounded-xl bg-surface-bg px-3 py-2 text-[13px] text-ink-soft">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-2"><span><b class="text-ink">{{ $item->service_name }}</b> · {{ $item->unit_label }}</span><span class="shrink-0 text-muted tabular-nums">{{ Format::duration($item->est_minutes) }}</span></li>
                @endforeach
            </ul>
            @if ($order->helper)
                <p class="mt-2 flex items-center gap-1.5 text-xs text-ink-soft"><x-icon name="users" class="size-4 text-muted" /> Helper: {{ $order->helper->name }}</p>
            @endif
        @endif
    </a>

    @unless ($done)
        @include('teknisi.tasks.partials.actions')
    @endunless
</article>
