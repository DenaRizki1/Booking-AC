<?php

namespace App\Exceptions;

use DomainException;

/**
 * Satu atau lebih unit AC tidak punya harga aktif untuk kombinasi layanan × PK × tipe.
 */
class UnavailableUnitsException extends DomainException
{
    /**
     * @param  array<int, string>  $unitErrors  indeks unit (mulai 0) => pesan
     */
    public function __construct(public readonly array $unitErrors)
    {
        parent::__construct(implode(' ', $unitErrors));
    }
}
