{{-- Tombol aksi tugas sesuai status (terima/tolak, berangkat, mulai, laporan). --}}
@use('App\Enums\OrderStatus')

@php
    $mapsUrl = 'https://www.google.com/maps/dir/?api=1&destination='.$order->lat.','.$order->lng;
@endphp

<div class="flex gap-2">
    @switch($order->status)
        @case(OrderStatus::DITUGASKAN)
            @if (Route::has('teknisi.tasks.reject'))
                <x-button variant="danger" class="flex-1" x-on:click="$dispatch('open-modal', 'reject-{{ $order->id }}')">Tolak</x-button>
                <form method="POST" action="{{ route('teknisi.tasks.accept', $order) }}" class="flex-[2]">
                    @csrf
                    <x-button type="submit" block>Terima tugas</x-button>
                </form>
            @endif
            @break

        @case(OrderStatus::DIKONFIRMASI)
        @case(OrderStatus::DALAM_PERJALANAN)
            <x-button :href="$mapsUrl" variant="secondary" icon="navigation" class="flex-1" target="_blank" rel="noopener">Navigasi</x-button>
            @if (Route::has('teknisi.tasks.depart'))
                <form method="POST" action="{{ route($order->status === OrderStatus::DIKONFIRMASI ? 'teknisi.tasks.depart' : 'teknisi.tasks.start', $order) }}" class="flex-[2]">
                    @csrf
                    <x-button type="submit" block>{{ $order->status === OrderStatus::DIKONFIRMASI ? 'Berangkat' : 'Tiba & mulai kerja' }}</x-button>
                </form>
            @endif
            @break

        @case(OrderStatus::DIKERJAKAN)
            @if (Route::has('teknisi.reports.edit'))
                <x-button :href="route('teknisi.reports.edit', $order)" icon="clipboard-pen" block>Isi laporan</x-button>
            @endif
            @break
    @endswitch
</div>
