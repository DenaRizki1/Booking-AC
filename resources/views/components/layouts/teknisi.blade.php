{{-- Panel web teknisi dengan sidebar, header halaman, dan area aksi. --}}
@props(['title' => null, 'back' => null, 'subtitle' => null])

@php
    $user = auth()->user();
    $company = \App\Models\CompanySettings::companyName();
    $links = [
        ['teknisi.tasks', ['teknisi.tasks*', 'teknisi.reports.*'], 'clipboard-list', 'Tugas'],
        ['teknisi.reviews', ['teknisi.reviews*'], 'star', 'Ulasan'],
        ['teknisi.account', ['teknisi.account*'], 'user-round', 'Akun'],
    ];
@endphp

<x-layouts.base :title="$title" class="min-h-screen bg-surface-bg">
    <div x-data="{ sidebar: false }" x-on:keydown.escape.window="sidebar = false" class="min-h-screen lg:pl-[248px]">
        <div x-show="sidebar" x-cloak x-transition.opacity x-on:click="sidebar = false" class="fixed inset-0 z-40 bg-navy/40 lg:hidden"></div>

        <aside id="teknisi-sidebar" :class="sidebar && 'max-lg:translate-x-0!'" class="fixed inset-y-0 left-0 z-50 flex w-[248px] flex-col bg-navy px-4 py-6 text-white transition-transform max-lg:-translate-x-full" aria-label="Menu teknisi">
            <a href="{{ route('teknisi.tasks') }}" class="flex items-center gap-2.5 px-2 pb-6">
                <x-brand-logo />
                <span class="min-w-0">
                    <span class="block truncate text-[17px] font-extrabold">{{ $company }}</span>
                    <span class="block text-[11px] text-white/60">Panel Teknisi</span>
                </span>
            </a>

            <nav class="flex flex-1 flex-col gap-1 overflow-y-auto" aria-label="Navigasi teknisi">
                @foreach ($links as [$route, $patterns, $icon, $label])
                    @php($active = request()->routeIs(...$patterns))
                    <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif @class([
                        'flex min-h-11 items-center gap-3 rounded-[10px] px-3.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/25',
                        'bg-primary text-white' => $active,
                        'text-white/75 hover:bg-white/5 hover:text-white' => ! $active,
                    ])>
                        <x-icon :name="$icon" class="size-5" />
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            @if ($user)
                <div class="mt-4 flex items-center gap-2.5 rounded-xl bg-white/5 p-3">
                    <x-avatar :name="$user->name" :src="$user->avatar" size="sm" class="bg-white! text-navy!" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-bold">{{ $user->name }}</p>
                        <p class="truncate text-[11px] text-white/60">{{ $user->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex size-11 items-center justify-center rounded-xl text-white/75 hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/25" title="Keluar">
                            <x-icon name="log-out" class="size-5" /><span class="sr-only">Keluar</span>
                        </button>
                    </form>
                </div>
            @endif
        </aside>

        <main class="mx-auto flex min-h-screen w-full max-w-[1400px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-7">
            <header class="flex flex-wrap items-center gap-3 sm:gap-4">
                <button type="button" x-on:click="sidebar = true" :aria-expanded="sidebar" aria-controls="teknisi-sidebar" class="flex size-11 items-center justify-center rounded-xl border border-line bg-white text-ink hover:bg-surface-bg lg:hidden">
                    <x-icon name="menu" class="size-5" /><span class="sr-only">Buka menu</span>
                </button>
                @if ($back)
                    <a href="{{ $back }}" class="flex size-11 items-center justify-center rounded-xl border border-line bg-white text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                        <x-icon name="chevron-left" class="size-5" /><span class="sr-only">Kembali</span>
                    </a>
                @endif
                <div class="min-w-0 flex-1">
                    <h1 class="text-2xl font-extrabold tracking-tight text-ink sm:text-[26px]">{{ $title }}</h1>
                    @if ($subtitle)<p class="text-[13px] text-muted">{{ $subtitle }}</p>@endif
                </div>
                {{ $headerActions ?? '' }}
                @unless (request()->routeIs('notifications.*'))<x-notification-bell class="border border-line bg-white" />@endunless
            </header>

            <x-flash />

            @isset($header)
                <section class="flex flex-col gap-5 rounded-card bg-navy p-5 text-white sm:p-6">
                    {{ $header }}
                </section>
            @endisset

            {{ $slot }}

            @isset($footer)
                <div class="sticky bottom-0 z-30 rounded-card border border-line bg-white p-4 shadow-sm sm:p-5">
                    {{ $footer }}
                </div>
            @endisset
        </main>
    </div>
</x-layouts.base>
