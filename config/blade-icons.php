<?php

return [

    'sets' => [],

    'class' => '',

    'attributes' => [],

    'fallback' => '',

    /*
    | Komponen <x-icon> disediakan aplikasi (resources/views/components/icon.blade.php)
    | yang memanggil helper svg('lucide-...'). Komponen bawaan paket dimatikan agar
    | tidak bentrok nama dan tidak mendaftarkan ribuan komponen ikon.
    */
    'components' => [
        'disabled' => true,
        'default' => null,
    ],

];
