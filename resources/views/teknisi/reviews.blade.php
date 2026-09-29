@use('App\Support\Format')

@php($total = (int) $distribution->sum())

<x-layouts.teknisi title="Ulasan">
    <x-slot:header>
        <p class="text-lg font-extrabold">Rating & ulasan saya</p>
        <div class="grid grid-cols-3 gap-2">
            <div class="rounded-xl bg-white/8 px-3 py-2.5">
                <p class="flex items-center gap-1 text-xl font-extrabold tabular-nums"><x-icon name="star" class="size-4 fill-warning text-warning" />{{ $technician->review_count ? number_format((float) $technician->avg_rating, 1, ',', '') : '–' }}</p>
                <p class="text-[11px] text-white/75">Rating</p>
            </div>
            <div class="rounded-xl bg-white/8 px-3 py-2.5">
                <p class="text-xl font-extrabold tabular-nums">{{ $total }}</p>
                <p class="text-[11px] text-white/75">Ulasan</p>
            </div>
            <a href="{{ route('teknisi.tasks', ['tab' => 'riwayat']) }}" class="rounded-xl bg-white/8 px-3 py-2.5 hover:bg-white/12">
                <p class="text-xl font-extrabold tabular-nums">{{ $completedCount }}</p>
                <p class="text-[11px] text-white/75">Tugas selesai</p>
            </a>
        </div>
    </x-slot:header>

    @if ($total > 0)
        <x-card>
            <div class="flex flex-col gap-1.5">
                @foreach ([5, 4, 3, 2, 1] as $stars)
                    @php($count = (int) ($distribution[$stars] ?? 0))
                    <div class="grid grid-cols-[28px_minmax(0,1fr)_28px] items-center gap-2 text-[13px]">
                        <span class="font-bold text-ink">{{ $stars }}★</span>
                        <span class="h-2 rounded bg-[#EEF2F8]"><span class="block h-2 rounded bg-warning" style="width: {{ round($count / $total * 100) }}%"></span></span>
                        <span class="text-right text-muted tabular-nums">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    @forelse ($reviews as $review)
        <article class="rounded-[18px] border border-line bg-white p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-ink">{{ $review->client->name }}</p>
                    <p class="text-xs text-muted">{{ $review->order->code }} · {{ Format::date($review->created_at) }}</p>
                </div>
                <span class="shrink-0 tracking-[1px]" aria-label="{{ $review->rating }} dari 5 bintang">
                    <span class="text-warning">{{ str_repeat('★', $review->rating) }}</span><span class="text-[#D5DEEE]">{{ str_repeat('★', 5 - $review->rating) }}</span>
                </span>
            </div>
            @if ($review->comment)
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $review->comment }}</p>
            @endif
        </article>
    @empty
        <x-card>
            <x-empty-state icon="star" title="Belum ada ulasan" description="Ulasan dari client muncul setelah mereka menilai tugas yang selesai." />
        </x-card>
    @endforelse

    @if ($reviews->hasPages())
        <div>{{ $reviews->links() }}</div>
    @endif
</x-layouts.teknisi>
