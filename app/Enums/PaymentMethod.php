<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case TUNAI = 'TUNAI';
    case TRANSFER = 'TRANSFER';

    public function label(): string
    {
        return match ($this) {
            self::TUNAI => 'Tunai',
            self::TRANSFER => 'Transfer',
        };
    }
}
