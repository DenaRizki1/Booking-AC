@props(['name' => null, 'id' => null, 'value' => '1', 'checked' => false, 'label' => null, 'description' => null])

@php
    $id ??= $name ? 'f-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], ['-', ''], $name)).'-'.\Illuminate\Support\Str::slug($value) : null;
    $errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null;
    $isChecked = $errorKey && old() ? in_array((string) $value, array_map('strval', (array) old($errorKey)), true) : $checked;
@endphp

<label @if ($id) for="{{ $id }}" @endif class="inline-flex min-h-11 cursor-pointer items-start gap-3 py-2.5">
    <input
        type="checkbox"
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        value="{{ $value }}"
        @checked($isChecked)
        {{ $attributes->merge(['class' => 'mt-0.5 size-5 shrink-0 cursor-pointer rounded-md border-line-strong accent-primary focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25']) }}
    >
    <span>
        <span class="block text-[15px] font-semibold text-ink">{{ $label ?? $slot }}</span>
        @if ($description)
            <span class="block text-[13px] text-muted">{{ $description }}</span>
        @endif
    </span>
</label>
