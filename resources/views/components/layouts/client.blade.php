{{--
    Layout client mobile-first (kolom tengah maks. 480px).
    Props: title, back (url tombol kembali), nav (tampilkan navigasi bawah).
    Slot: header (ganti header bawaan), actions (kanan header), footer (aksi melekat di bawah).
--}}
@props(['title' => null, 'back' => null, 'nav' => true])

@php
    $items = [
        ['client.home', 'client.home', 'house', 'Beranda'],
        ['client.orders.index', 'client.orders.*', 'clipboard-list', 'Order'],
        ['client.addresses.index', 'client.addresses.*', 'map-pin', 'Alamat'],
        ['client.profile', 'client.profile*', 'user', 'Profil'],
    ];
@endphp

<x-layouts.base :title="$title" class="min-h-screen bg-surface-bg">
    <div class="mx-auto flex min-h-screen max-w-[480px] flex-col bg-surface-bg md:border-x md:border-line">
        @isset($header)
            {{ $header }}
        @elseif ($title)
            <header class="sticky top-0 z-20 flex min-h-[72px] items-center gap-3 border-b border-line bg-white px-4 py-3">
                @if ($back)
                    <a href="{{ $back }}" class="flex size-12 shrink-0 items-center justify-center rounded-xl border-[1.5px] border-line bg-white text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                        <x-icon name="chevron-left" class="size-6" />
                        <span class="sr-only">Kembali</span>
                    </a>
                @endif
                <h1 class="min-w-0 flex-1 truncate text-xl font-extrabold tracking-tight text-ink">{{ $title }}</h1>
                {{ $actions ?? '' }}
            </header>
        @endisset

        <main @class(['flex-1 px-4 pt-5', 'pb-28' => $nav && ! isset($footer), 'pb-6' => ! $nav || isset($footer)])>
            <x-flash class="mb-4" />
            {{ $slot }}
        </main>

        @isset($footer)
            <div class="sticky bottom-0 z-20 border-t border-line bg-white px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                {{ $footer }}
            </div>
        @endisset

        @if ($nav && ! isset($footer))
            <nav aria-label="Navigasi utama" class="fixed inset-x-0 bottom-0 z-30">
                <ul class="mx-auto grid max-w-[480px] grid-cols-4 border-t border-line bg-white pb-[env(safe-area-inset-bottom)] md:border-x">
                    @foreach ($items as [$route, $pattern, $icon, $label])
                        @php($active = request()->routeIs($pattern))
                        <li>
                            <a
                                href="{{ Route::has($route) ? route($route) : '#' }}"
                                @if ($active) aria-current="page" @endif
                                @class([
                                    'flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-semibold focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-primary/25',
                                    'text-primary' => $active,
                                    'text-muted hover:text-ink' => ! $active,
                                ])
                            >
                                <x-icon :name="$icon" class="size-6" />
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
</x-layouts.base>
