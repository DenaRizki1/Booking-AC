{{--
    Pemilih tanggal & jam mulai. Butuh scope Alpine berisi:
    dates, date, time, day (getter), slot (getter), pickDate(d), unitCount, duration.
--}}
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
