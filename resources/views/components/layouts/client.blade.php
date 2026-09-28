{{--
    Layout web client: navbar atas + konten lebar (responsif; di layar kecil menu jadi tombol).
    Props: title, subtitle, back (url tombol kembali), width (default | narrow).
    Slot: actions (kanan judul), intro (di bawah judul, mis. stepper).
--}}
@props(['title' => null, 'subtitle' => null, 'back' => null, 'width' => 'default', 'heading' => true])

@php
    $user = auth()->user();
    $company = \App\Models\CompanySettings::companyName();
    $links = [
        ['client.home', 'client.home', 'house', 'Beranda'],
        ['client.orders.index', 'client.orders.index', 'clipboard-list', 'Order Saya'],
        ['client.addresses.index', 'client.addresses.*', 'map-pin', 'Alamat'],
    ];
    $container = $width === 'narrow' ? 'max-w-3xl' : 'max-w-6xl';
@endphp

<x-layouts.base :title="$title" class="flex min-h-screen flex-col bg-surface-bg">
    {{-- Navbar --}}
    <header x-data="{ menu: false, account: false }" class="sticky top-0 z-40 border-b border-line bg-white">
        <div class="mx-auto flex h-[72px] max-w-6xl items-center gap-4 px-4 sm:px-6 lg:gap-6">
            <a href="{{ route('client.home') }}" class="flex min-h-11 shrink-0 items-center gap-2.5">
                <span class="flex size-10 items-center justify-center rounded-xl bg-primary text-white">
                    <x-icon name="snowflake" class="size-6" stroke-width="2.2" />
                </span>
                <span class="text-lg font-extrabold tracking-tight text-navy">{{ $company }}</span>
            </a>

            <nav aria-label="Navigasi utama" class="hidden flex-1 items-center gap-1 md:flex">
                @foreach ($links as [$route, $pattern, $icon, $label])
                    @php($active = request()->routeIs($pattern))
                    <a
                        href="{{ route($route) }}"
                        @if ($active) aria-current="page" @endif
                        @class([
                            'inline-flex min-h-11 items-center gap-2 rounded-xl px-3 text-[15px] font-semibold whitespace-nowrap lg:px-4 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25',
                            'bg-blue-50 text-primary' => $active,
                            'text-ink-soft hover:bg-surface-bg hover:text-ink' => ! $active,
                        ])
                    >
                        <x-icon :name="$icon" class="size-5" /> {{ $label }}
                    </a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <span class="hidden lg:block"><x-button :href="route('client.orders.create')" size="sm" icon="plus" class="whitespace-nowrap">Buat Order</x-button></span>

                <a href="#" class="flex size-11 items-center justify-center rounded-xl text-ink-soft hover:bg-surface-bg hover:text-ink focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                    <x-icon name="bell" class="size-5" /><span class="sr-only">Notifikasi</span>
                </a>

                @if ($user)
                    <div class="relative hidden md:block" x-on:click.outside="account = false">
                        <button type="button" x-on:click="account = !account" :aria-expanded="account" class="flex min-h-11 items-center gap-2 rounded-xl px-2 hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                            <x-avatar :name="$user->name" :src="$user->avatar" size="sm" />
                            <span class="hidden max-w-40 truncate text-sm font-semibold text-ink lg:block">{{ $user->name }}</span>
                            <x-icon name="chevron-down" class="size-4 text-muted" />
                        </button>
                        <div x-show="account" x-cloak x-transition.origin.top.right class="absolute right-0 mt-2 w-56 rounded-2xl border border-line bg-white p-2 shadow-lg">
                            <p class="truncate px-3 pt-1 pb-2 text-xs text-muted">{{ $user->email }}</p>
                            <a href="{{ route('client.profile') }}" class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-ink hover:bg-surface-bg">
                                <x-icon name="user" class="size-4" /> Profil
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex min-h-11 w-full items-center gap-2 rounded-xl px-3 text-sm font-semibold text-danger hover:bg-danger-50">
                                    <x-icon name="log-out" class="size-4" /> Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <button type="button" x-on:click="menu = !menu" :aria-expanded="menu" class="flex size-11 items-center justify-center rounded-xl text-ink hover:bg-surface-bg md:hidden">
                    <x-icon name="menu" class="size-6" x-show="!menu" />
                    <x-icon name="x" class="size-6" x-show="menu" x-cloak />
                    <span class="sr-only">Menu</span>
                </button>
            </div>
        </div>

        {{-- Menu layar kecil --}}
        <nav x-show="menu" x-cloak x-transition class="border-t border-line bg-white px-4 py-3 md:hidden" aria-label="Navigasi utama">
            @foreach ($links as [$route, $pattern, $icon, $label])
                <a href="{{ route($route) }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-3 font-semibold', 'bg-blue-50 text-primary' => request()->routeIs($pattern), 'text-ink hover:bg-surface-bg' => ! request()->routeIs($pattern)])>
                    <x-icon :name="$icon" class="size-5" /> {{ $label }}
                </a>
            @endforeach
            <a href="{{ route('client.profile') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-3 font-semibold', 'bg-blue-50 text-primary' => request()->routeIs('client.profile*'), 'text-ink hover:bg-surface-bg' => ! request()->routeIs('client.profile*')])>
                <x-icon name="user" class="size-5" /> Profil
            </a>
            <x-button :href="route('client.orders.create')" icon="plus" block class="mt-2">Buat Order</x-button>
        </nav>
    </header>

    <main class="flex-1">
        <div class="mx-auto {{ $container }} px-4 py-6 sm:px-6 lg:py-8">
            @if ($heading && ($title || isset($actions)))
                <div class="mb-6 flex flex-wrap items-center gap-4">
                    @if ($back)
                        <a href="{{ $back }}" class="flex size-11 shrink-0 items-center justify-center rounded-xl border-[1.5px] border-line bg-white text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                            <x-icon name="chevron-left" class="size-5" /><span class="sr-only">Kembali</span>
                        </a>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-2xl font-extrabold tracking-tight text-navy lg:text-[28px]">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="mt-0.5 text-[15px] text-muted">{{ $subtitle }}</p>
                        @endif
                    </div>
                    {{ $actions ?? '' }}
                </div>
            @endif

            {{ $intro ?? '' }}

            <x-flash class="mb-5" />

            {{ $slot }}
        </div>
    </main>

    <footer class="border-t border-line bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-5 text-[13px] text-muted sm:px-6">
            <p>© {{ now()->year }} {{ $company }}. Bayar setelah pekerjaan selesai.</p>
            <p>Butuh bantuan? Hubungi admin lewat halaman detail order.</p>
        </div>
    </footer>
</x-layouts.base>
