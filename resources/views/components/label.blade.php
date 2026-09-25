@props(['for' => null, 'required' => false])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'mb-1.5 block text-[13px] font-semibold text-ink-soft']) }}>
    {{ $slot }}
    @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
</label>
