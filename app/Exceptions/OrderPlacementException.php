<?php

namespace App\Exceptions;

use DomainException;

/**
 * Order gagal disimpan karena data berubah sejak dipilih (alamat, slot jam).
 * $field = nama isian form tempat pesan ditampilkan.
 */
class OrderPlacementException extends DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
