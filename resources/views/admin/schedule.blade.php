@use('App\Enums\OrderStatus')
@use('App\Support\Format')
@use('Carbon\CarbonImmutable')

@php
    $open = $day->setTimeFromTimeString((string) $settings->open_time);
    $close = $day->setTimeFromTimeString((string) $settings->close_time);
    $span = max(60, $open->diffInMinutes($close));
    $hours = range((int) $open->format('G'), (int) ceil($open->diffInMinutes($close) / 60) + (int) $open->format('G') - 1);
    $pct = fn ($time) => max(0, min(100, $open->diffInMinutes(CarbonImmutable::instance($time), false) / $span * 100));
    $now = CarbonImmutable::now();
    $nowPct = $day->isSameDay($now) && $now->between($open, $close) ? $pct($now) : null;

    // Warna blok per status (mockup Admin-Jadwal).
    $styles = [
        OrderStatus::SELESAI->value => 'bg-success-50 text-success-700',
        OrderStatus::LUNAS->value => 'bg-success-50 text-success-700',
        OrderStatus::DIKERJAKAN->value => 'bg-navy text-white',
        OrderStatus::DALAM_PERJALANAN->value => 'bg-primary text-white',
        OrderStatus::DIKONFIRMASI->value => 'bg-blue-100 text-blue-800',
        OrderStatus::DITUGASKAN->value => 'border-[1.5px] border-dashed border-primary bg-white text-blue-800',
    ];
    $firstName = fn (?string $name) => $name ? collect(explode(' ', $name))->take(1)->implode(' ').(str_contains($name, ' ') ? ' '.mb_substr(explode(' ', $name)[1], 0, 1).'.' : '') : '';
    $lanes = ['Teknisi' => $technicians, 'Helper' => $helpers];
    $grid = 'background-image: linear-gradient(90deg, #EEF2F8 1px, transparent 1px); background-size: calc(100% / '.count($hours).') 100%;';
@endphp

<x-layouts.admin title="Jadwal Teknisi & Helper" :subtitle="'Jam operasional '.$open->format('H:i').'–'.$close->format('H:i').' · buffer perjalanan '.$settings->travel_buffer_minutes.' menit'" :breadcrumbs="[['Jadwal', null]]">
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.schedule', ['tanggal' => $day->subDay()->toDateString()]) }}" class="flex size-11 items-center justify-center rounded-xl border border-line bg-white text-ink hover:bg-surface-bg">
                <x-icon name="chevron-left" class="size-5" /><span class="sr-only">Hari sebelumnya</span>
            </a>
            <form method="GET" action="{{ route('admin.schedule') }}" x-data>
                <label for="f-tanggal" class="sr-only">Tanggal</label>
                <input id="f-tanggal" type="date" name="tanggal" value="{{ $day->toDateString() }}" x-on:change="$el.form.submit()" class="min-h-11 rounded-xl border border-line bg-white px-3 text-sm font-bold text-ink focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15">
            </form>
            <a href="{{ route('admin.schedule', ['tanggal' => $day->addDay()->toDateString()]) }}" class="flex size-11 items-center justify-center rounded-xl border border-line bg-white text-ink hover:bg-surface-bg">
                <x-icon name="chevron-right" class="size-5" /><span class="sr-only">Hari berikutnya</span>
            </a>
            @unless ($day->isToday())
                <x-button :href="route('admin.schedule')" variant="secondary" size="sm">Hari ini</x-button>
            @endunless
        </div>
    </x-slot:actions>

    <p class="-mt-3 text-sm font-bold text-ink">{{ $day->translatedFormat('l') }}, {{ $day->translatedFormat('j F Y') }}</p>

    @if ($unassigned->isNotEmpty())
        <section class="flex flex-wrap items-center gap-2 rounded-card border border-warning/40 bg-warning-50 px-4 py-3 text-[13px] text-warning-900">
            <x-icon name="triangle-alert" class="size-5" />
            <b>{{ $unassigned->count() }} order belum di-assign:</b>
            @foreach ($unassigned as $order)
                <a href="{{ route('admin.orders.show', $order) }}" class="rounded-lg bg-white px-2 py-1 font-bold text-ink hover:text-primary">{{ $order->code }} · {{ $order->scheduled_start->format('H:i') }}</a>
            @endforeach
        </section>
    @endif

    <section class="overflow-hidden rounded-card border border-line bg-white">
        <div class="relative overflow-x-auto">
            <div class="min-w-[1080px] px-5 pt-4 pb-2">
                {{-- Skala jam --}}
                <div class="grid grid-cols-[200px_minmax(0,1fr)] border-b border-line pb-2">
                    <span class="text-xs font-bold text-muted">Tim</span>
                    <div class="flex text-xs font-semibold text-muted tabular-nums">
                        @foreach ($hours as $hour)
                            <span class="flex-1">{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00</span>
                        @endforeach
                    </div>
                </div>

                <div class="relative">
                    @if ($nowPct !== null)
                        <div class="pointer-events-none absolute inset-y-0 z-10" style="left: calc(200px + (100% - 200px) * {{ round($nowPct / 100, 4) }})" aria-hidden="true">
                            <span class="absolute -top-1 -translate-x-1/2 rounded-[5px] bg-[#E5484D] px-1.5 text-[11px] font-bold text-white tabular-nums">{{ $now->format('H:i') }}</span>
                            <span class="absolute top-4 bottom-0 w-0.5 -translate-x-1/2 bg-[#E5484D]"></span>
                        </div>
                    @endif

                    @foreach ($lanes as $heading => $rows)
                        <p class="pt-4 pb-1 text-[11px] font-bold tracking-[0.6px] text-muted uppercase">
                            {{ $heading }} @if ($heading === 'Helper')<span class="font-semibold tracking-normal normal-case">(tanpa aplikasi)</span>@endif
                        </p>

                        @forelse ($rows as $row)
                            @php
                                $minutes = $row['orders']->sum('total_est_minutes');
                                $isTechnician = $heading === 'Teknisi';
                                $partners = $isTechnician ? collect() : $row['orders']->map(fn ($order) => $order->technician?->user->name)->filter()->unique();
                            @endphp
                            <div class="grid grid-cols-[200px_minmax(0,1fr)] items-center border-b border-[#EEF2F8] last:border-b-0">
                                <div class="flex items-center gap-2.5 py-2 pr-3">
                                    <x-avatar :name="$row['name']" :src="$isTechnician ? $row['staff']->photoUrl() : null" size="sm" :class="\Illuminate\Support\Arr::toCssClasses(['bg-line-strong!' => $row['leave'], 'bg-blue-50! text-primary!' => ! $isTechnician && ! $row['leave']])" />
                                    <div class="min-w-0">
                                        <p @class(['truncate text-[13px] font-bold', 'text-ink' => ! $row['leave'], 'text-muted' => $row['leave']])>{{ $row['name'] }}</p>
                                        <p class="truncate text-[11px] text-muted">
                                            @if ($row['leave'])
                                                Cuti
                                            @elseif (! $row['staff']->is_active)
                                                Nonaktif
                                            @elseif ($isTechnician)
                                                {{ $row['orders']->count() }} order · {{ Format::decimal($minutes / 60) }} jam
                                            @else
                                                {{ $partners->isNotEmpty() ? 'Ikut '.$partners->implode(', ') : 'Tidak ada order' }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <div class="relative h-16" style="{{ $grid }}">
                                    @if ($row['leave'])
                                        <div class="absolute inset-x-0 top-2.5 flex h-11 items-center justify-center rounded-[10px] text-xs font-bold text-muted" style="background: repeating-linear-gradient(135deg, #F4F7FC 0 8px, #E8EDF5 8px 16px);">
                                            Cuti sepanjang hari{{ $row['leave']->reason ? ' · '.$row['leave']->reason : '' }}
                                        </div>
                                    @endif

                                    @foreach ($row['orders'] as $order)
                                        @php
                                            $left = $pct($order->scheduled_start);
                                            $work = max(0.8, $pct($order->scheduled_end) - $left);
                                            $buffer = max(0, $pct($order->blocked_until) - $pct($order->scheduled_end));
                                            $style = $isTechnician ? ($styles[$order->status->value] ?? 'bg-surface-bg text-ink') : 'bg-blue-50 text-blue-800';
                                        @endphp
                                        <a
                                            href="{{ route('admin.orders.show', $order) }}"
                                            class="group absolute top-2.5 flex h-11 overflow-hidden rounded-[10px] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/30"
                                            style="left: {{ round($left, 3) }}%; width: {{ round($work + $buffer, 3) }}%"
                                            title="{{ $order->code }} · {{ $order->client->name }} · {{ $order->scheduled_start->format('H:i') }}–{{ $order->scheduled_end->format('H:i') }} (+ buffer s/d {{ $order->blocked_until->format('H:i') }}) · {{ $order->status->label() }}"
                                        >
                                            <span class="flex min-w-0 flex-col justify-center px-2.5 text-[11px] leading-tight {{ $style }} {{ $buffer > 0 ? 'rounded-l-[10px]' : 'rounded-[10px]' }} group-hover:brightness-95" style="width: {{ round($work / ($work + $buffer) * 100, 3) }}%">
                                                <b class="truncate text-xs">{{ $order->code }}{{ $isTechnician ? ' · '.$firstName($order->client->name) : '' }}</b>
                                                <span class="truncate opacity-85">
                                                    @if ($isTechnician)
                                                        {{ $order->items_count }} unit · {{ $order->status->label() }}
                                                    @else
                                                        bersama {{ $order->technician?->user->name ?? '–' }}
                                                    @endif
                                                </span>
                                            </span>
                                            @if ($buffer > 0)
                                                <span class="flex-1 rounded-r-[10px] border border-l-0 border-line" style="background: repeating-linear-gradient(135deg, #E3E8F2 0 3px, #FFFFFF 3px 6px);" aria-hidden="true"></span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="py-3 text-sm text-muted">Belum ada {{ strtolower($heading) }} aktif.</p>
                        @endforelse
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-semibold text-ink-soft">
        <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded bg-success-50"></span>Selesai / lunas</span>
        <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded bg-navy"></span>Dikerjakan</span>
        <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded bg-primary"></span>Dalam perjalanan</span>
        <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded bg-blue-100"></span>Dikonfirmasi teknisi</span>
        <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded border-[1.5px] border-dashed border-primary bg-white"></span>Ditugaskan (menunggu teknisi)</span>
        <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded border border-line" style="background: repeating-linear-gradient(135deg, #E3E8F2 0 3px, #FFFFFF 3px 6px);"></span>Buffer perjalanan</span>
    </div>
</x-layouts.admin>
