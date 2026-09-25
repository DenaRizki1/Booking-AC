{{-- Sakelar on/off (checkbox role="switch"). Hidden input mengirim "0" saat nonaktif. --}}
@props(['name' => null, 'id' => null, 'checked' => false, 'label' => null, 'description' => null])

@php
    $id ??= $name ? 'f-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], ['-', ''], $name)) : null;
    $errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null;
    $isOn = $errorKey && old() ? (bool) old($errorKey) : $checked;
@endphp

<label @if ($id) for="{{ $id }}" @endif class="inline-flex min-h-11 cursor-pointer items-center gap-3">
    @if ($name)
        <input type="hidden" name="{{ $name }}" value="0">
    @endif
    <input
        type="checkbox"
        role="switch"
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        value="1"
        @checked($isOn)
        {{ $attributes->merge(['class' => 'peer sr-only']) }}
    >
    <span aria-hidden="true" class="relative h-6 w-10 shrink-0 rounded-full bg-line-strong transition-colors after:absolute after:top-1 after:left-1 after:size-4 after:rounded-full after:bg-white after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-4 peer-focus-visible:ring-4 peer-focus-visible:ring-primary/25 peer-disabled:opacity-50"></span>
    @if ($label || $description || $slot->isNotEmpty())
        <span>
            <span class="block text-[15px] font-semibold text-ink">{{ $label ?? $slot }}</span>
            @if ($description)
                <span class="block text-[13px] text-muted">{{ $description }}</span>
            @endif
        </span>
    @endif
</label>
