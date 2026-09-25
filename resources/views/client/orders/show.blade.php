{{-- Detail order — dilengkapi di T-605 (timeline, teknisi, unit), T-606–T-609. --}}
@use('App\Support\Format')

<x-layouts.client :title="$order->code" :back="route('client.home')">
    <x-card>
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="font-extrabold text-ink">{{ $order->code }}</p>
                <p class="text-sm text-muted">{{ Format::schedule($order->scheduled_start, $order->scheduled_end, true) }}</p>
            </div>
            <x-badge :status="$order->status" />
        </div>
    </x-card>
</x-layouts.client>
