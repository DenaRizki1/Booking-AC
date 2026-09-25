{{-- Pesan error validasi. Pakai :name (dibaca dari $errors) atau :messages langsung. --}}
@props(['name' => null, 'messages' => null, 'id' => null])

@php
    $messages = $messages !== null
        ? (array) $messages
        : ($name && isset($errors) ? $errors->get(str_replace(['[', ']'], ['.', ''], $name)) : []);
@endphp

@if (count($messages))
    <p @if ($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'mt-1.5 flex items-start gap-1.5 text-[13px] font-semibold text-danger']) }}>
        <x-icon name="circle-alert" class="mt-px size-4" />
        <span>{{ $messages[0] }}</span>
    </p>
@endif
