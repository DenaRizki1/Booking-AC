{{-- :options = [value => label]. Atau isi <option> sendiri lewat slot. --}}
@props([
    'name' => null,
    'label' => null,
    'id' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php
    $id ??= $name ? 'f-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], ['-', ''], $name)) : null;
    $errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null;
    $message = $error ?? ($errorKey ? ($errors ?? null)?->first($errorKey) : null);
    $current = (string) ($errorKey ? old($errorKey, $value) : $value);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp

<div>
    @if ($label)
        <x-label :for="$id" :required="$required">{{ $label }}</x-label>
    @endif

    <div class="relative">
        <select
            @if ($name) name="{{ $name }}" @endif
            @if ($id) id="{{ $id }}" @endif
            @required($required)
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class([
                'block min-h-12 w-full cursor-pointer appearance-none rounded-xl border-[1.5px] bg-white pr-11 pl-3.5 text-[15px] font-semibold text-ink',
                'focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15',
                'disabled:cursor-not-allowed disabled:bg-surface-bg disabled:text-muted',
                'border-danger' => $message,
                'border-line' => ! $message,
            ]) }}
        >
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 size-5 -translate-y-1/2 text-muted" />
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-[13px] text-muted">{{ $hint }}</p>
    @endif
    <x-field-error :messages="$message ? [$message] : []" :id="$id.'-error'" />
</div>
