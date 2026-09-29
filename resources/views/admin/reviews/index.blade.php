@use('App\Support\Format')

@php
    $filterUrl = fn (array $changes) => route('admin.reviews.index', array_filter(array_merge($filters, $changes), fn ($value) => $value !== null && $value !== ''));
@endphp

<x-layouts.admin title="Ulasan" subtitle="Rating & ulasan client setelah order selesai." :breadcrumbs="[['Master data', null], ['Ulasan', null]]">
    <div class="grid items-start gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
        <div class="flex flex-col gap-5">
            <x-card>
                <p class="text-[13px] font-semibold text-muted">Rata-rata</p>
                <p class="mt-1 flex items-center gap-2 text-[34px] font-extrabold tracking-tight text-navy tabular-nums">
                    {{ $total ? number_format($average, 1, ',', '') : '–' }}
                    <x-icon name="star" class="size-6 fill-warning text-warning" />
                </p>
                <p class="text-xs text-muted">dari {{ $total }} ulasan</p>

                <div class="mt-4 flex flex-col gap-1">
                    @foreach ([5, 4, 3, 2, 1] as $stars)
                        @php($count = (int) ($distribution[$stars] ?? 0))
                        <a
                            href="{{ $filterUrl(['rating' => (int) ($filters['rating'] ?? 0) === $stars ? null : $stars]) }}"
                            @class([
                                'grid min-h-9 grid-cols-[28px_minmax(0,1fr)_32px] items-center gap-2 rounded-lg px-2 text-[13px] hover:bg-surface-bg',
                                'bg-blue-50 ring-1 ring-primary/30' => (int) ($filters['rating'] ?? 0) === $stars,
                            ])
                            aria-label="Tampilkan ulasan {{ $stars }} bintang ({{ $count }})"
                        >
                            <span class="font-bold text-ink">{{ $stars }}★</span>
                            <span class="h-2 rounded bg-[#EEF2F8]"><span class="block h-2 rounded bg-warning" style="width: {{ $total ? round($count / $total * 100) : 0 }}%"></span></span>
                            <span class="text-right text-muted tabular-nums">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>
            </x-card>

            <form method="GET" action="{{ route('admin.reviews.index') }}" class="flex flex-col gap-3 rounded-card border border-line bg-white p-5">
                @if (! empty($filters['rating']))<input type="hidden" name="rating" value="{{ $filters['rating'] }}">@endif
                <div>
                    <x-label for="f-technician">Teknisi</x-label>
                    <select id="f-technician" name="technician" onchange="this.form.submit()" class="min-h-12 w-full rounded-xl border-[1.5px] border-line bg-white px-3.5 text-[15px] text-ink focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15">
                        <option value="">Semua teknisi</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}" @selected((int) ($filters['technician'] ?? 0) === $technician->id)>{{ $technician->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><x-button type="submit" variant="secondary" size="sm">Terapkan</x-button></noscript>
                @if ($filters)
                    <x-button :href="route('admin.reviews.index')" variant="ghost" size="sm">Reset filter</x-button>
                @endif
            </form>
        </div>

        <section class="flex flex-col gap-3">
            @forelse ($reviews as $review)
                <article class="rounded-card border border-line bg-white p-5">
                    <div class="flex flex-wrap items-start gap-3">
                        <x-avatar :name="$review->client->name" :src="$review->client->avatar" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-ink">{{ $review->client->name }} <span class="font-normal text-muted">untuk</span> {{ $review->technician?->user->name ?? '–' }}</p>
                            <p class="text-xs text-muted">
                                {{ Format::date($review->created_at) }} ·
                                <a href="{{ route('admin.orders.show', $review->order) }}" class="font-semibold text-primary">{{ $review->order->code }}</a>
                            </p>
                        </div>
                        <span class="tracking-[1px]" aria-label="{{ $review->rating }} dari 5 bintang">
                            <span class="text-warning">{{ str_repeat('★', $review->rating) }}</span><span class="text-[#D5DEEE]">{{ str_repeat('★', 5 - $review->rating) }}</span>
                        </span>
                    </div>
                    @if ($review->comment)
                        <p class="mt-3 text-sm leading-relaxed text-ink-soft">{{ $review->comment }}</p>
                    @else
                        <p class="mt-3 text-sm text-muted italic">Tanpa komentar.</p>
                    @endif
                </article>
            @empty
                <x-card>
                    <x-empty-state icon="star" title="Belum ada ulasan" :description="$filters ? 'Tidak ada ulasan yang cocok dengan filter.' : 'Ulasan muncul setelah client menilai order yang selesai.'" />
                </x-card>
            @endforelse

            @if ($reviews->hasPages())
                <div>{{ $reviews->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.admin>
