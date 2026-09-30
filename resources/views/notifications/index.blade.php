@php
    $isTeknisi = $layout === 'layouts.teknisi';
    $home = auth()->user()->homeUrl();
@endphp

<x-dynamic-component
    :component="$layout"
    title="Notifikasi"
    :subtitle="$unread ? $unread.' belum dibaca' : 'Semua notifikasi sudah dibaca.'"
    :back="$isTeknisi || $layout === 'layouts.client' ? $home : null"
    :breadcrumbs="[['Notifikasi', null]]"
>
    @if ($unread)
        @php($readAll = '<form method="POST" action="'.route('notifications.read-all').'">'.csrf_field().'<button type="submit" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-[13px] font-bold text-primary hover:bg-blue-50"><span>Tandai semua dibaca</span></button></form>')
        <x-slot:actions>{!! $readAll !!}</x-slot:actions>
        <x-slot:headerActions>{!! $readAll !!}</x-slot:headerActions>
    @endif

    <section @class(['overflow-hidden rounded-card border border-line bg-white', 'max-w-3xl' => $layout === 'layouts.admin'])>
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            <a
                href="{{ route('notifications.open', $notification->id) }}"
                @class([
                    'flex items-start gap-3 border-b border-[#EEF2F8] px-4 py-3.5 last:border-b-0 hover:bg-surface-bg focus-visible:bg-surface-bg focus-visible:outline-none sm:px-5',
                    'bg-[#F2F6FE]' => $notification->read_at === null,
                ])
            >
                <span @class([
                    'mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl',
                    'bg-primary text-white' => $notification->read_at === null,
                    'bg-surface-bg text-muted' => $notification->read_at !== null,
                ])>
                    <x-icon :name="$data['icon'] ?? 'bell'" class="size-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-start justify-between gap-2">
                        <span class="text-sm font-bold text-ink">{{ $data['title'] ?? 'Notifikasi' }}</span>
                        <span class="shrink-0 text-xs text-muted" title="{{ $notification->created_at->format('d M Y H:i') }}">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                    <span class="mt-0.5 block text-[13px] leading-relaxed text-ink-soft">{{ $data['message'] ?? '' }}</span>
                    @if (! empty($data['note']))
                        <span class="mt-1 block text-xs text-muted">Keterangan: {{ $data['note'] }}</span>
                    @endif
                </span>
                @if ($notification->read_at === null)
                    <span class="mt-2 size-2.5 shrink-0 rounded-full bg-primary" aria-label="Belum dibaca"></span>
                @endif
            </a>
        @empty
            <x-empty-state icon="bell" title="Belum ada notifikasi" description="Kabar terbaru tentang order akan muncul di sini." />
        @endforelse
    </section>

    @if ($notifications->hasPages())
        <div>{{ $notifications->links() }}</div>
    @endif
</x-dynamic-component>
