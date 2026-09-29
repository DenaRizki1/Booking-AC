{{-- Badge status dari sudut pandang teknisi. --}}
@if ($order->status === \App\Enums\OrderStatus::DITUGASKAN)
    <x-badge variant="warning" size="sm">Perlu konfirmasi</x-badge>
@else
    <x-badge :status="$order->status" size="sm" />
@endif
