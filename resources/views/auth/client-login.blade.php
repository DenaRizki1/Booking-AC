@php
    $company = \App\Models\CompanySettings::companyName();
    $services = \App\Models\Service::query()->where('is_active', true)->orderBy('sort_order')->pluck('name');
    $demoClients = app()->environment('local')
        ? \App\Models\User::query()->where('role', 'client')->where('is_active', true)->orderBy('id')->limit(5)->get(['id', 'name'])
        : collect();
@endphp

<x-layouts.base title="Masuk" class="min-h-screen bg-white">
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Panel merek (mockup Client-Login) --}}
        <section class="relative flex flex-col overflow-hidden bg-primary px-6 py-8 text-white sm:px-10 lg:px-14 lg:py-12">
            <div aria-hidden="true" class="absolute -top-32 -right-40 size-[460px] rounded-full border-[1.5px] border-white/15"></div>
            <div aria-hidden="true" class="absolute -top-16 -right-24 size-[300px] rounded-full border-[1.5px] border-white/15"></div>

            <div class="relative flex items-center gap-2.5">
                <span class="flex size-11 items-center justify-center rounded-xl bg-white text-primary">
                    <x-icon name="snowflake" class="size-6" stroke-width="2.2" />
                </span>
                <span class="text-xl font-extrabold tracking-tight">{{ $company }}</span>
            </div>

            <div class="relative mt-10 max-w-lg lg:mt-16">
                <h1 class="text-[32px] leading-[1.1] font-extrabold tracking-tight lg:text-5xl">Servis AC tanpa ribet, teknisi datang tepat jadwal.</h1>
                <p class="mt-4 text-base leading-relaxed text-white/85 lg:text-lg">Pilih unit, layanan, dan jam. Estimasi harga langsung terlihat, bayar setelah pekerjaan selesai.</p>
            </div>

            <div class="relative flex flex-1 items-center justify-center py-8" aria-hidden="true">
                <svg width="340" height="294" class="max-w-full" viewBox="0 0 300 260" fill="none">
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

            <ul class="relative flex flex-wrap gap-2">
                @foreach ($services as $service)
                    <li class="inline-flex h-9 items-center rounded-full bg-white/15 px-4 text-sm font-semibold">{{ $service }}</li>
                @endforeach
            </ul>
        </section>

        {{-- Panel masuk --}}
        <main class="flex items-center justify-center px-6 py-10 sm:px-10">
            <div class="w-full max-w-md">
                <h2 class="text-3xl font-extrabold tracking-tight text-navy">Masuk</h2>
                <p class="mt-2 text-[15px] text-muted">Masuk dengan akun Google untuk membuat dan memantau order servis AC Anda.</p>

                <x-flash class="mt-5" />

                <div x-data="firebaseLogin(@js(['firebase' => [
                    'apiKey' => config('services.firebase.api_key'),
                    'authDomain' => config('services.firebase.auth_domain'),
                    'projectId' => config('services.firebase.project_id'),
                    'appId' => config('services.firebase.app_id'),
                ], 'endpoint' => route('auth.firebase')]))">
                    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-5 rounded-xl border border-danger/20 bg-danger-50 px-4 py-3 text-sm font-semibold text-danger-700"></p>

                    <button type="button" @click="signIn()" :disabled="loading" :aria-busy="loading" class="mt-6 flex min-h-[54px] w-full items-center justify-center gap-3 rounded-[14px] border-[1.5px] border-[#D5DEEE] bg-white text-base font-bold text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25 disabled:cursor-wait disabled:opacity-70">
                        <span aria-hidden="true" class="inline-flex size-[26px] items-center justify-center rounded-full bg-blue-50 text-sm font-extrabold text-primary">G</span>
                        <span x-text="loading ? 'Membuka Google…' : 'Masuk dengan Google'">Masuk dengan Google</span>
                    </button>
                </div>
                <p class="mt-3 text-center text-xs leading-normal text-muted">Dengan masuk, Anda menyetujui Syarat Layanan dan Kebijakan Privasi {{ $company }}.</p>

                <ul class="mt-8 space-y-3 border-t border-line pt-6 text-sm text-ink-soft">
                    <li class="flex gap-2"><x-icon name="calendar" class="size-5 shrink-0 text-primary" /> Pilih jadwal sesuai ketersediaan teknisi.</li>
                    <li class="flex gap-2"><x-icon name="credit-card" class="size-5 shrink-0 text-primary" /> Bayar setelah selesai — tunai atau transfer.</li>
                    <li class="flex gap-2"><x-icon name="shield-check" class="size-5 shrink-0 text-primary" /> Garansi pekerjaan & laporan foto sebelum/sesudah.</li>
                </ul>

                <div class="mt-6 flex justify-center">
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
                        <a href="{{ route('dev.login-as', $demo) }}" class="inline-flex min-h-11 items-center rounded-lg border border-warning/40 bg-white px-3 text-[13px] font-semibold text-warning-900 hover:bg-warning-50">{{ $demo->name }}</a>
                    @endforeach
                </div>
            </div>
        @endif
            </div>
        </main>
    </div>
</x-layouts.base>
