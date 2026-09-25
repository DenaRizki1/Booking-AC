@php
    $company = \App\Models\CompanySettings::companyName();
    $services = \App\Models\Service::query()->where('is_active', true)->orderBy('sort_order')->pluck('name');
    $demoClients = app()->environment('local')
        ? \App\Models\User::query()->where('role', 'client')->where('is_active', true)->orderBy('id')->limit(5)->get(['id', 'name'])
        : collect();
@endphp

<x-layouts.base title="Masuk" class="min-h-screen bg-surface-bg">
    <div class="mx-auto flex min-h-screen max-w-[480px] flex-col bg-white md:my-6 md:min-h-[calc(100vh-3rem)] md:overflow-hidden md:rounded-3xl md:border md:border-line">
        {{-- Hero (mockup Client-Login) --}}
        <section class="relative flex min-h-[420px] flex-col overflow-hidden bg-primary px-6 pt-7 text-white">
            <div aria-hidden="true" class="absolute -top-30 -right-38 size-90 rounded-full border-[1.5px] border-white/15"></div>
            <div aria-hidden="true" class="absolute -top-15 -right-22 size-60 rounded-full border-[1.5px] border-white/15"></div>

            <div class="relative flex items-center gap-2.5">
                <span class="flex size-10 items-center justify-center rounded-xl bg-white text-primary">
                    <x-icon name="snowflake" class="size-6" stroke-width="2.2" />
                </span>
                <span class="text-xl font-extrabold tracking-tight">{{ $company }}</span>
            </div>

            <div class="relative flex flex-1 items-center justify-center py-6" aria-hidden="true">
                <svg width="280" height="240" viewBox="0 0 300 260" fill="none">
                    <rect x="30" y="40" width="240" height="84" rx="18" fill="#FFFFFF"/>
                    <rect x="30" y="98" width="240" height="26" fill="#E8F0FE"/>
                    <rect x="30" y="40" width="240" height="84" rx="18" stroke="#00084D" stroke-opacity="0.08" stroke-width="2"/>
                    <line x1="54" y1="108" x2="246" y2="108" stroke="#1D63ED" stroke-opacity="0.35" stroke-width="3" stroke-linecap="round"/>
                    <line x1="54" y1="116" x2="246" y2="116" stroke="#1D63ED" stroke-opacity="0.2" stroke-width="3" stroke-linecap="round"/>
                    <rect x="206" y="58" width="44" height="20" rx="6" fill="#00084D"/>
                    <text x="228" y="72" text-anchor="middle" font-family="Plus Jakarta Sans" font-size="12" font-weight="700" fill="#7FB0FF">18°</text>
                    <circle cx="58" cy="68" r="5" fill="#27C281"/>
                    <path d="M80 150c10 10 -10 22 0 34s-10 22 0 34" stroke="#FFFFFF" stroke-opacity="0.75" stroke-width="3" stroke-linecap="round"/>
                    <path d="M150 150c10 10 -10 22 0 34s-10 22 0 34" stroke="#FFFFFF" stroke-opacity="0.9" stroke-width="3" stroke-linecap="round"/>
                    <path d="M220 150c10 10 -10 22 0 34s-10 22 0 34" stroke="#FFFFFF" stroke-opacity="0.75" stroke-width="3" stroke-linecap="round"/>
                    <path d="M115 160c8 8 -8 18 0 28" stroke="#FFFFFF" stroke-opacity="0.45" stroke-width="3" stroke-linecap="round"/>
                    <path d="M185 160c8 8 -8 18 0 28" stroke="#FFFFFF" stroke-opacity="0.45" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </div>

            <ul class="relative flex flex-wrap gap-2 pb-7">
                @foreach ($services as $service)
                    <li class="inline-flex h-8 items-center rounded-full bg-white/15 px-3 text-[13px] font-semibold">{{ $service }}</li>
                @endforeach
            </ul>
        </section>

        <main class="flex flex-1 flex-col gap-3 px-6 pt-8 pb-7">
            <h1 class="text-[30px] leading-[1.15] font-extrabold tracking-tight text-navy">Servis AC tanpa ribet, teknisi datang tepat jadwal.</h1>
            <p class="text-[15px] leading-relaxed text-muted">Pilih unit, layanan, dan jam. Estimasi harga langsung terlihat, bayar setelah pekerjaan selesai.</p>

            <x-flash class="mt-2" />

            <div class="flex-1"></div>

            <a href="{{ route('auth.google.redirect') }}" class="flex min-h-[54px] items-center justify-center gap-3 rounded-[14px] border-[1.5px] border-[#D5DEEE] bg-white text-base font-bold text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                <span aria-hidden="true" class="inline-flex size-[26px] items-center justify-center rounded-full bg-blue-50 text-sm font-extrabold text-primary">G</span>
                Masuk dengan Google
            </a>
            <p class="mt-1 text-center text-xs leading-normal text-muted">Dengan masuk, Anda menyetujui Syarat Layanan dan Kebijakan Privasi {{ $company }}.</p>

            <div class="flex justify-center pt-2">
                <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-semibold text-primary hover:text-primary-hover">
                    <x-icon name="user-cog" class="size-4" />
                    Masuk sebagai Admin / Teknisi
                </a>
            </div>

            @if ($demoClients->isNotEmpty())
                <div class="mt-2 rounded-xl border border-dashed border-warning bg-warning-50 p-3">
                    <p class="text-xs font-bold text-warning-900">Mode lokal — masuk sebagai client demo (tanpa Google):</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($demoClients as $demo)
                            <form method="POST" action="{{ route('dev.login-as', $demo) }}">
                                @csrf
                                <button type="submit" class="min-h-11 rounded-lg border border-warning/40 bg-white px-3 text-[13px] font-semibold text-warning-900 hover:bg-warning-50">{{ $demo->name }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </main>
    </div>
</x-layouts.base>
