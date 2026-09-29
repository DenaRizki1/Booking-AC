<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pindah urutan baris master data (naik / turun).
 */
class MoveSortOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['direction' => ['required', 'in:up,down']];
    }

    /** @return 'up'|'down' */
    public function direction(): string
    {
        return $this->validated('direction') === 'up' ? 'up' : 'down';
    }
}
