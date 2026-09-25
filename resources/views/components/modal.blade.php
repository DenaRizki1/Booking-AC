{{--
    Modal Alpine. Buka:  <x-button x-on:click="$dispatch('open-modal', 'nama')">
    Tutup:               $dispatch('close-modal', 'nama') atau tombol Esc / klik latar.
--}}
@props(['name', 'title' => null, 'show' => false, 'maxWidth' => 'md'])

@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
    $titleId = 'modal-'.$name.'-title';
@endphp

<div
    x-data="{ show: @js($show) }"
    x-init="$watch('show', value => {
        document.body.classList.toggle('overflow-hidden', value);
        if (value) $nextTick(() => ($refs.panel.querySelector('[autofocus]') ?? $refs.panel).focus());
    })"
    x-on:open-modal.window="if ($event.detail === @js($name)) show = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
>
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-navy/40" x-on:click="show = false"></div>

    <div
        x-ref="panel"
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
        role="dialog"
        aria-modal="true"
        @if ($title) aria-labelledby="{{ $titleId }}" @endif
        tabindex="-1"
        {{ $attributes->merge(['class' => 'relative max-h-[90vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-xl focus:outline-none sm:rounded-card '.($widths[$maxWidth] ?? $widths['md'])]) }}
    >
        <header class="flex items-center justify-between gap-3 border-b border-line py-2 pr-2 pl-5">
            <h2 id="{{ $titleId }}" class="text-base font-extrabold text-ink">{{ $title }}</h2>
            <button type="button" x-on:click="show = false" class="flex size-11 items-center justify-center rounded-xl text-muted hover:bg-surface-bg hover:text-ink focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                <x-icon name="x" />
                <span class="sr-only">Tutup</span>
            </button>
        </header>

        <div class="p-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="flex flex-col-reverse gap-2 border-t border-line px-5 py-4 sm:flex-row sm:justify-end">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
