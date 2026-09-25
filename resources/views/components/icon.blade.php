{{-- Ikon Lucide via blade-icons. Contoh: <x-icon name="map-pin" class="size-4" /> --}}
@props(['name'])

{{ svg('lucide-'.$name, $attributes->get('class', 'size-5'), array_merge(
    ['stroke-width' => '1.8', 'aria-hidden' => 'true', 'focusable' => 'false'],
    $attributes->except('class')->getAttributes(),
)) }}
