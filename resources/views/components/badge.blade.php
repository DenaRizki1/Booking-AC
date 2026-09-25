{{-- Badge umum (:variant) atau badge status order (:status = OrderStatus / string). --}}
@props(['status' => null, 'variant' => 'neutral', 'size' => 'md'])

@php
    if ($status !== null && ! $status instanceof \App\Enums\OrderStatus) {
        $status = \App\Enums\OrderStatus::from($status);
    }

    $variants = [
        'neutral' => 'bg-surface-bg text-ink-soft',
        'primary' => 'bg-blue-50 text-primary',
        'navy' => 'bg-navy text-white',
        'success' => 'bg-success-50 text-success-700',
        'warning' => 'bg-warning-50 text-warning-900',
        'danger' => 'bg-danger-50 text-danger-700',
    ];

    $sizes = [
        'sm' => 'h-[22px] px-2 text-[11px]',
        'md' => 'h-[26px] px-2.5 text-xs',
    ];

    $color = $status?->color() ?? ($variants[$variant] ?? $variants['neutral']);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 whitespace-nowrap rounded-full font-bold '.$color.' '.($sizes[$size] ?? $sizes['md'])]) }}>
    {{ $slot->isEmpty() && $status ? $status->label() : $slot }}
</span>
