{{-- Lonceng notifikasi + jumlah belum dibaca. Variant: light (latar putih) | dark (header navy). --}}
@props(['variant' => 'light'])

@php
    $unread = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
    $active = request()->routeIs('notifications.*');
@endphp

<a
    href="{{ route('notifications.index') }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'relative flex size-11 shrink-0 items-center justify-center rounded-xl focus-visible:outline-none focus-visible:ring-4',
        'text-ink-soft hover:bg-surface-bg hover:text-ink focus-visible:ring-primary/25' => $variant === 'light',
        'text-white/85 hover:bg-white/10 hover:text-white focus-visible:ring-white/25' => $variant === 'dark',
        'text-primary!' => $active && $variant === 'light',
    ]) }}
>
    <x-icon name="bell" class="size-5" />
    @if ($unread > 0)
        <span class="absolute top-1.5 right-1.5 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#E5484D] px-1 text-[10px] font-extrabold text-white ring-2 {{ $variant === 'dark' ? 'ring-navy' : 'ring-white' }}" data-unread="{{ $unread }}">{{ $unread > 99 ? '99+' : $unread }}</span>
    @endif
    <span class="sr-only">Notifikasi{{ $unread ? " ({$unread} belum dibaca)" : '' }}</span>
</a>
