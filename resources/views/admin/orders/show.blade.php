@use('App\Enums\OrderType')
@use('App\Services\Assignments\StaffCandidate')
@use('App\Services\OrderAssignmentService')
@use('App\Support\Format')
@use('Carbon\CarbonImmutable')

@php
    $day = CarbonImmutable::instance($order->scheduled_start)->startOfDay();
    $open = $day->setTimeFromTimeString((string) $settings->open_time);
    $close = $day->setTimeFromTimeString((string) $settings->close_time);
    $span = max(1, $open->diffInMinutes($close));
    // Posisi (%) jam pada mini timeline jam operasional.
    $pos = fn ($time) => max(0, min(100, $open->diffInMinutes(CarbonImmutable::instance($time), false) / $span * 100));
    $bar = fn ($from, $to) => 'left: '.round($pos($from), 2).'%; width: '.round(max(1.5, $pos($to) - $pos($from)), 2).'%';
    $buffer = $order->scheduled_end->diffInMinutes($order->blocked_until);
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.$order->lat.','.$order->lng;
    if ($canAssign) {
        $firstFree = fn (string $group) => $candidates[$group]->first(fn (StaffCandidate $candidate) => $candidate->isFree())?->staff->id;
        $selectedTechnician = old('technician_id', $order->technician_id ?? $firstFree('technicians'));
        $selectedHelper = old('helper_id', $order->helper_id ?? ($order->requires_helper ? $firstFree('helpers') : null)) ?? '';
    }
@endphp

<x-layouts.admin
    :title="$order->code"
    :subtitle="'Dibuat '.Format::date($order->created_at).' · '.$order->created_at->format('H:i')"
    :breadcrumbs="[['Order', route('admin.orders.index')], ['Detail', null]]"
>
    <x-slot:actions>
        @if ($order->order_type === OrderType::GARANSI)
            <x-badge variant="success">Garansi</x-badge>
        @endif
        <x-badge :status="$order->status" />
        @if (in_array(\App\Enums\OrderStatus::DITOLAK, $allowed, true))
            <x-button variant="danger" icon="circle-x" x-on:click="$dispatch('open-modal', 'order-reject')">Tolak order</x-button>
        @endif
        @if (in_array(\App\Enums\OrderStatus::DIBATALKAN, $allowed, true))
            <x-button variant="danger" icon="ban" x-on:click="$dispatch('open-modal', 'order-cancel')">Batalkan</x-button>
        @endif
    </x-slot:actions>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_480px]">
        <div class="flex min-w-0 flex-col gap-5">
            {{-- Client & lokasi --}}
            <section class="grid overflow-hidden rounded-card border border-line bg-white md:grid-cols-[minmax(0,1fr)_260px]">
                <div class="flex flex-col gap-3 p-5">
                    <h2 class="text-base font-extrabold text-ink">Client &amp; lokasi</h2>
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$order->client->name" :src="$order->client->avatar" />
                        <div class="min-w-0">
                            <p class="font-bold text-ink">{{ $order->client->name }}</p>
                            <p class="text-[13px] text-muted">
                                @if ($order->client->phone)
                                    <a href="{{ Format::whatsappUrl($order->client->phone) }}" target="_blank" rel="noopener" class="font-semibold text-primary tabular-nums">{{ $order->client->phone }}</a> ·
                                @endif
                                {{ $order->client->email }}
                            </p>
                        </div>
                    </div>
                    <p class="text-sm leading-relaxed text-ink-soft">{{ $order->address_snapshot }}</p>
                    <div class="flex flex-wrap gap-2">
                        <x-badge variant="primary" size="sm">{{ Format::km($order->distance_km) }} dari kantor</x-badge>
                        <x-badge size="sm">Transport {{ $order->transport_fee ? Format::rupiah($order->transport_fee) : 'gratis' }}</x-badge>
                    </div>
                </div>
                <div class="relative isolate min-h-44 bg-[#E8EDF5]" x-data="miniMap(@js(['lat' => (float) $order->lat, 'lng' => (float) $order->lng]))">
                    <div x-ref="map" class="absolute inset-0 z-0" aria-label="Peta lokasi client"></div>
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="absolute right-3 bottom-3 z-[500] inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-white px-3 text-[13px] font-bold text-primary shadow">
                        <x-icon name="external-link" class="size-4" /> Buka peta
                    </a>
                </div>
            </section>

            {{-- Unit & layanan --}}
            <section class="overflow-hidden rounded-card border border-line bg-white">
                <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5 pb-3">
                    <h2 class="text-base font-extrabold text-ink">Unit &amp; layanan ({{ $order->items->count() }})</h2>
                    <span class="text-[13px] text-muted">{{ Format::date($order->scheduled_start) }} · <b class="font-bold text-ink tabular-nums">{{ $order->scheduled_start->format('H:i') }}–{{ $order->scheduled_end->format('H:i') }}</b></span>
                </div>
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left">
                        <thead class="border-y border-line bg-[#F8FAFD] text-xs font-bold tracking-[0.4px] whitespace-nowrap text-muted uppercase">
                            <tr>
                                <th class="px-5 py-2.5">#</th>
                                <th class="px-3 py-2.5">Unit AC</th>
                                <th class="px-3 py-2.5">Layanan</th>
                                <th class="px-3 py-2.5">{{ $order->grand_total !== null ? 'Harga final' : 'Estimasi' }}</th>
                                <th class="px-5 py-2.5 text-right">Durasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEF2F8] text-[13px]">
                            @foreach ($order->items as $item)
                                <tr class="align-top">
                                    <td class="px-5 py-3 text-muted">{{ $loop->iteration }}</td>
                                    <td class="px-3 py-3 font-semibold text-ink">{{ $item->unit_label }}</td>
                                    <td class="px-3 py-3 text-ink-soft">
                                        {{ $item->service_name }}
                                        @if ($item->work_note)<p class="mt-1 text-xs text-muted">Catatan teknisi: {{ $item->work_note }}</p>@endif
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap tabular-nums">
                                        @if ($item->final_price !== null)
                                            <b class="text-ink">{{ Format::rupiah($item->final_price) }}</b>
                                            @if ($item->price_note)<p class="text-xs whitespace-normal text-muted">{{ $item->price_note }}</p>@endif
                                        @else
                                            {{ Format::rupiahRange($item->est_price_min, $item->est_price_max) }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap text-ink-soft">{{ Format::duration($item->est_minutes) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-end justify-between gap-3 border-t border-line bg-[#F8FAFD] px-5 py-4">
                    <p class="text-[13px] text-muted">
                        Durasi {{ Format::duration($order->total_est_minutes) }} + buffer {{ $buffer }} mnt · slot terkunci
                        <b class="font-bold text-ink tabular-nums">{{ $order->scheduled_start->format('H:i') }}–{{ $order->blocked_until->format('H:i') }}</b>
                        @if ($order->requires_helper) · <b class="font-bold text-warning-900">wajib helper</b>@endif
                    </p>
                    <div class="text-right">
                        <p class="text-xs text-muted">{{ $order->grand_total !== null ? 'Total tagihan' : 'Total estimasi (termasuk transport)' }}</p>
                        <p class="text-lg font-extrabold text-ink tabular-nums">{{ Format::orderTotal($order) }}</p>
                    </div>
                </div>
            </section>

            @if ($order->customer_note || $order->parentOrder)
                <section class="flex flex-col gap-3 rounded-card border border-line bg-white p-5">
                    @if ($order->parentOrder)
                        <div class="flex items-start gap-3 rounded-xl bg-success-50 px-4 py-3 text-sm text-success-700">
                            <x-icon name="shield-check" class="mt-0.5 size-5 shrink-0" />
                            <p>Klaim garansi dari order <a href="{{ route('admin.orders.show', $order->parentOrder) }}" class="font-bold underline">{{ $order->parentOrder->code }}</a>. Disarankan ditangani teknisi yang sama.</p>
                        </div>
                    @endif
                    @if ($order->customer_note)
                        <div class="flex items-start gap-3">
                            <x-icon name="message-square-text" class="mt-0.5 size-5 shrink-0 text-muted" />
                            <div>
                                <p class="text-[13px] font-semibold text-ink-soft">Catatan client</p>
                                <p class="mt-0.5 text-sm leading-relaxed text-ink">{{ $order->customer_note }}</p>
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            {{-- Riwayat status --}}
            <x-card title="Riwayat status">
                <ol class="flex flex-col gap-3">
                    @foreach ($order->statusLogs->reverse() as $log)
                        <li class="flex gap-3 text-[13px]">
                            <span class="w-28 shrink-0 text-muted tabular-nums">{{ Format::shortDate($log->created_at) }} · {{ $log->created_at->format('H:i') }}</span>
                            <span class="min-w-0">
                                <x-badge :status="$log->to_status" size="sm" />
                                <span class="text-muted">oleh {{ $log->changedBy?->name ?? 'sistem' }}</span>
                                @if ($log->note)<span class="block text-ink-soft">{{ $log->note }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </div>

        {{-- Tim --}}
        <div class="flex flex-col gap-5 xl:sticky xl:top-6">
            @if ($canAssign)
                <form
                    method="POST"
                    action="{{ route('admin.orders.assign', $order) }}"
                    x-data="{ technician: @js((string) $selectedTechnician), helper: @js((string) $selectedHelper), names: {} }"
                    class="flex flex-col gap-3 rounded-card border border-line bg-white p-5"
                >
                    @csrf
                    @method('PUT')
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-extrabold text-ink">{{ $order->technician_id ? 'Ganti tim' : 'Tugaskan tim' }}</h2>
                        <div class="flex gap-3 text-[11px] font-semibold text-muted">
                            <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm bg-line-strong"></span>Terisi</span>
                            <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm bg-primary"></span>Order ini</span>
                            <span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm bg-danger"></span>Bentrok</span>
                        </div>
                    </div>

                    @if ($order->scheduled_start->isPast())
                        <p class="rounded-xl bg-danger-50 px-3 py-2 text-xs text-danger-700">Jadwal order ini sudah lewat. Hubungi client untuk memastikan, atau tolak/batalkan order.</p>
                    @endif

                    @if ($order->technician_id && $order->status !== \App\Enums\OrderStatus::MENUNGGU)
                        <p class="rounded-xl bg-warning-50 px-3 py-2 text-xs text-warning-900">Mengganti teknisi mengembalikan status ke <b>Ditugaskan</b> dan teknisi baru perlu konfirmasi ulang. Mengganti helper saja tidak mengubah status.</p>
                    @endif

                    <p class="-mt-1 text-[11px] text-muted">Garis waktu jam operasional {{ $open->format('H:i') }}–{{ $close->format('H:i') }}, {{ Format::date($day) }}.</p>

                    @foreach (['technicians' => ['Teknisi', 'technician_id', 'technician'], 'helpers' => ['Helper', 'helper_id', 'helper']] as $group => [$heading, $field, $model])
                        <fieldset class="flex flex-col gap-2">
                            <legend class="mb-1 flex flex-wrap items-center gap-2 text-xs font-bold tracking-[0.4px] text-muted uppercase">
                                {{ $heading }}
                                @if ($group === 'helpers')
                                    @if ($order->requires_helper)
                                        <x-badge variant="warning" size="sm">Wajib — ada layanan wajib helper</x-badge>
                                    @else
                                        <span class="font-semibold normal-case">(opsional)</span>
                                    @endif
                                @endif
                            </legend>

                            @if ($group === 'helpers' && ! $order->requires_helper)
                                <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border-[1.5px] px-3 py-2" :class="helper === '' ? 'border-primary bg-[#F2F6FE]' : 'border-line'">
                                    <input type="radio" name="helper_id" value="" x-model="helper" class="size-4 accent-primary">
                                    <span class="text-sm font-semibold text-ink">Tanpa helper</span>
                                </label>
                            @endif

                            @forelse ($candidates[$group] as $candidate)
                                @php($id = (string) $candidate->staff->id)
                                <label
                                    @class([
                                        'grid min-h-14 grid-cols-[auto_auto_minmax(0,1fr)] items-center gap-x-3 gap-y-1.5 rounded-xl border-[1.5px] px-3 py-2 sm:grid-cols-[auto_auto_120px_minmax(0,1fr)_auto]',
                                        'cursor-pointer' => $candidate->isFree(),
                                        'cursor-not-allowed bg-[#F8FAFD] opacity-70' => ! $candidate->isFree(),
                                    ])
                                    :class="{{ $model }} === @js($id) ? 'border-primary bg-[#F2F6FE]' : 'border-line'"
                                >
                                    <input type="radio" name="{{ $field }}" value="{{ $id }}" x-model="{{ $model }}" @disabled(! $candidate->isFree()) class="size-4 accent-primary">
                                    <x-avatar :name="$candidate->name()" :src="$candidate->staff instanceof \App\Models\Technician ? $candidate->staff->photoUrl() : null" size="sm" />
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-bold text-ink">{{ $candidate->name() }}</span>
                                        <span class="block truncate text-[11px] text-muted">
                                            @if ($candidate->status === StaffCandidate::LEAVE)
                                                Cuti hari ini
                                            @elseif ($candidate->status === StaffCandidate::CONFLICT)
                                                {{ OrderAssignmentService::conflictNote($candidate) }}
                                            @elseif ($candidate->staff instanceof \App\Models\Technician)
                                                @if ($candidate->staff->review_count)★ {{ number_format((float) $candidate->staff->avg_rating, 1, ',', '') }} · @endif{{ $candidate->dayOrders->count() }} order hari ini
                                            @else
                                                {{ $candidate->dayOrders->count() }} order hari ini
                                            @endif
                                        </span>
                                    </span>
                                    <span class="relative col-span-3 h-3 rounded-full bg-[#EEF2F8] sm:col-span-1" aria-hidden="true">
                                        @unless ($candidate->status === StaffCandidate::LEAVE)
                                            @foreach ($candidate->dayOrders as $other)
                                                <span class="absolute inset-y-0 rounded-full {{ $candidate->conflicts->contains($other) ? 'bg-danger/70' : 'bg-line-strong' }}" style="{{ $bar($other->scheduled_start, $other->blocked_until) }}" title="{{ $other->code }}"></span>
                                            @endforeach
                                            <span class="absolute inset-y-0 rounded-full {{ $candidate->isFree() ? 'bg-primary' : 'bg-primary/40' }} ring-2 ring-white" style="{{ $bar($order->scheduled_start, $order->blocked_until) }}"></span>
                                        @endunless
                                    </span>
                                    <span class="col-start-3 row-start-1 justify-self-end sm:col-start-auto sm:row-start-auto">
                                        <span class="inline-flex flex-wrap justify-end gap-1">
                                            @if ($candidate->current)<x-badge variant="navy" size="sm">Saat ini</x-badge>@endif
                                            @if ($candidate->suggested)<x-badge variant="primary" size="sm">Teknisi order asal</x-badge>@endif
                                            <x-badge :variant="match ($candidate->status) { StaffCandidate::FREE => 'success', StaffCandidate::CONFLICT => 'danger', default => 'neutral' }" size="sm">{{ $candidate->label() }}</x-badge>
                                        </span>
                                    </span>
                                </label>
                            @empty
                                <p class="rounded-xl bg-surface-bg px-4 py-3 text-sm text-muted">Belum ada {{ strtolower($heading) }} aktif. <a href="{{ route($group === 'technicians' ? 'admin.technicians.create' : 'admin.helpers.create') }}" class="font-bold text-primary">Tambah {{ strtolower($heading) }}</a></p>
                            @endforelse
                            <x-field-error :name="$field" />
                        </fieldset>
                    @endforeach

                    <div class="mt-2 flex flex-col-reverse gap-2 border-t border-line pt-4 sm:flex-row sm:justify-end">
                        <x-button type="submit" icon="user-check" ::disabled="!technician">
                            {{ $order->technician_id ? 'Simpan tim' : 'Tugaskan' }}
                        </x-button>
                    </div>
                </form>
            @endif

            @if (! $canAssign && $order->technician)
                <x-card title="Tim">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <x-avatar :name="$order->technician->user->name" :src="$order->technician->photoUrl()" size="sm" />
                            <div>
                                <p class="text-sm font-bold text-ink">{{ $order->technician->user->name }}</p>
                                <p class="text-xs text-muted">Teknisi · {{ $order->technician->user->phone }}</p>
                            </div>
                        </div>
                        @if ($order->helper)
                            <div class="flex items-center gap-3">
                                <x-avatar :name="$order->helper->name" size="sm" />
                                <div>
                                    <p class="text-sm font-bold text-ink">{{ $order->helper->name }}</p>
                                    <p class="text-xs text-muted">Helper{{ $order->helper->phone ? ' · '.$order->helper->phone : '' }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-card>
            @endif

            @if ($order->status_reason)
                <x-card title="Alasan">
                    <p class="text-sm text-ink-soft">{{ $order->status_reason }}</p>
                </x-card>
            @endif
        </div>
    </div>

    @foreach ([
        'reject' => [\App\Enums\OrderStatus::DITOLAK, 'Tolak order', 'Order baru ditolak (mis. di luar jangkauan atau jadwal penuh). Alasan ditampilkan ke client.', 'admin.orders.reject', 'Tolak order'],
        'cancel' => [\App\Enums\OrderStatus::DIBATALKAN, 'Batalkan order', 'Order dibatalkan dan jadwal teknisi/helper dilepas. Alasan ditampilkan ke client.', 'admin.orders.cancel', 'Batalkan order'],
    ] as $action => [$target, $modalTitle, $description, $route, $submit])
        @if (in_array($target, $allowed, true))
            <x-modal :name="'order-'.$action" :title="$modalTitle" :show="$errors->reason->any() && old('_modal') === $action">
                <form id="form-{{ $action }}" method="POST" action="{{ route($route, $order) }}" class="flex flex-col gap-3" novalidate>
                    @csrf
                    <input type="hidden" name="_modal" value="{{ $action }}">
                    <p class="text-sm text-ink-soft">{{ $description }}</p>
                    <x-textarea name="reason" :id="'reason-'.$action" label="Alasan" rows="3" :error="old('_modal') === $action ? ($errors->reason->first('reason') ?: null) : null" maxlength="500" required autofocus />
                </form>
                <x-slot:footer>
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'order-{{ $action }}')">Kembali</x-button>
                    <x-button type="submit" :form="'form-'.$action" variant="danger-solid">{{ $submit }}</x-button>
                </x-slot:footer>
            </x-modal>
        @endif
    @endforeach
</x-layouts.admin>
