{{--
    Layout panel teknisi: mobile-first (dipakai di lapangan), tetap rapi di laptop (kolom tengah maks. 672px).
    Props: title, back (url tombol kembali; bila diisi header ringkas putih).
    Slot: header (isi header navy, mis. ringkasan & tab), footer (bar aksi melekat di bawah).
--}}
@props(['title' => null, 'back' => null])

@php
    $user = auth()->user();
    $links = [
        ['teknisi.tasks', 'teknisi.tasks*', 'clipboard-list', 'Tugas'],
        ['teknisi.reviews', 'teknisi.reviews*', 'star', 'Ulasan'],
        ['teknisi.account', 'teknisi.account*', 'user-round', 'Akun'],
    ];
@endphp

<x-layouts.base :title="$title" class="min-h-screen bg-surface-bg">
    <div class="mx-auto flex min-h-screen w-full max-w-2xl flex-col bg-surface-bg md:border-x md:border-line">
        @if ($back)
            <header class="sticky top-0 z-30 flex items-center gap-2 border-b border-line bg-white px-2 py-2">
                <a href="{{ $back }}" class="flex size-11 items-center justify-center rounded-xl text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                    <x-icon name="chevron-left" class="size-6" /><span class="sr-only">Kembali</span>
                </a>
                <h1 class="min-w-0 flex-1 truncate text-base font-extrabold text-ink">{{ $title }}</h1>
                {{ $headerActions ?? '' }}
            </header>
        @else
            <header class="flex flex-col gap-4 bg-navy px-5 pt-5 pb-4 text-white">
                {{ $header ?? '' }}
            </header>
        @endif

        <main @class(['flex flex-1 flex-col gap-3 px-4 py-4', 'pb-28' => ! isset($footer), 'pb-44' => isset($footer)])>
            <x-flash />
            {{ $slot }}
        </main>

        @isset($footer)
            <div class="fixed inset-x-0 bottom-[68px] z-30">
                <div class="mx-auto max-w-2xl border-t border-line bg-white px-4 py-3 md:border-x">
                    {{ $footer }}
                </div>
            </div>
        @endisset

        {{-- Navigasi bawah --}}
        <nav class="fixed inset-x-0 bottom-0 z-40" aria-label="Navigasi teknisi">
            <div class="mx-auto flex max-w-2xl border-t border-line bg-white px-2 pt-1.5 pb-[max(0.375rem,env(safe-area-inset-bottom))] md:border-x">
                @foreach ($links as [$route, $pattern, $icon, $label])
                    @php($active = request()->routeIs($pattern))
                    @if (Route::has($route))
                        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif @class([
                            'flex min-h-14 flex-1 flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-semibold',
                            'text-primary' => $active,
                            'text-muted hover:text-ink' => ! $active,
                        ])>
                            <x-icon :name="$icon" class="size-6" :stroke-width="$active ? '2.2' : '1.8'" />
                            {{ $label }}
                        </a>
                    @else
                        <span aria-disabled="true" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-1 text-[11px] font-semibold text-line-strong">
                            <x-icon :name="$icon" class="size-6" />{{ $label }}
                        </span>
                    @endif
                @endforeach
            </div>
        </nav>
    </div>
</x-layouts.base>
