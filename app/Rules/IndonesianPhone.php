<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * No. HP Indonesia: 08…, 628…, atau +628… (10–13 digit). Spasi, titik, strip & kurung diabaikan.
 */
class IndonesianPhone implements ValidationRule
{
    private const PATTERN = '/^(?:\+62|62|0)8[1-9]\d{7,10}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, self::clean($value)) !== 1) {
            $fail('Format no. HP tidak valid. Gunakan 08… atau +62…');
        }
    }

    /**
     * Seragamkan ke format 08xxxxxxxxxx.
     */
    public static function normalize(string $value): string
    {
        return (string) preg_replace('/^(?:\+62|62)/', '0', self::clean($value));
    }

    private static function clean(string $value): string
    {
        return (string) preg_replace('/[\s.\-()]/', '', trim($value));
    }
}
