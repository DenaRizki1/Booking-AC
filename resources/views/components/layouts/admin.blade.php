{{--
    Layout panel admin: sidebar navy (drawer di layar < lg) + topbar + breadcrumb + flash.
    Props: title, subtitle, breadcrumbs (list [label, url|null]).
    Slot: actions (kanan judul).
    $menu & $pendingOrders dari App\View\Composers\AdminLayoutComposer.
--}}
@props(['title' => null, 'subtitle' => null, 'breadcrumbs' => []])

@php
    $user = auth()->user();
    $company = \App\Models\CompanySettings::companyName();
@endphp

<x-layouts.base :title="$title" class="min-h-screen bg-surface-bg">
    <div x-data="{ sidebar: false }" x-on:keydown.escape.window="sidebar = false" class="min-h-screen lg:pl-[248px]">
        {{-- Latar drawer (layar kecil) --}}
        <div x-show="sidebar" x-cloak x-transition.opacity x-on:click="sidebar = false" class="fixed inset-0 z-40 bg-navy/40 lg:hidden"></div>

        {{-- Sidebar --}}
        <aside
            :class="sidebar && 'max-lg:translate-x-0!'"
            class="fixed inset-y-0 left-0 z-50 flex w-[248px] flex-col bg-navy px-4 py-6 text-white transition-transform max-lg:-translate-x-full"
            aria-label="Menu admin"
        >
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 px-2 pb-6">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-primary">
                    <x-icon name="snowflake" class="size-5" stroke-width="2.2" />
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-[17px] font-extrabold">{{ $company }}</span>
                    <span class="block text-[11px] text-white/60">Panel Admin</span>
                </span>
            </a>

            <nav class="-mx-1 flex flex-1 flex-col gap-1 overflow-y-auto px-1">
                @foreach ($menu as $group => $items)
                    @if ($group !== '')
                        <p class="px-3.5 pt-[18px] pb-1.5 text-[11px] font-bold tracking-[0.8px] text-white/50 uppercase">{{ $group }}</p>
                    @endif
                    @foreach ($items as $item)
                        @if (Route::has($item['route']))
                            @php($active = request()->routeIs($item['active']))
                            <a
                                href="{{ route($item['route']) }}"
                                @if ($active) aria-current="page" @endif
                                @class([
                                    'flex min-h-11 items-center gap-3 rounded-[10px] px-3.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/25',
                                    'bg-primary text-white' => $active,
                                    'text-white/75 hover:bg-white/5 hover:text-white' => ! $active,
                                ])
                            >
                                <x-icon :name="$item['icon']" class="size-5" />
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if (($item['badge'] ?? false) && $pendingOrders > 0)
                                    <span class="inline-flex h-[22px] min-w-[22px] items-center justify-center rounded-full bg-warning px-1.5 text-[11px] font-extrabold text-navy" title="Order menunggu">{{ $pendingOrders }}</span>
                                @endif
                            </a>
                        @else
                            {{-- Fitur belum tersedia (task berikutnya). --}}
                            <span aria-disabled="true" class="flex min-h-11 cursor-default items-center gap-3 rounded-[10px] px-3.5 text-sm font-semibold text-white/35">
                                <x-icon :name="$item['icon']" class="size-5" />
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if (($item['badge'] ?? false) && $pendingOrders > 0)
                                    <span class="inline-flex h-[22px] min-w-[22px] items-center justify-center rounded-full bg-warning px-1.5 text-[11px] font-extrabold text-navy" title="Order menunggu">{{ $pendingOrders }}</span>
                                @else
                                    <span class="text-[10px] font-bold tracking-wide uppercase">Segera</span>
                                @endif
                            </span>
                        @endif
                    @endforeach
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
                        <button type="submit" class="flex size-11 items-center justify-center rounded-[10px] text-white/75 hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/25" title="Keluar">
                            <x-icon name="log-out" class="size-5" /><span class="sr-only">Keluar</span>
                        </button>
                    </form>
                </div>
            @endif
        </aside>

        {{-- Konten --}}
        <main class="mx-auto flex max-w-[1400px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-7">
            <header class="flex flex-wrap items-center gap-4">
                <button type="button" x-on:click="sidebar = true" class="flex size-11 items-center justify-center rounded-xl border border-line bg-white text-ink hover:bg-surface-bg lg:hidden">
                    <x-icon name="menu" class="size-5" /><span class="sr-only">Buka menu</span>
                </button>

                <div class="min-w-0 flex-1 basis-72">
                    @if ($breadcrumbs)
                        <nav aria-label="Breadcrumb" class="mb-1">
                            <ol class="flex flex-wrap items-center gap-1 text-[13px] text-muted">
                                @foreach ($breadcrumbs as [$crumbLabel, $crumbUrl])
                                    <li class="flex items-center gap-1">
                                        @if (! $loop->first)<x-icon name="chevron-right" class="size-3.5" />@endif
                                        @if ($crumbUrl && ! $loop->last)
                                            <a href="{{ $crumbUrl }}" class="font-semibold text-muted hover:text-primary">{{ $crumbLabel }}</a>
                                        @elseif ($loop->last)
                                            <span aria-current="page" class="font-semibold text-ink-soft">{{ $crumbLabel }}</span>
                                        @else
                                            <span>{{ $crumbLabel }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                    @endif
                    <h1 class="truncate text-[26px] font-extrabold tracking-tight text-ink">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="text-[13px] text-muted">{{ $subtitle }}</p>
                    @endif
                </div>

                {{ $actions ?? '' }}

                <a href="#" class="flex size-11 items-center justify-center rounded-xl border border-line bg-white text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                    <x-icon name="bell" class="size-5" /><span class="sr-only">Notifikasi</span>
                </a>
            </header>

            <x-flash />

            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
