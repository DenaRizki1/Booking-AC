@props([
    'name' => null,
    'label' => null,
    'id' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'error' => null,
    'icon' => null,
    'required' => false,
])

@php
    $id ??= $name ? 'f-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], ['-', ''], $name)) : null;
    $errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null;
    $message = $error ?? ($errorKey ? ($errors ?? null)?->first($errorKey) : null);
    $current = $type === 'password' ? null : ($errorKey ? old($errorKey, $value) : $value);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp

<div>
    @if ($label)
        <x-label :for="$id" :required="$required">{{ $label }}</x-label>
    @endif

    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted" />
        @endif
        <input
            type="{{ $type }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($id) id="{{ $id }}" @endif
            @if ($current !== null) value="{{ $current }}" @endif
            @required($required)
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class([
                'block min-h-12 w-full rounded-xl border-[1.5px] bg-white px-3.5 text-[15px] text-ink placeholder:text-muted/70',
                'focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15',
                'disabled:cursor-not-allowed disabled:bg-surface-bg disabled:text-muted',
                'pl-11' => $icon,
                'pr-12' => isset($suffix),
                'border-danger' => $message,
                'border-line' => ! $message,
            ]) }}
        >
        @isset($suffix)
            <div class="absolute inset-y-0 right-0.5 flex items-center">{{ $suffix }}</div>
        @endisset
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-[13px] text-muted">{{ $hint }}</p>
    @endif
    <x-field-error :messages="$message ? [$message] : []" :id="$id.'-error'" />
</div>
