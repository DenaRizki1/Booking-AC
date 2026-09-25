@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'iconRight' => null,
    'block' => false,
])

@php
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-hover',
        'secondary' => 'border-[1.5px] border-blue-200 bg-white text-primary hover:bg-blue-50',
        'danger' => 'border-[1.5px] border-danger-200 bg-white text-danger hover:bg-danger-50',
        'danger-solid' => 'bg-danger text-white hover:bg-danger-700',
        'ghost' => 'text-ink-soft hover:bg-surface-bg hover:text-ink',
    ];

    // Semua ukuran memenuhi target sentuh minimal 44px.
    $sizes = [
        'sm' => 'min-h-11 px-4 text-sm',
        'md' => 'min-h-12 px-5 text-[15px]',
        'lg' => 'min-h-14 px-6 text-base',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-xl font-bold transition-colors select-none',
        'focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25',
        'disabled:cursor-not-allowed disabled:opacity-50',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
        $block ? 'w-full' : '',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-5" />@endif
        {{ $slot }}
        @if ($iconRight)<x-icon :name="$iconRight" class="size-5" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-5" />@endif
        {{ $slot }}
        @if ($iconRight)<x-icon :name="$iconRight" class="size-5" />@endif
    </button>
@endif
