<?php

namespace App\Enums;

enum OrderType: string
{
    case REGULER = 'REGULER';
    case GARANSI = 'GARANSI';

    public function label(): string
    {
        return match ($this) {
            self::REGULER => 'Reguler',
            self::GARANSI => 'Garansi',
        };
    }
}
