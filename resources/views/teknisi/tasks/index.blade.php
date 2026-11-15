@use('App\Http\Controllers\Teknisi\TaskController')
@use('App\Support\Format')

<x-layouts.teknisi title="Tugas">
    <x-slot:header>
        <div class="flex items-center gap-3">
            <x-avatar :name="$technician->user->name" :src="$technician->photoUrl()" class="bg-primary!" />
            <div class="min-w-0 flex-1">
                <p class="text-[13px] text-white/75">{{ Format::date(now()) }}</p>
                <p class="truncate text-lg font-extrabold">Halo, {{ Str::before($technician->user->name, ' ') }}</p>
            </div>
            @if ($onDuty)
                <span class="inline-flex h-[30px] items-center gap-1.5 rounded-full bg-[#7CF0B6]/20 px-3 text-xs font-bold text-[#7CF0B6]"><span class="size-2 rounded-full bg-[#7CF0B6]"></span>Bertugas</span>
            @endif
        </div>

        <div class="grid grid-cols-3 gap-3 sm:gap-5">
            <div class="rounded-xl bg-white/8 px-3 py-3 sm:px-5 sm:py-4">
                <p class="text-2xl font-extrabold sm:text-3xl tabular-nums">{{ $todayCount }}</p>
                <p class="text-xs text-white/75">Tugas hari ini</p>
            </div>
            <div class="rounded-xl bg-white/8 px-3 py-3 sm:px-5 sm:py-4">
                <p class="text-2xl font-extrabold sm:text-3xl tabular-nums">{{ $todayDone }}</p>
                <p class="text-xs text-white/75">Selesai</p>
            </div>
            <div class="rounded-xl bg-white/8 px-3 py-3 sm:px-5 sm:py-4">
                <p class="flex items-center gap-1 text-2xl font-extrabold sm:text-3xl tabular-nums">
                    <x-icon name="star" class="size-4 fill-warning text-warning" />{{ $technician->review_count ? number_format((float) $technician->avg_rating, 1, ',', '') : '–' }}
                </p>
                <p class="text-xs text-white/75">Rating</p>
            </div>
        </div>

        <nav class="flex rounded-xl bg-white/8 p-1 sm:max-w-xl" aria-label="Filter tugas">
            @foreach (TaskController::TABS as $key => $label)
                <a
                    href="{{ route('teknisi.tasks', ['tab' => $key]) }}"
                    @if ($tab === $key) aria-current="page" @endif
                    @class([
                        'flex min-h-10 flex-1 items-center justify-center rounded-[10px] text-[13px] font-bold',
                        'bg-white text-navy' => $tab === $key,
                        'text-white/75 hover:text-white' => $tab !== $key,
                    ])
                >{{ $label }}</a>
            @endforeach
        </nav>
    </x-slot:header>

    <div class="grid items-start gap-5 xl:grid-cols-2">
        @forelse ($orders as $order)
            @include('teknisi.tasks.partials.card')
        @empty
            <x-card class="xl:col-span-2">
                <x-empty-state
                    icon="clipboard-list"
                    :title="match ($tab) { 'hari-ini' => 'Tidak ada tugas hari ini', 'mendatang' => 'Belum ada tugas mendatang', default => 'Belum ada riwayat' }"
                    :description="$tab === 'riwayat' ? 'Tugas yang sudah selesai akan tampil di sini.' : 'Tugas baru dari admin akan muncul di sini.'"
                />
            </x-card>
        @endforelse
    </div>

    @foreach ($orders->where('status', \App\Enums\OrderStatus::DITUGASKAN) as $order)
        @includeWhen(Route::has('teknisi.tasks.reject'), 'teknisi.tasks.partials.reject-modal')
    @endforeach
</x-layouts.teknisi>
