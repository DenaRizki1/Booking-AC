{{-- Tab halaman layanan: Info layanan | Matriks harga. --}}
@php
    $tabs = [
        'info' => ['Info layanan', route('admin.services.edit', $service)],
        'prices' => ['Matriks harga', Route::has('admin.services.prices.edit') ? route('admin.services.prices.edit', $service) : null],
    ];
@endphp

<nav class="flex gap-6 border-b border-line" aria-label="Bagian layanan">
    @foreach ($tabs as $key => [$label, $url])
        @if ($url)
            <a
                href="{{ $url }}"
                @if ($active === $key) aria-current="page" @endif
                @class([
                    '-mb-px inline-flex min-h-11 items-center border-b-[2.5px] text-sm font-bold',
                    'border-primary text-primary' => $active === $key,
                    'border-transparent text-muted hover:text-ink' => $active !== $key,
                ])
            >{{ $label }}</a>
        @else
            <span aria-disabled="true" class="-mb-px inline-flex min-h-11 items-center gap-2 border-b-[2.5px] border-transparent text-sm font-bold text-muted/60">
                {{ $label }} <span class="text-[10px] tracking-wide uppercase">Segera</span>
            </span>
        @endif
    @endforeach
</nav>
