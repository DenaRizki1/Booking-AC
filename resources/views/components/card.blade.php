@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'rounded-card border border-line bg-white']) }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="text-[15px] font-extrabold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-[13px] text-muted">{{ $description }}</p>
                @endif
            </div>
            {{ $actions ?? '' }}
        </header>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
