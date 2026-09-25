@php
    $company = \App\Models\CompanySettings::companyName();
@endphp

<x-layouts.base title="Masuk Admin / Teknisi" class="min-h-screen bg-white md:flex md:items-center md:justify-center md:bg-surface-bg md:p-6">
    <div class="flex min-h-screen flex-col md:min-h-0 md:w-full md:max-w-4xl md:flex-row md:overflow-hidden md:rounded-3xl md:border md:border-line md:bg-white md:shadow-sm">
        {{-- Panel merek (mengikuti mockup Client-Login) --}}
        <section class="relative overflow-hidden bg-primary px-6 pt-7 pb-8 text-white md:flex md:w-[44%] md:flex-col md:justify-between md:p-10">
            <div aria-hidden="true" class="absolute -top-28 -right-36 size-90 rounded-full border-[1.5px] border-white/15"></div>
            <div aria-hidden="true" class="absolute -top-14 -right-22 size-60 rounded-full border-[1.5px] border-white/15"></div>

            <a href="{{ url('/') }}" class="relative inline-flex min-h-11 items-center gap-2.5">
                <span class="flex size-10 items-center justify-center rounded-xl bg-white text-primary">
                    <x-icon name="snowflake" class="size-6" stroke-width="2.2" />
                </span>
                <span class="text-xl font-extrabold tracking-tight">{{ $company }}</span>
            </a>

            <div class="relative mt-10 md:mt-0">
                <p class="text-sm font-semibold text-white/75">Panel internal</p>
                <h1 class="mt-1 text-[28px] leading-tight font-extrabold tracking-tight md:text-3xl">Masuk sebagai Admin atau Teknisi</h1>
                <p class="mt-2 max-w-xs text-[15px] leading-relaxed text-white/80">Kelola order, jadwal, dan laporan pekerjaan dari satu tempat.</p>
            </div>
        </section>

        {{-- Form --}}
        <main class="flex flex-1 flex-col px-6 pt-8 pb-7 md:justify-center md:p-10">
            <form method="POST" action="{{ route('login.store') }}" class="space-y-4" novalidate>
                @csrf

                <x-flash />

                <x-input
                    name="email"
                    type="email"
                    label="Email"
                    icon="mail"
                    autocomplete="username"
                    inputmode="email"
                    placeholder="nama@perusahaan.com"
                    required
                    autofocus
                />

                <div x-data="{ show: false }">
                    <x-input
                        name="password"
                        type="password"
                        label="Password"
                        icon="lock"
                        autocomplete="current-password"
                        x-bind:type="show ? 'text' : 'password'"
                        required
                    >
                        <x-slot:suffix>
                            <button
                                type="button"
                                x-on:click="show = !show"
                                x-bind:aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                aria-label="Tampilkan password"
                                class="flex size-11 items-center justify-center rounded-lg text-muted hover:text-ink focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25"
                            >
                                <x-icon name="eye" class="size-5" x-show="!show" />
                                <x-icon name="eye-off" class="size-5" x-show="show" x-cloak />
                            </button>
                        </x-slot:suffix>
                    </x-input>
                </div>

                <x-checkbox name="remember" label="Ingat saya di perangkat ini" />

                <x-button type="submit" size="lg" block icon-right="arrow-right">Masuk</x-button>
            </form>

            <p class="mt-6 text-center text-[13px] text-muted">
                Client? <a href="{{ url('/') }}" class="inline-flex min-h-11 items-center font-semibold text-primary hover:text-primary-hover">Masuk dengan Google</a>
            </p>
        </main>
    </div>
</x-layouts.base>
