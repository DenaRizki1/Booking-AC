<?php

namespace App\Enums;

enum PhotoKind: string
{
    case KELUHAN = 'KELUHAN';
    case SEBELUM = 'SEBELUM';
    case SESUDAH = 'SESUDAH';

    public function label(): string
    {
        return match ($this) {
            self::KELUHAN => 'Keluhan',
            self::SEBELUM => 'Sebelum',
            self::SESUDAH => 'Sesudah',
        };
    }
}
