<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alasan wajib saat admin menolak / membatalkan order (ditampilkan ke client).
 */
class OrderReasonRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'reason';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan wajib diisi.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
