@use('App\Support\Format')

@php
    $oldDate = old('date');
    $initial = collect($dates)->firstWhere('date', $oldDate) ?? collect($dates)->first(fn ($d) => collect($d['slots'])->contains('available', true));
    $config = [
        'dates' => $dates,
        'date' => $initial['date'] ?? null,
        'time' => old('time'),
        'unitCount' => $unitCount,
        'duration' => Format::duration($estimate->totalMinutes),
    ];
@endphp

<x-layouts.client title="Buat Order" :nav="false">
    <x-slot:header>
        <header class="sticky top-0 z-20 border-b border-line bg-white px-4 pt-3 pb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('client.orders.create') }}" class="flex size-12 items-center justify-center rounded-xl border-[1.5px] border-line text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                    <x-icon name="chevron-left" class="size-6" /><span class="sr-only">Kembali ke unit AC</span>
                </a>
                <h1 class="text-xl font-extrabold tracking-tight text-ink">Buat Order</h1>
            </div>
            <div class="mt-4"><x-order-stepper :step="2" /></div>
        </header>
    </x-slot:header>

    <form
        method="POST"
        action="{{ route('client.orders.store') }}"
        x-data="{
            ...@js($config),
            get day() { return this.dates.find(d => d.date === this.date) ?? null },
            get slot() { return this.day?.slots.find(s => s.time === this.time) ?? null },
            pickDate(d) { if (!d.working || d.full) return; this.date = d.date; if (!this.slot?.available) this.time = null },
        }"
        class="-mb-6"
        novalidate
    >
        @csrf
        <input type="hidden" name="date" :value="date">
        <input type="hidden" name="time" :value="time">

        @if ($errors->hasAny(['time', 'date', 'customer_address_id']))
            <x-flash type="error" :message="$errors->first('time') ?: $errors->first('date') ?: $errors->first('customer_address_id')" class="mb-4" />
        @endif

        {{-- Tanggal --}}
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-extrabold text-ink">Pilih tanggal</h2>
            <p class="text-[15px] text-ink-soft" x-text="day?.month"></p>
        </div>
        <div class="-mx-4 flex snap-x gap-2.5 overflow-x-auto px-4 pb-2" role="listbox" aria-label="Tanggal">
            <template x-for="d in dates" :key="d.date">
                <button
                    type="button"
                    role="option"
                    x-on:click="pickDate(d)"
                    :aria-selected="d.date === date"
                    :aria-disabled="!d.working || d.full"
                    :aria-label="d.long + (!d.working ? ' — libur' : d.full ? ' — penuh' : '')"
                    class="flex min-h-[84px] w-[72px] shrink-0 snap-start flex-col items-center justify-center rounded-2xl border-[1.5px] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25"
                    :class="d.date === date ? 'border-primary bg-primary text-white' : (!d.working || d.full) ? 'cursor-not-allowed border-transparent text-muted/70' : 'border-line bg-white text-ink'"
                >
                    <span class="text-[13px] font-semibold" x-text="d.day"></span>
                    <span class="text-[26px] leading-none font-extrabold" x-text="d.number"></span>
                    <span class="mt-1 text-[10px] font-bold tracking-wide uppercase" x-show="!d.working || d.full" x-text="d.working ? 'Penuh' : 'Libur'"></span>
                </button>
            </template>
        </div>

        {{-- Jam --}}
        <h2 class="mt-5 mb-3 text-lg font-extrabold text-ink">Pilih jam mulai</h2>
        <div class="grid grid-cols-4 gap-2" role="listbox" aria-label="Jam mulai">
            <template x-for="s in day?.slots ?? []" :key="s.time">
                <button
                    type="button"
                    role="option"
                    x-on:click="s.available && (time = s.time)"
                    :aria-selected="s.time === time"
                    :aria-disabled="!s.available"
                    class="min-h-12 rounded-xl border-[1.5px] text-[15px] font-bold tabular-nums focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25"
                    :class="s.time === time ? 'border-primary bg-primary text-white' : s.available ? 'border-line bg-white text-ink' : 'cursor-not-allowed border-transparent text-muted/60 line-through'"
                    x-text="s.time"
                ></button>
            </template>
        </div>
        <p x-show="day && !day.slots.some(s => s.available)" x-cloak class="mt-2 text-sm font-semibold text-danger">Semua jam pada tanggal ini sudah penuh. Pilih tanggal lain.</p>

        <div class="mt-3 flex gap-3 rounded-2xl bg-blue-50 p-4 text-[15px] text-ink-soft">
            <x-icon name="clock" class="size-5 shrink-0 text-primary" />
            <p>
                <template x-if="slot"><span>Perkiraan selesai <strong class="text-ink" x-text="slot.end"></strong> (<span x-text="`${unitCount} unit ${duration}`"></span>).</span></template>
                Hanya jam dengan teknisi tersedia yang bisa dipilih.
            </p>
        </div>

        {{-- Catatan --}}
        <div class="mt-6">
            <label for="f-customer-note" class="mb-2 block text-lg font-extrabold text-ink">Catatan keluhan <span class="text-[15px] font-medium text-muted">(opsional)</span></label>
            <x-textarea name="customer_note" rows="3" placeholder="Mis. AC kamar depan kurang dingin dan ada tetesan air." />
        </div>

        {{-- Ringkasan --}}
        <section class="mt-6 rounded-3xl border border-line bg-white p-5">
            <h2 class="text-lg font-extrabold text-ink">Ringkasan</h2>
            <dl class="mt-3 space-y-2 text-[15px]">
                <div class="flex justify-between gap-3"><dt class="text-muted">Jadwal</dt><dd class="text-right font-bold text-ink" x-text="day && time ? `${day.long} · ${time}` : 'Belum dipilih'"></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">Alamat</dt><dd class="text-right font-bold text-ink">{{ $address->label }} · {{ Format::km($address->distance_km) }}</dd></div>
            </dl>
            <div class="my-3 border-t border-line"></div>
            <ul class="space-y-3">
                @foreach ($lines as $line)
                    <li class="flex justify-between gap-3">
                        <div>
                            <p class="font-bold text-ink">{{ $line['service'] }}</p>
                            <p class="text-[13px] text-muted">{{ $line['unit'] }}</p>
                        </div>
                        <p class="text-right font-bold whitespace-nowrap text-ink">{{ $line['price'] }}</p>
                    </li>
                @endforeach
                <li class="flex justify-between gap-3 text-[15px]">
                    <span class="text-muted">{{ $transportNote }}</span>
                    <span class="font-bold text-ink">{{ $transportFee === 0 ? 'Gratis' : Format::rupiah($transportFee) }}</span>
                </li>
            </ul>
            <div class="my-3 border-t border-line"></div>
            <div class="flex items-end justify-between gap-3">
                <span class="font-extrabold text-ink">Total estimasi</span>
                <span class="text-xl font-extrabold tracking-tight text-navy tabular-nums">{{ Format::rupiahRange($estimate->priceMin + $transportFee, $estimate->priceMax + $transportFee) }}</span>
            </div>
            @if ($estimate->requiresHelper)
                <p class="mt-3 text-[13px] text-muted">Pekerjaan ini didampingi 1 helper.</p>
            @endif
        </section>

        <ul class="mt-4 space-y-2 text-sm text-ink-soft">
            <li class="flex gap-2"><x-icon name="credit-card" class="size-5 shrink-0 text-success" /> Bayar setelah selesai — tunai atau transfer. Harga final ditentukan teknisi setelah pengecekan.</li>
            <li class="flex gap-2"><x-icon name="shield-check" class="size-5 shrink-0 text-success" /> Garansi {{ $settings->warranty_days }} hari. Pembatalan gratis hingga {{ $settings->cancel_limit_hours }} jam sebelum jadwal.</li>
        </ul>

        <div class="sticky bottom-0 -mx-4 mt-6 border-t border-line bg-white px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <x-button type="submit" size="lg" block ::disabled="!slot?.available">Konfirmasi Order</x-button>
        </div>
    </form>
</x-layouts.client>
