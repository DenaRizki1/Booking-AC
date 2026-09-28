@use('App\Support\Format')

@php
    $config = [
        'items' => $selected,
        'minutes' => $items->pluck('est_minutes', 'id'),
        'date' => old('date'),
        'time' => old('time'),
        'slotsUrl' => route('client.orders.warranty.slots', $order),
    ];
@endphp

<x-layouts.client title="Klaim garansi" :subtitle="'Order '.$order->code" :back="route('client.orders.show', $order)">
    <form
        method="POST"
        action="{{ route('client.orders.warranty.store', $order) }}"
        enctype="multipart/form-data"
        x-data="{
            ...@js($config),
            dates: [],
            loading: false,
            get unitCount() { return this.items.length },
            get duration() {
                const total = this.items.reduce((sum, id) => sum + (this.minutes[id] ?? 0), 0);
                return total < 60 ? `±${total} menit` : `±${String(Math.round(total / 6) / 10).replace('.', ',')} jam`;
            },
            get day() { return this.dates.find(d => d.date === this.date) ?? null },
            get slot() { return this.day?.slots.find(s => s.time === this.time) ?? null },
            pickDate(d) { if (!d.working || d.full) return; this.date = d.date; if (!this.slot?.available) this.time = null },
            async load() {
                this.loading = true;
                const params = new URLSearchParams();
                this.items.forEach(id => params.append('items[]', id));
                const { data } = await window.axios.get(`${this.slotsUrl}?${params}`);
                this.dates = data.dates;
                if (!this.day || this.day.full || !this.day.working) {
                    this.date = this.dates.find(d => d.slots.some(s => s.available))?.date ?? null;
                }
                if (!this.slot?.available) this.time = null;
                this.loading = false;
            },
            init() { this.load(); this.$watch('items', () => this.load()) },
        }"
        novalidate
    >
        @csrf
        @csrf
        <input type="hidden" name="date" :value="date">
        <input type="hidden" name="time" :value="time">


        <div class="grid items-start gap-6 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                @if ($errors->hasAny(['items', 'time', 'date']))
                    <x-flash type="error" :message="$errors->first('items') ?: $errors->first('time') ?: $errors->first('date')" />
                @endif

                <div class="rounded-card border border-line bg-white p-5">
                    {{-- Unit --}}
                    <fieldset>
                        <legend class="mb-3 text-lg font-extrabold text-ink">Unit yang bermasalah</legend>
                        <div class="space-y-2">
                            @foreach ($items as $item)
                                <label class="flex min-h-16 cursor-pointer items-center gap-3 rounded-2xl border-[1.5px] bg-white p-4" :class="items.includes({{ $item->id }}) ? 'border-primary bg-blue-50' : 'border-line'">
                                    <input type="checkbox" name="items[]" value="{{ $item->id }}" x-model.number="items" class="size-5 shrink-0 rounded accent-primary">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-extrabold tracking-wide text-primary uppercase">{{ $item->service_name }}</span>
                                        <span class="block font-bold text-ink">{{ $item->unit_label }}</span>
                                        <span class="block text-[13px] text-muted">Garansi s/d {{ Format::date($item->warranty_until) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-field-error name="items" />
                    </fieldset>

                </div>
                <div class="rounded-card border border-line bg-white p-5">
                    {{-- Keluhan --}}
                    <div class="space-y-4">
                        <x-textarea name="complaint" label="Keluhan" rows="3" placeholder="Mis. AC kembali menetes air setelah dicuci." required />
                        <div>
                            <x-label for="photo">Foto keluhan (opsional)</x-label>
                            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" class="block min-h-12 w-full rounded-xl border-[1.5px] border-dashed border-line bg-white px-3 py-3 text-sm text-ink-soft file:mr-3 file:min-h-10 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:font-bold file:text-primary">
                            <p class="mt-1.5 text-[13px] text-muted">JPG/PNG/WEBP, maks. 5 MB.</p>
                            <x-field-error name="photo" />
                        </div>
                    </div>

                </div>
                <div class="rounded-card border border-line bg-white p-5">
                    {{-- Jadwal --}}
                    <div x-show="items.length" x-cloak :class="loading && 'opacity-60'">
                        @include('client.orders.partials.booking-picker')
                    </div>
                    <p x-show="!items.length" class="text-sm text-muted">Pilih unit terlebih dahulu untuk melihat jadwal yang tersedia.</p>

                </div>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-24">
                <div class="rounded-card border border-line bg-white p-5">
                    <p class="flex items-center gap-2 font-extrabold text-ink"><x-icon name="shield-check" class="size-5 text-success" /> Ringkasan klaim</p>
                    <dl class="mt-4 space-y-2 text-[15px]">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Unit</dt><dd class="font-bold text-ink" x-text="`${items.length} unit · ${duration}`"></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Jadwal</dt><dd class="text-right font-bold text-ink" x-text="day && time ? `${day.long} · ${time}` : 'Belum dipilih'"></dd></div>
                        <div class="flex justify-between gap-3 border-t border-line pt-3"><dt class="font-extrabold text-ink">Biaya</dt><dd class="font-extrabold text-success-700">Rp0</dd></div>
                    </dl>
                    <p class="mt-3 text-[13px] text-muted">Pengecekan & perbaikan dalam garansi tanpa biaya layanan & transport. Bila kerusakan di luar garansi, teknisi akan menginformasikan biayanya.</p>
                    <x-button type="submit" size="lg" block icon="shield-check" class="mt-5" ::disabled="!items.length || !slot?.available">Kirim klaim garansi</x-button>
                </div>
            </aside>
        </div>
    </form>
</x-layouts.client>
