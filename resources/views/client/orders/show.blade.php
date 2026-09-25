@use('App\Enums\OrderStatus')
@use('App\Enums\OrderType')
@use('App\Support\Format')

@php
    $index = $order->status->progressIndex();
    $ended = $index === null;
    $steps = [
        ['Dipesan', $order->created_at, true],
        ['Ditugaskan', $order->assigned_at, $index >= 1],
        ['Perjalanan', $order->departed_at, $index >= 3],
        ['Dikerjakan', $order->started_at, $index >= 4],
        ['Selesai', $order->completed_at, $index >= 5],
    ];
    $technician = $order->technician;
    $cancellableStatus = in_array($order->status, [OrderStatus::MENUNGGU, OrderStatus::DITUGASKAN, OrderStatus::DIKONFIRMASI], true);
    $officeWa = Format::whatsappUrl($settings->phone);
@endphp

<x-layouts.client :title="$order->code" :nav="false">
    <x-slot:header>
        <header class="sticky top-0 z-20 flex min-h-[72px] items-center gap-3 border-b border-line bg-white px-4 py-3">
            <a href="{{ route('client.orders.index') }}" class="flex size-12 shrink-0 items-center justify-center rounded-xl border-[1.5px] border-line text-ink hover:bg-surface-bg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                <x-icon name="chevron-left" class="size-6" /><span class="sr-only">Kembali ke daftar order</span>
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-xl font-extrabold tracking-tight text-ink">{{ $order->code }}</h1>
                <p class="truncate text-[13px] text-muted">{{ Format::schedule($order->scheduled_start, $order->scheduled_end, true) }}</p>
            </div>
            <x-badge :status="$order->status" />
        </header>
    </x-slot:header>

    <div class="space-y-4">
        @if ($order->order_type === OrderType::GARANSI)
            <x-flash type="info" :dismissible="false" :message="'Order klaim garansi dari '.($order->parentOrder?->code ?? 'order sebelumnya').' — tanpa biaya layanan & transport.'" />
        @endif

        {{-- Timeline & teknisi --}}
        <x-card>
            @if ($ended)
                <div class="flex items-start gap-3 rounded-2xl bg-danger-50 p-4 text-danger-700">
                    <x-icon name="circle-x" class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-bold">{{ $order->status->clientMessage() }}</p>
                        @if ($order->status_reason)
                            <p class="mt-0.5 text-sm">Alasan: {{ $order->status_reason }}</p>
                        @endif
                        @if ($order->cancelled_at)
                            <p class="mt-0.5 text-[13px] opacity-80">{{ Format::date($order->cancelled_at) }} · {{ $order->cancelled_at->format('H:i') }}</p>
                        @endif
                    </div>
                </div>
            @else
                <ol class="flex items-start" aria-label="Progres order">
                    @foreach ($steps as $i => [$label, $at, $done])
                        @php($current = ! $done && ($i === 0 || $steps[$i - 1][2]))
                        <li class="relative flex flex-1 flex-col items-center text-center" @if ($current) aria-current="step" @endif>
                            @unless ($loop->last)
                                <span aria-hidden="true" @class(['absolute top-3.5 left-1/2 h-0.5 w-full', 'bg-success' => $steps[$i + 1][2], 'bg-line' => ! $steps[$i + 1][2]])></span>
                            @endunless
                            <span @class([
                                'relative flex size-7 items-center justify-center rounded-full',
                                'bg-success text-white' => $done,
                                'border-2 border-primary bg-white' => $current,
                                'bg-line' => ! $done && ! $current,
                            ])>
                                @if ($done) <x-icon name="check" class="size-4" stroke-width="3" /> @endif
                            </span>
                            <span @class(['mt-1.5 text-[11px] font-semibold', 'text-success-700' => $done, 'text-primary' => $current, 'text-muted' => ! $done && ! $current])>{{ $label }}</span>
                            @if ($done && $at)
                                <span class="text-[10px] text-muted">{{ $at->format('H:i') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
                <p class="mt-4 text-center text-[15px] font-bold text-ink">{{ $order->status->clientMessage() }}</p>
            @endif

            @if ($technician)
                <div class="mt-4 flex items-center gap-3 border-t border-line pt-4">
                    <x-avatar :name="$technician->user->name" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-ink">{{ $technician->user->name }}</p>
                        <p class="truncate text-[13px] text-muted">Teknisi{{ $order->helper ? ' · Helper: '.$order->helper->name : '' }}</p>
                    </div>
                    @if ($technician->review_count > 0)
                        <span class="text-[15px] font-bold text-ink">★ {{ number_format((float) $technician->avg_rating, 1, ',', '') }}</span>
                    @endif
                    @if ($order->status->isActive() && ($wa = Format::whatsappUrl($technician->user->phone)))
                        <a href="{{ $wa }}" target="_blank" rel="noopener" class="flex size-11 items-center justify-center rounded-xl border-[1.5px] border-line text-primary hover:bg-blue-50" title="Chat teknisi">
                            <x-icon name="message-square" class="size-5" /><span class="sr-only">Chat teknisi via WhatsApp</span>
                        </a>
                    @endif
                </div>
            @elseif (! $ended)
                <p class="mt-4 border-t border-line pt-4 text-sm text-muted">Admin sedang memilih teknisi untuk jadwal Anda.</p>
            @endif
        </x-card>

        {{-- Unit & estimasi (sebelum laporan) --}}
        @unless (in_array($order->status, [OrderStatus::SELESAI, OrderStatus::LUNAS], true))
            <x-card title="Unit AC ({{ $order->items->count() }})">
                <ul class="space-y-3">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[13px] font-extrabold tracking-wide text-primary uppercase">Unit {{ $loop->iteration }} · {{ $item->service_name }}</p>
                                <p class="font-bold text-ink">{{ $item->unit_label }}</p>
                            </div>
                            <p class="shrink-0 text-right text-sm font-bold text-ink">{{ Format::rupiahRange($item->est_price_min, $item->est_price_max) }}</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-4 space-y-2 border-t border-line pt-4 text-[15px]">
                    <div class="flex justify-between"><dt class="text-muted">Transport</dt><dd class="font-bold text-ink">{{ $order->transport_fee === 0 ? 'Gratis' : Format::rupiah($order->transport_fee) }}</dd></div>
                    <div class="flex justify-between"><dt class="font-extrabold text-ink">Total estimasi</dt><dd class="font-extrabold text-navy">{{ Format::rupiahRange($order->est_price_min + $order->transport_fee, $order->est_price_max + $order->transport_fee) }}</dd></div>
                </dl>
                <p class="mt-3 text-[13px] text-muted">Harga final ditentukan teknisi setelah pengecekan. Bayar setelah pekerjaan selesai.</p>
            </x-card>
        @endunless

        {{-- Laporan & pembayaran (setelah SELESAI) --}}
        @if (in_array($order->status, [OrderStatus::SELESAI, OrderStatus::LUNAS], true))
            <x-card title="Laporan pekerjaan" x-data="{ photo: null }">
                @if ($order->completed_at)
                    <x-slot:actions><span class="text-[13px] text-muted">Dikirim {{ $order->completed_at->format('H:i') }}</span></x-slot:actions>
                @endif

                <div class="space-y-3">
                    @foreach ($order->items as $item)
                        @php($over = $item->final_price !== null && $item->final_price > $item->est_price_max)
                        <article class="rounded-2xl bg-surface-bg p-4">
                            <div class="flex justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[13px] font-extrabold tracking-wide text-primary uppercase">Unit {{ $loop->iteration }} · {{ $item->service_name }}</p>
                                    <p class="font-bold text-ink">{{ $item->unit_label }}</p>
                                </div>
                                <p class="shrink-0 text-[17px] font-extrabold text-ink tabular-nums">{{ $item->final_price !== null ? Format::rupiah($item->final_price) : '–' }}</p>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                @foreach ([\App\Enums\PhotoKind::SEBELUM, \App\Enums\PhotoKind::SESUDAH] as $kind)
                                    @php($photos = $item->photos->where('kind', $kind))
                                    <div @class(['relative aspect-[4/3] overflow-hidden rounded-xl', 'bg-[#DCE4F0]' => $kind === \App\Enums\PhotoKind::SEBELUM, 'bg-blue-100' => $kind !== \App\Enums\PhotoKind::SEBELUM])>
                                        <span @class(['absolute top-2 left-2 z-10 rounded-md px-2 py-0.5 text-xs font-bold', 'bg-white text-ink' => $kind === \App\Enums\PhotoKind::SEBELUM, 'bg-primary text-white' => $kind !== \App\Enums\PhotoKind::SEBELUM])>{{ $kind->label() }}</span>
                                        @if ($photos->isNotEmpty())
                                            @php($first = $photos->first())
                                            <button type="button" class="size-full" x-on:click="photo = @js(Storage::disk('public')->url($first->path))">
                                                <img src="{{ Storage::disk('public')->url($first->path) }}" alt="Foto {{ strtolower($kind->label()) }} unit {{ $loop->parent->iteration }}" loading="lazy" class="size-full object-cover">
                                                @if ($photos->count() > 1)
                                                    <span class="absolute right-2 bottom-2 rounded-md bg-navy/70 px-1.5 text-xs font-bold text-white">+{{ $photos->count() - 1 }}</span>
                                                @endif
                                            </button>
                                        @else
                                            <span class="flex size-full items-center justify-center text-xs text-muted">Belum ada foto</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @if ($item->work_note)
                                <p class="mt-3 text-[15px] text-ink-soft">{{ $item->work_note }}</p>
                            @endif
                            @if ($over || $item->price_note)
                                <div class="mt-3 flex gap-2 rounded-xl bg-warning-50 p-3 text-sm text-warning-900">
                                    <x-icon name="circle-alert" class="mt-0.5 size-4 shrink-0" />
                                    <p>{{ $over ? 'Di atas estimasi: ' : '' }}{{ $item->price_note }}@if ($over) (+{{ Format::rupiah($item->final_price - $item->est_price_max) }})@endif</p>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if ($order->technician_note)
                    <p class="mt-3 text-[15px] text-ink-soft"><span class="font-semibold text-ink">Catatan teknisi:</span> {{ $order->technician_note }}</p>
                @endif

                {{-- Lightbox --}}
                <div x-show="photo" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-navy/85 p-4" x-on:click="photo = null" x-on:keydown.escape.window="photo = null" role="dialog" aria-modal="true" aria-label="Foto laporan">
                    <img :src="photo" alt="" class="max-h-[85vh] max-w-full rounded-xl object-contain">
                    <button type="button" class="absolute top-4 right-4 flex size-12 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" x-on:click="photo = null">
                        <x-icon name="x" class="size-6" /><span class="sr-only">Tutup foto</span>
                    </button>
                </div>
            </x-card>

            @php($serviceTotal = (int) ($order->final_price ?? $order->items->sum('final_price')))
            <x-card title="Pembayaran">
                <x-slot:actions>
                    @if ($order->status === OrderStatus::LUNAS)
                        <x-badge variant="success">Lunas</x-badge>
                    @elseif ((int) $order->grand_total === 0)
                        <x-badge variant="primary">Tanpa biaya</x-badge>
                    @else
                        <x-badge variant="warning">Belum dibayar</x-badge>
                    @endif
                </x-slot:actions>

                <dl class="space-y-2 text-[15px]">
                    <div class="flex justify-between"><dt class="text-muted">Layanan ({{ $order->items->count() }} unit)</dt><dd class="font-bold text-ink">{{ Format::rupiah($serviceTotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Transport</dt><dd class="font-bold text-ink">{{ $order->transport_fee === 0 ? 'Gratis' : Format::rupiah($order->transport_fee) }}</dd></div>
                </dl>
                <div class="mt-3 flex items-end justify-between border-t border-line pt-3">
                    <span class="text-lg font-extrabold text-ink">Total</span>
                    <span class="text-2xl font-extrabold tracking-tight text-navy tabular-nums">{{ Format::rupiah((int) ($order->grand_total ?? $serviceTotal + $order->transport_fee)) }}</span>
                </div>

                @if ($order->status === OrderStatus::LUNAS)
                    <p class="mt-3 text-sm text-success-700">Dibayar {{ $order->payment_method?->label() ? 'secara '.strtolower($order->payment_method->label()) : '' }} pada {{ Format::date($order->paid_at ?? $order->updated_at) }}. Terima kasih!</p>
                @elseif ((int) $order->grand_total === 0)
                    <p class="mt-3 text-sm text-muted">Order garansi — tidak ada tagihan.</p>
                @else
                    <p class="mt-3 text-sm text-muted">
                        Bayar tunai ke teknisi, atau transfer ke
                        @if ($settings->bank_name && $settings->bank_account_no)
                            <strong class="text-ink">{{ $settings->bank_name }} {{ $settings->bank_account_no }}</strong> a.n. <strong class="text-ink">{{ $settings->bank_account_name ?? $settings->company_name }}</strong>.
                        @else
                            rekening {{ $settings->company_name }} (hubungi admin).
                        @endif
                        Admin akan menandai lunas setelah pembayaran diterima.
                    </p>
                @endif
            </x-card>
        @endif

        {{-- Lokasi & catatan --}}
        <x-card title="Lokasi">
            <p class="text-[15px] text-ink">{{ $order->address_snapshot }}</p>
            <p class="mt-1 text-[13px] text-muted">{{ Format::km($order->distance_km) }} dari kantor</p>
            @if ($order->customer_note)
                <div class="mt-3 rounded-xl bg-surface-bg p-3">
                    <p class="text-[13px] font-semibold text-muted">Catatan keluhan</p>
                    <p class="text-[15px] text-ink">{{ $order->customer_note }}</p>
                </div>
            @endif
        </x-card>

        {{-- Batalkan / hubungi admin --}}
        @if ($canCancel)
            <x-card>
                <p class="text-sm text-muted">Pembatalan gratis paling lambat {{ $settings->cancel_limit_hours }} jam sebelum jadwal (s/d {{ Format::shortDate($order->scheduled_start->copy()->subHours($settings->cancel_limit_hours)) }}, {{ $order->scheduled_start->copy()->subHours($settings->cancel_limit_hours)->format('H:i') }}).</p>
                <x-button variant="danger" block icon="circle-x" class="mt-3" x-on:click="$dispatch('open-modal', 'cancel-order')">Batalkan order</x-button>
            </x-card>

            <x-modal name="cancel-order" title="Batalkan order?" :show="$errors->has('reason')">
                <form id="cancel-order-form" method="POST" action="{{ route('client.orders.cancel', $order) }}" class="space-y-3">
                    @csrf
                    <p class="text-[15px] text-ink-soft">Order <strong>{{ $order->code }}</strong> akan dibatalkan dan jadwal teknisi dilepas.</p>
                    <x-textarea name="reason" label="Alasan pembatalan" rows="3" placeholder="Mis. ada acara mendadak" required autofocus />
                </form>
                <x-slot:footer>
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'cancel-order')">Kembali</x-button>
                    <x-button type="submit" form="cancel-order-form" variant="danger-solid">Ya, batalkan</x-button>
                </x-slot:footer>
            </x-modal>
        @elseif ($cancellableStatus || $order->status->isActive())
            <x-card>
                <p class="text-sm text-muted">
                    @if ($cancellableStatus)
                        Batas pembatalan mandiri ({{ $settings->cancel_limit_hours }} jam sebelum jadwal) sudah lewat. Untuk membatalkan atau mengubah jadwal, hubungi admin.
                    @else
                        Ada kendala? Hubungi admin {{ $settings->company_name }}.
                    @endif
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    @if ($officeWa)
                        <x-button :href="$officeWa" target="_blank" rel="noopener" variant="secondary" icon="message-circle">WhatsApp</x-button>
                    @endif
                    @if ($settings->phone)
                        <x-button :href="'tel:'.$settings->phone" variant="secondary" icon="phone">Telepon</x-button>
                    @endif
                </div>
            </x-card>
        @endif

        {{-- Riwayat status --}}
        <details class="rounded-card border border-line bg-white">
            <summary class="flex min-h-12 cursor-pointer items-center justify-between px-5 text-[15px] font-extrabold text-ink">Riwayat status <x-icon name="chevron-down" class="size-5 text-muted" /></summary>
            <ol class="space-y-3 border-t border-line px-5 py-4">
                @foreach ($order->statusLogs as $log)
                    <li class="flex gap-3 text-sm">
                        <span class="w-24 shrink-0 text-muted">{{ $log->created_at->format('j M, H:i') }}</span>
                        <span class="text-ink">{{ $log->to_status->label() }}@if ($log->note) <span class="text-muted">— {{ $log->note }}</span>@endif</span>
                    </li>
                @endforeach
            </ol>
        </details>
    </div>
</x-layouts.client>
