{{-- Foto profil atau inisial (mis. "DR") dalam lingkaran navy. --}}
@props(['name', 'src' => null, 'size' => 'md'])

@php
    $sizes = ['sm' => 'size-9 text-xs', 'md' => 'size-11 text-sm', 'lg' => 'size-14 text-lg'];
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp

@if ($src)
    <img src="{{ $src }}" alt="" referrerpolicy="no-referrer" {{ $attributes->merge(['class' => 'shrink-0 rounded-full object-cover '.($sizes[$size] ?? $sizes['md'])]) }}>
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-center rounded-full bg-navy font-extrabold text-white '.($sizes[$size] ?? $sizes['md'])]) }}>{{ $initials }}</span>
@endif
