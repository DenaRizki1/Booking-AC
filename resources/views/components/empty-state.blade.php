@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-10 text-center']) }}>
    <div class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-blue-50 text-primary">
        <x-icon :name="$icon" class="size-7" />
    </div>
    <h3 class="text-base font-extrabold text-ink">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
