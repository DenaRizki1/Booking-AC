<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Assign teknisi/helper gagal (bentrok, cuti, helper wajib, status tidak bisa di-assign).
 * $field = nama input form yang ditandai error.
 */
class OrderAssignmentException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'technician_id')
    {
        parent::__construct($message);
    }
}
