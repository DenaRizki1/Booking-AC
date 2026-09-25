<?php

namespace App\Enums;

enum UserRole: string
{
    case CLIENT = 'client';
    case ADMIN = 'admin';
    case TEKNISI = 'teknisi';

    public function label(): string
    {
        return match ($this) {
            self::CLIENT => 'Client',
            self::ADMIN => 'Admin',
            self::TEKNISI => 'Teknisi',
        };
    }
}
