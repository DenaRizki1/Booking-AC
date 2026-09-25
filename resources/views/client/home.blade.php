@use('App\Enums\OrderStatus')
@use('App\Support\Format')

@php
    $to = fn (string $name, mixed $parameters = []) => Route::has($name) ? route($name, $parameters) : '#';
    $hour = now()->hour;
    $greeting = match (true) {
        $hour >= 4 && $hour < 11 => 'Selamat pagi,',
        $hour >= 11 && $hour < 15 => 'Selamat siang,',
        $hour >= 15 && $hour < 18 => 'Selamat sore,',
        default => 'Selamat malam,',
    };
@endphp

<x-layouts.client title="Beranda">
    <x-slot:header>
        <header class="border-b border-line bg-white px-5 pt-6 pb-4">
            <div class="flex items-center gap-3">
                <x-avatar :name="$client->name" :src="$client->avatar" size="lg" />
                <div class="min-w-0 flex-1">
                    <p class="text-[15px] text-muted">{{ $greeting }}</p>
                    <p class="truncate text-xl font-extrabold tracking-tight text-ink">{{ $client->name }}</p>
                </div>
                <a href="#" class="flex size-12 items-center justify-center rounded-2xl border-[1.5px] border-line text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                    <x-icon name="bell" class="size-6" />
                    <span class="sr-only">Notifikasi</span>
                </a>
            </div>

            <a href="{{ $to('client.addresses.index') }}" class="mt-4 flex min-h-12 items-center gap-3 rounded-2xl bg-surface-bg px-4 text-[15px] hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                <x-icon name="map-pin" class="size-5 text-primary" />
                @if ($address)
                    <span class="min-w-0 flex-1 truncate"><strong class="font-bold text-ink">{{ $address->label }}</strong> <span class="text-ink-soft">· {{ $address->address }}</span></span>
                @else
                    <span class="flex-1 font-semibold text-primary">Tambah alamat untuk mulai order</span>
                @endif
                <x-icon name="chevron-down" class="size-5 text-muted" />
            </a>
        </header>
    </x-slot:header>

    <div class="space-y-6">
        {{-- Order aktif --}}
        @if ($activeOrder)
            @php($step = $activeOrder->status->progressIndex())
            <a href="{{ $to('client.orders.show', $activeOrder) }}" class="relative block overflow-hidden rounded-3xl bg-primary p-5 text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/40">
                <div aria-hidden="true" class="absolute -top-24 -right-20 size-60 rounded-full border border-white/15"></div>
                <div class="relative flex items-center justify-between text-[13px] font-bold tracking-wide text-white/80 uppercase">
                    <span>Order aktif</span>
                    <span>{{ $activeOrder->code }}</span>
                </div>
                <p class="relative mt-3 flex items-center gap-2 text-[22px] leading-tight font-extrabold tracking-tight">
                    <span aria-hidden="true" class="size-3 shrink-0 rounded-full bg-[#7CF0B5] ring-4 ring-white/20"></span>
                    {{ $activeOrder->status->clientMessage() }}
                </p>
                <div class="relative mt-4 grid grid-cols-6 gap-1.5" role="img" aria-label="Tahap {{ $step + 1 }} dari {{ count(OrderStatus::progressSteps()) }}">
                    @foreach (OrderStatus::progressSteps() as $index => $s)
                        <span @class(['h-1.5 rounded-full', 'bg-white' => $index <= $step, 'bg-white/30' => $index > $step])></span>
                    @endforeach
                </div>

                @if ($activeOrder->technician)
                    <div class="relative mt-4 flex items-center gap-3 rounded-2xl bg-[#1650C4] p-3">
                        <x-avatar :name="$activeOrder->technician->user->name" class="!bg-white !text-primary" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold">{{ $activeOrder->technician->user->name }}</p>
                            <p class="truncate text-[13px] text-white/80">
                                Teknisi
                                @if ($activeOrder->technician->review_count > 0) · ★ {{ number_format((float) $activeOrder->technician->avg_rating, 1, ',', '') }} @endif
                                @if ($activeOrder->helper) · + Helper {{ Str::before($activeOrder->helper->name, ' ') }} @endif
                            </p>
                        </div>
                    </div>
                @endif

                <div class="relative mt-4 flex items-center justify-between text-[15px]">
                    <span class="flex items-center gap-2"><x-icon name="clock" class="size-5" /> {{ Format::schedule($activeOrder->scheduled_start, $activeOrder->scheduled_end) }}</span>
                    <span class="font-bold">{{ $activeOrder->items_count }} unit</span>
                </div>
            </a>
            @if ($otherActiveCount > 0)
                <a href="{{ $to('client.orders.index') }}" class="-mt-3 block text-center text-sm font-semibold text-primary">+ {{ $otherActiveCount }} order aktif lainnya</a>
            @endif
        @endif

        {{-- Pilih layanan --}}
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-extrabold text-ink">Pilih layanan</h2>
                <a href="{{ $to('client.orders.create') }}" class="inline-flex min-h-11 items-center text-[15px] font-semibold text-primary">Semua</a>
            </div>

            @if ($services->isEmpty())
                <x-card><x-empty-state icon="wrench" title="Layanan belum tersedia" /></x-card>
            @else
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($services as $service)
                        <a href="{{ $to('client.orders.create', ['service' => $service->slug]) }}" class="rounded-2xl border-[1.5px] border-line bg-white p-4 hover:border-primary focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                            <span class="flex size-12 items-center justify-center rounded-xl bg-blue-50 text-primary">
                                <x-icon :name="$service->icon ?: 'wrench'" class="size-6" />
                            </span>
                            <span class="mt-3 block text-[17px] font-bold text-ink">{{ $service->name }}</span>
                            <span class="mt-1 block text-[13px] text-muted">Mulai {{ Format::rupiahShort((int) $service->price_from) }} · {{ Format::duration((int) $service->minutes_from) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Garansi aktif --}}
        @if ($warranty)
            <div class="flex items-center gap-3 rounded-2xl border-[1.5px] border-line bg-white p-4">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-success-50 text-success">
                    <x-icon name="shield-check" class="size-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-ink">Garansi aktif</p>
                    <p class="truncate text-[13px] text-muted">{{ $warranty->order->code }} · s/d {{ $warranty->warranty_until->translatedFormat('j M Y') }}</p>
                </div>
                <a href="{{ $to('client.orders.show', $warranty->order) }}" class="inline-flex min-h-11 items-center px-2 text-[15px] font-bold text-primary">Klaim</a>
            </div>
        @endif

        <x-button :href="$to('client.orders.create')" size="lg" block icon="plus" class="!min-h-14 rounded-2xl text-lg">Buat Order</x-button>
    </div>
</x-layouts.client>
