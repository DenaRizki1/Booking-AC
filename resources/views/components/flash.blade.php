{{-- Pesan flash dari session (success / error / warning / info) atau :type + :message langsung. --}}
@props(['type' => null, 'message' => null, 'dismissible' => true])

@php
    $styles = [
        'success' => ['bg-success-50 text-success-700 border-success/20', 'circle-check'],
        'error' => ['bg-danger-50 text-danger-700 border-danger/20', 'circle-alert'],
        'warning' => ['bg-warning-50 text-warning-900 border-warning/30', 'triangle-alert'],
        'info' => ['bg-blue-50 text-blue-800 border-primary/20', 'info'],
    ];

    $messages = $message !== null
        ? [[$type ?? 'info', $message]]
        : collect(array_keys($styles))
            ->filter(fn ($key) => session()->has($key))
            ->map(fn ($key) => [$key, session($key)])
            ->values()
            ->all();
@endphp

@foreach ($messages as [$flashType, $flashMessage])
    <div
        x-data="{ open: true }"
        x-show="open"
        x-transition.opacity
        role="{{ $flashType === 'error' ? 'alert' : 'status' }}"
        {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm font-semibold '.$styles[$flashType][0]]) }}
    >
        <x-icon :name="$styles[$flashType][1]" class="mt-0.5 size-5" />
        <p class="flex-1 py-0.5">{{ $flashMessage }}</p>
        @if ($dismissible)
            <button type="button" x-on:click="open = false" class="-my-2 -mr-2 flex size-11 shrink-0 items-center justify-center rounded-lg opacity-70 hover:opacity-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                <x-icon name="x" class="size-4" />
                <span class="sr-only">Tutup pesan</span>
            </button>
        @endif
    </div>
@endforeach
