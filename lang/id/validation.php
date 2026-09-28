<?php

/*
| Pesan validasi Bahasa Indonesia (fallback ke bahasa Inggris untuk aturan yang tidak ada di sini).
*/

return [
    'accepted' => ':Attribute harus disetujui.',
    'after' => ':Attribute harus setelah :date.',
    'after_or_equal' => ':Attribute harus sama dengan atau setelah :date.',
    'array' => ':Attribute harus berupa daftar.',
    'before' => ':Attribute harus sebelum :date.',
    'before_or_equal' => ':Attribute harus sama dengan atau sebelum :date.',
    'between' => [
        'array' => ':Attribute harus berisi :min sampai :max item.',
        'file' => ':Attribute harus berukuran :min sampai :max KB.',
        'numeric' => ':Attribute harus antara :min dan :max.',
        'string' => ':Attribute harus :min sampai :max karakter.',
    ],
    'boolean' => ':Attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'date_format' => 'Format :attribute harus :format.',
    'decimal' => ':Attribute harus memiliki :decimal angka desimal.',
    'different' => ':Attribute dan :other harus berbeda.',
    'digits' => ':Attribute harus :digits digit.',
    'digits_between' => ':Attribute harus :min sampai :max digit.',
    'distinct' => ':Attribute memiliki nilai ganda.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'file' => ':Attribute harus berupa file.',
    'gt' => [
        'numeric' => ':Attribute harus lebih besar dari :value.',
        'string' => ':Attribute harus lebih dari :value karakter.',
    ],
    'gte' => [
        'numeric' => ':Attribute harus lebih besar dari atau sama dengan :value.',
        'string' => ':Attribute minimal :value karakter.',
    ],
    'image' => ':Attribute harus berupa gambar.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'lt' => [
        'numeric' => ':Attribute harus lebih kecil dari :value.',
    ],
    'lte' => [
        'numeric' => ':Attribute harus lebih kecil dari atau sama dengan :value.',
    ],
    'max' => [
        'array' => ':Attribute maksimal :max item.',
        'file' => ':Attribute maksimal :max KB.',
        'numeric' => ':Attribute maksimal :max.',
        'string' => ':Attribute maksimal :max karakter.',
    ],
    'mimes' => ':Attribute harus berupa file: :values.',
    'min' => [
        'array' => ':Attribute minimal :min item.',
        'file' => ':Attribute minimal :min KB.',
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'numeric' => ':Attribute harus berupa angka.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':Attribute wajib diisi.',
    'required_if' => ':Attribute wajib diisi bila :other :value.',
    'required_with' => ':Attribute wajib diisi bila :values diisi.',
    'same' => ':Attribute dan :other harus sama.',
    'size' => [
        'numeric' => ':Attribute harus :size.',
        'string' => ':Attribute harus :size karakter.',
    ],
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah dipakai.',
    'url' => 'Format :attribute tidak valid.',

    'custom' => [],

    'attributes' => [],
];
