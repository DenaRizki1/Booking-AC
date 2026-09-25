{{-- Langkah wizard buat order: 1 Unit AC — 2 Jadwal — 3 Ringkasan. --}}
@props(['step' => 1])

@php($steps = [1 => 'Unit AC', 2 => 'Jadwal', 3 => 'Ringkasan'])

<ol class="flex items-center gap-2" aria-label="Langkah buat order">
    @foreach ($steps as $number => $label)
        <li class="flex items-center gap-2 {{ $loop->last ? '' : 'flex-1' }}" @if ($number === $step) aria-current="step" @endif>
            <span @class([
                'flex size-7 shrink-0 items-center justify-center rounded-full text-[13px] font-bold',
                'bg-success-50 text-success' => $number < $step,
                'bg-primary text-white' => $number === $step,
                'bg-surface-bg text-muted' => $number > $step,
            ])>
                @if ($number < $step) <x-icon name="check" class="size-4" stroke-width="3" /> @else {{ $number }} @endif
            </span>
            <span @class([
                'text-sm font-semibold whitespace-nowrap',
                'text-success-700' => $number < $step,
                'text-primary' => $number === $step,
                'text-muted' => $number > $step,
            ])>{{ $label }}</span>
            @unless ($loop->last)
                <span aria-hidden="true" @class(['h-0.5 min-w-4 flex-1 rounded-full', 'bg-primary' => $number < $step, 'bg-line' => $number >= $step])></span>
            @endunless
        </li>
    @endforeach
</ol>
