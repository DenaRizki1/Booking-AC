@use('App\Enums\OrderStatus')
@use('App\Support\Format')

@php
    $hour = now()->hour;
    $greeting = match (true) {
        $hour >= 4 && $hour < 11 => 'Selamat pagi,',
        $hour >= 11 && $hour < 15 => 'Selamat siang,',
        $hour >= 15 && $hour < 18 => 'Selamat sore,',
        default => 'Selamat malam,',
    };
@endphp

<x-layouts.client title="Beranda" :heading="false">
    {{-- Sapaan --}}
    <section class="mb-6 flex flex-wrap items-center gap-4 rounded-3xl border border-line bg-white p-5 lg:p-6">
        <x-avatar :name="$client->name" :src="$client->avatar" size="lg" />
        <div class="min-w-0 flex-1">
            <p class="text-[15px] text-muted">{{ $greeting }}</p>
            <h1 class="truncate text-2xl font-extrabold tracking-tight text-navy">{{ $client->name }}</h1>
        </div>
        <a href="{{ route('client.addresses.index') }}" class="flex min-h-12 w-full items-center gap-3 rounded-2xl bg-surface-bg px-4 text-[15px] hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25 md:w-auto md:max-w-md">
            <x-icon name="map-pin" class="size-5 shrink-0 text-primary" />
            @if ($address)
                <span class="min-w-0 flex-1 truncate"><strong class="font-bold text-ink">{{ $address->label }}</strong> <span class="text-ink-soft">· {{ $address->address }}</span></span>
            @else
                <span class="flex-1 font-semibold text-primary">Tambah alamat untuk mulai order</span>
            @endif
            <x-icon name="chevron-right" class="size-5 shrink-0 text-muted" />
        </a>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Order aktif --}}
            @if ($activeOrder)
                @php($step = $activeOrder->status->progressIndex())
                <a href="{{ route('client.orders.show', $activeOrder) }}" class="relative block overflow-hidden rounded-3xl bg-primary p-6 text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/40">
                    <div aria-hidden="true" class="absolute -top-24 -right-16 size-72 rounded-full border border-white/15"></div>
                    <div class="relative flex items-center justify-between text-[13px] font-bold tracking-wide text-white/80 uppercase">
                        <span>Order aktif</span>
                        <span>{{ $activeOrder->code }}</span>
                    </div>
                    <p class="relative mt-3 flex items-center gap-2 text-2xl leading-tight font-extrabold tracking-tight">
                        <span aria-hidden="true" class="size-3 shrink-0 rounded-full bg-[#7CF0B5] ring-4 ring-white/20"></span>
                        {{ $activeOrder->status->clientMessage() }}
                    </p>
                    <div class="relative mt-4 grid grid-cols-6 gap-1.5" role="img" aria-label="Tahap {{ $step + 1 }} dari {{ count(OrderStatus::progressSteps()) }}">
                        @foreach (OrderStatus::progressSteps() as $index => $s)
                            <span @class(['h-1.5 rounded-full', 'bg-white' => $index <= $step, 'bg-white/30' => $index > $step])></span>
                        @endforeach
                    </div>

                    <div class="relative mt-5 flex flex-wrap items-center gap-4">
                        @if ($activeOrder->technician)
                            <div class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl bg-[#1650C4] p-3">
                                <x-avatar :name="$activeOrder->technician->user->name" class="!bg-white !text-primary" />
                                <div class="min-w-0">
                                    <p class="truncate font-bold">{{ $activeOrder->technician->user->name }}</p>
                                    <p class="truncate text-[13px] text-white/80">
                                        Teknisi
                                        @if ($activeOrder->technician->review_count > 0) · ★ {{ number_format((float) $activeOrder->technician->avg_rating, 1, ',', '') }} @endif
                                        @if ($activeOrder->helper) · + Helper {{ Str::before($activeOrder->helper->name, ' ') }} @endif
                                    </p>
                                </div>
                            </div>
                        @endif
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-[15px]">
                            <span class="flex items-center gap-2"><x-icon name="clock" class="size-5" /> {{ Format::schedule($activeOrder->scheduled_start, $activeOrder->scheduled_end) }}</span>
                            <span class="font-bold">{{ $activeOrder->items_count }} unit</span>
                        </div>
                    </div>
                </a>
                @if ($otherActiveCount > 0)
                    <a href="{{ route('client.orders.index') }}" class="-mt-3 block text-sm font-semibold text-primary">+ {{ $otherActiveCount }} order aktif lainnya</a>
                @endif
            @else
                <section class="flex flex-wrap items-center gap-4 rounded-3xl bg-primary p-6 text-white">
                    <div class="min-w-0 flex-1">
                        <p class="text-2xl font-extrabold tracking-tight">AC kurang dingin?</p>
                        <p class="mt-1 text-white/80">Pilih unit, layanan, dan jam. Estimasi harga langsung terlihat, bayar setelah pekerjaan selesai.</p>
                    </div>
                    <x-button :href="route('client.orders.create')" variant="secondary" size="lg" icon="plus">Buat Order</x-button>
                </section>
            @endif

            {{-- Pilih layanan --}}
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-xl font-extrabold text-ink">Pilih layanan</h2>
                    <a href="{{ route('client.orders.create') }}" class="inline-flex min-h-11 items-center text-[15px] font-semibold text-primary">Semua</a>
                </div>

                @if ($services->isEmpty())
                    <x-card><x-empty-state icon="wrench" title="Layanan belum tersedia" /></x-card>
                @else
                    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                        @foreach ($services as $service)
                            <a href="{{ route('client.orders.create', ['service' => $service->slug]) }}" class="group rounded-2xl border-[1.5px] border-line bg-white p-5 transition hover:-translate-y-0.5 hover:border-primary hover:shadow-md focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                                <span class="flex size-12 items-center justify-center rounded-xl bg-blue-50 text-primary group-hover:bg-primary group-hover:text-white">
                                    <x-icon :name="$service->icon ?: 'wrench'" class="size-6" />
                                </span>
                                <span class="mt-4 block text-[17px] font-bold text-ink">{{ $service->name }}</span>
                                <span class="mt-1 block text-[13px] text-muted">Mulai {{ Format::rupiahShort((int) $service->price_from) }} · {{ Format::duration((int) $service->minutes_from) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- Samping --}}
        <aside class="space-y-4">
            <x-button :href="route('client.orders.create')" size="lg" block icon="plus" class="!min-h-14 rounded-2xl text-lg">Buat Order</x-button>

            @if ($warranty)
                <div class="flex items-center gap-3 rounded-2xl border-[1.5px] border-line bg-white p-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-success-50 text-success">
                        <x-icon name="shield-check" class="size-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-ink">Garansi aktif</p>
                        <p class="truncate text-[13px] text-muted">{{ $warranty->order->code }} · s/d {{ $warranty->warranty_until->translatedFormat('j M Y') }}</p>
                    </div>
                    <a href="{{ route('client.orders.warranty', $warranty->order) }}" class="inline-flex min-h-11 items-center px-2 text-[15px] font-bold text-primary">Klaim</a>
                </div>
            @endif

            <x-card title="Cara kerja">
                <ol class="space-y-4 text-[15px]">
                    @foreach ([
                        ['clipboard-list', 'Pilih unit & layanan', 'Estimasi harga & durasi langsung terlihat.'],
                        ['calendar', 'Pilih jadwal', 'Hanya jam dengan teknisi tersedia yang bisa dipilih.'],
                        ['wrench', 'Teknisi datang', 'Pantau status & laporan foto dari halaman order.'],
                        ['credit-card', 'Bayar setelah selesai', 'Tunai atau transfer, plus garansi 7 hari.'],
                    ] as [$icon, $label, $hint])
                        <li class="flex gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary"><x-icon :name="$icon" class="size-5" /></span>
                            <span><span class="block font-bold text-ink">{{ $label }}</span><span class="block text-[13px] text-muted">{{ $hint }}</span></span>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </aside>
    </div>
</x-layouts.client>
