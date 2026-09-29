@use('App\Enums\PhotoKind')
@use('App\Support\Format')

@php
    $unitNumber = $index + 1;
    $finalTotal = (int) $order->items->sum('final_price');
    $allComplete = $complete->every(fn ($done) => $done);
    $price = old('final_price', $item->final_price !== null ? number_format($item->final_price, 0, ',', '.') : '');
@endphp

<x-layouts.teknisi title="Laporan Pekerjaan" :back="route('teknisi.tasks.show', $order)">
    <x-slot:headerActions>
        <x-badge :status="$order->status" size="sm" />
    </x-slot:headerActions>

    <p class="-mt-1 text-[13px] text-muted">{{ $order->code }} · {{ $order->client->name }}</p>

    {{-- Tab unit --}}
    <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Unit">
        @foreach ($order->items as $unit)
            @php($current = $loop->index === $index)
            <a
                href="{{ route('teknisi.reports.edit', ['order' => $order, 'unit' => $loop->iteration]) }}"
                @if ($current) aria-current="page" @endif
                @class([
                    'flex min-h-14 min-w-32 shrink-0 flex-col justify-center rounded-xl border-[1.5px] px-3.5 py-2',
                    'border-primary bg-primary text-white' => $current,
                    'border-line bg-white text-ink' => ! $current,
                ])
            >
                <span class="flex items-center gap-1.5 text-sm font-extrabold">
                    @if ($complete[$loop->index])<x-icon name="circle-check" class="size-4" stroke-width="2.4" /><span class="sr-only">Lengkap:</span>@endif
                    Unit {{ $loop->iteration }}
                </span>
                <span @class(['truncate text-[11px]', 'text-white/80' => $current, 'text-muted' => ! $current])>{{ Str::limit($unit->unit_label, 22) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="flex flex-col gap-5 rounded-card border border-line bg-white p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-extrabold tracking-wide text-primary uppercase">{{ $item->service_name }}</p>
                <p class="text-base font-bold text-ink">{{ $item->unit_label }}</p>
            </div>
            <div class="text-right">
                <p class="text-[11px] text-muted">Estimasi</p>
                <p class="text-sm font-bold text-ink tabular-nums">{{ Format::rupiahRange($item->est_price_min, $item->est_price_max) }}</p>
            </div>
        </div>

        {{-- Foto sebelum / sesudah --}}
        @foreach ([PhotoKind::SEBELUM, PhotoKind::SESUDAH] as $kind)
            @php($photos = $item->photos->where('kind', $kind)->values())
            <div>
                <div class="mb-2 flex items-center justify-between text-[13px]">
                    <span class="font-bold text-ink">Foto {{ strtolower($kind->label()) }}</span>
                    @if ($kind === PhotoKind::SESUDAH && $photos->isEmpty())
                        <span class="font-semibold text-danger">Wajib min. 1</span>
                    @else
                        <span class="text-muted">{{ $photos->count() }} foto</span>
                    @endif
                </div>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    @foreach ($photos as $photo)
                        <div class="relative aspect-square overflow-hidden rounded-xl bg-surface-bg">
                            <img src="{{ Storage::disk('public')->url($photo->path) }}" alt="Foto {{ strtolower($kind->label()) }} unit {{ $unitNumber }}" loading="lazy" class="size-full object-cover">
                            <form method="POST" action="{{ route('teknisi.reports.photos.destroy', [$order, $photo]) }}" class="absolute top-1 right-1">
                                @csrf @method('DELETE')
                                <button type="submit" class="flex size-9 items-center justify-center rounded-lg bg-navy/70 text-white hover:bg-danger" onclick="return confirm('Hapus foto ini?')">
                                    <x-icon name="trash-2" class="size-4" /><span class="sr-only">Hapus foto</span>
                                </button>
                            </form>
                        </div>
                    @endforeach

                    @if ($photos->count() < \App\Services\OrderReportService::MAX_PHOTOS_PER_KIND)
                        <form method="POST" action="{{ route('teknisi.reports.photos.store', [$order, $item]) }}" enctype="multipart/form-data" x-data="{ uploading: false }" x-on:submit="uploading = true" @class(['aspect-square' => $photos->isNotEmpty(), 'col-span-3 sm:col-span-4' => $photos->isEmpty()])>
                            @csrf
                            <input type="hidden" name="kind" value="{{ $kind->value }}">
                            <label @class([
                                'flex size-full cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-[1.5px] border-dashed border-blue-200 bg-[#F2F6FE] text-primary hover:bg-blue-50',
                                'min-h-24' => $photos->isEmpty(),
                            ]) :class="uploading && 'animate-pulse pointer-events-none'">
                                <x-icon name="camera" class="size-6" />
                                <span class="text-xs font-bold" x-text="uploading ? 'Mengunggah…' : @js($photos->isEmpty() ? 'Ambil foto / pilih dari galeri' : 'Tambah')"></span>
                                <input type="file" name="photos[]" accept="image/*" capture="environment" multiple class="sr-only" x-on:change="if ($event.target.files.length) $el.form.requestSubmit()">
                            </label>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
        <x-field-error name="photos" />
        <x-field-error name="photos.0" />

        {{-- Catatan & harga --}}
        <form
            method="POST"
            action="{{ route('teknisi.reports.items.update', [$order, $item]) }}"
            x-data="{
                price: @js((string) $price),
                max: {{ $item->est_price_max }},
                get amount() { return Number(String(this.price).replace(/\D/g, '')) || 0; },
                format() { const d = String(this.price).replace(/\D/g, ''); this.price = d === '' ? '' : new Intl.NumberFormat('id-ID').format(Number(d)); },
            }"
            class="flex flex-col gap-4 border-t border-[#EEF2F8] pt-4"
            novalidate
        >
            @csrf
            @method('PUT')
            <x-textarea name="work_note" label="Catatan (opsional)" :value="$item->work_note" rows="3" placeholder="Mis. saluran pembuangan tersumbat, sudah dibersihkan" maxlength="1000" />

            <div>
                <x-input name="final_price" label="Harga final unit ini" inputmode="numeric" icon="banknote" x-model="price" x-on:blur="format()" :value="$price" placeholder="0" required />
                <div x-show="amount > max" x-cloak class="mt-2 flex items-start gap-2 rounded-xl bg-warning-50 px-3 py-2 text-[13px] text-warning-900">
                    <x-icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
                    <span>Di atas estimasi (maks. {{ Format::rupiah($item->est_price_max) }}). Keterangan wajib diisi dan akan terlihat oleh client.</span>
                </div>
            </div>
            <div x-show="amount > max || @js((bool) old('price_note', $item->price_note))" x-cloak>
                <x-input name="price_note" label="Keterangan tambahan harga" :value="$item->price_note" placeholder="Mis. ganti kapasitor 35µF (+Rp50.000)" maxlength="500" />
            </div>

            <x-button type="submit" variant="secondary" size="lg" block>Simpan Unit {{ $unitNumber }}</x-button>
        </form>
    </section>

    <x-slot:footer>
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs text-muted">Total harga final · {{ $order->items->count() }} unit</p>
                <p class="text-lg font-extrabold text-ink tabular-nums">{{ Format::rupiah($finalTotal) }}</p>
            </div>
            <span class="text-xs text-muted tabular-nums">+ transport {{ Format::rupiah((int) $order->transport_fee) }}</span>
        </div>
        <form method="POST" action="{{ route('teknisi.reports.submit', $order) }}" class="mt-2" x-data x-on:submit="if (! confirm('Kirim laporan? Status order menjadi Selesai dan tidak bisa diubah lagi.')) $event.preventDefault()">
            @csrf
            <x-button type="submit" size="lg" icon="send" block :disabled="! $allComplete">Kirim Laporan &amp; Selesai</x-button>
            @unless ($allComplete)
                <p class="mt-1.5 text-center text-xs text-muted">Lengkapi harga final & min. 1 foto sesudah di setiap unit.</p>
            @endunless
        </form>
    </x-slot:footer>
</x-layouts.teknisi>
