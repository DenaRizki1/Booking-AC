@use('App\Enums\OrderStatus')
@use('App\Support\Format')

@php
    $active = in_array($order->status, \App\Http\Controllers\Teknisi\TaskController::ACTIVE, true);
    $mapsUrl = 'https://www.google.com/maps/dir/?api=1&destination='.$order->lat.','.$order->lng;
@endphp

<x-layouts.teknisi :title="$order->code" :back="route('teknisi.tasks')">
    <x-slot:headerActions>
        @include('teknisi.tasks.partials.status')
    </x-slot:headerActions>

    <x-card>
        <p class="text-[13px] font-semibold text-muted">Jadwal</p>
        <p class="text-lg font-extrabold text-ink tabular-nums">{{ Format::schedule($order->scheduled_start, $order->scheduled_end, true) }}</p>
        <p class="text-xs text-muted">Estimasi {{ Format::duration($order->total_est_minutes) }} · selesai paling lambat {{ $order->blocked_until->format('H:i') }} termasuk perjalanan</p>
        @if ($order->parentOrder)
            <p class="mt-3 rounded-xl bg-success-50 px-3 py-2 text-[13px] text-success-700">Klaim garansi dari order <b>{{ $order->parentOrder->code }}</b> — biaya Rp0 kecuali kerusakan di luar garansi.</p>
        @endif
    </x-card>

    <section class="overflow-hidden rounded-card border border-line bg-white">
        <div class="relative isolate h-44 bg-[#E8EDF5]" x-data="miniMap(@js(['lat' => (float) $order->lat, 'lng' => (float) $order->lng]))">
            <div x-ref="map" class="absolute inset-0 z-0" aria-label="Peta lokasi client"></div>
        </div>
        <div class="flex flex-col gap-3 p-5">
            <div class="flex items-center gap-3">
                <x-avatar :name="$order->client->name" size="sm" />
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-ink">{{ $order->client->name }}</p>
                    <p class="text-xs text-muted tabular-nums">{{ $order->client->phone ?? 'No. HP belum diisi' }}</p>
                </div>
                @if ($order->client->phone && $active)
                    <a href="{{ Format::whatsappUrl($order->client->phone) }}" target="_blank" rel="noopener" class="flex size-11 items-center justify-center rounded-xl border-[1.5px] border-blue-200 text-primary hover:bg-blue-50">
                        <x-icon name="message-circle" class="size-5" /><span class="sr-only">WhatsApp client</span>
                    </a>
                @endif
            </div>
            <p class="flex items-start gap-2 text-sm text-ink-soft"><x-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-muted" />{{ $order->address_snapshot }} · {{ Format::km($order->distance_km) }}</p>
            @if ($active)
                <x-button :href="$mapsUrl" variant="secondary" icon="navigation" block target="_blank" rel="noopener">Buka navigasi Google Maps</x-button>
            @endif
        </div>
    </section>

    <x-card :title="'Unit & layanan ('.$order->items->count().')'">
        <ul class="flex flex-col divide-y divide-[#EEF2F8]">
            @foreach ($order->items as $item)
                <li class="flex justify-between gap-3 py-2.5 first:pt-0 last:pb-0 text-sm">
                    <div>
                        <p class="font-bold text-ink">{{ $item->service_name }}</p>
                        <p class="text-[13px] text-ink-soft">{{ $item->unit_label }}</p>
                        @if ($item->final_price !== null)
                            <p class="mt-1 text-xs text-muted">Harga final {{ Format::rupiah($item->final_price) }}</p>
                        @endif
                    </div>
                    <div class="shrink-0 text-right text-[13px]">
                        <p class="font-semibold text-ink tabular-nums">{{ Format::rupiahRange($item->est_price_min, $item->est_price_max) }}</p>
                        <p class="text-muted">{{ Format::duration($item->est_minutes) }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-card>

    @if ($order->customer_note || $order->helper)
        <x-card>
            <div class="flex flex-col gap-3 text-sm">
                @if ($order->helper)
                    <p class="flex items-center gap-2 text-ink-soft"><x-icon name="users" class="size-5 text-muted" /> Helper: <b class="text-ink">{{ $order->helper->name }}</b>@if ($order->helper->phone) · {{ $order->helper->phone }}@endif</p>
                @endif
                @if ($order->customer_note)
                    <div>
                        <p class="text-[13px] font-semibold text-muted">Catatan client</p>
                        <p class="text-ink">{{ $order->customer_note }}</p>
                    </div>
                @endif
            </div>
        </x-card>
    @endif

    @if ($active)
        <x-slot:footer>
            @include('teknisi.tasks.partials.actions')
        </x-slot:footer>

        @if ($order->status === OrderStatus::DITUGASKAN)
            @includeWhen(Route::has('teknisi.tasks.reject'), 'teknisi.tasks.partials.reject-modal')
        @endif
    @endif
</x-layouts.teknisi>
