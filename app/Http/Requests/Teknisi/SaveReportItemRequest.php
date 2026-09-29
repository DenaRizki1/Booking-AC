<?php

namespace App\Http\Requests\Teknisi;

use App\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Harga final + catatan satu unit. Harga di atas estimasi maks. → keterangan wajib.
 */
class SaveReportItemRequest extends FormRequest
{
    public const MAX_PRICE = 100_000_000;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('final_price'));

        $this->merge(['final_price' => $digits === '' ? null : (int) substr((string) $digits, 0, 12)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'final_price' => ['required', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
            'price_note' => ['nullable', 'string', 'max:500'],
            'work_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $item = $this->route('item');
            if ($validator->errors()->isNotEmpty() || ! $item instanceof OrderItem) {
                return;
            }

            if ((int) $this->input('final_price') > $item->est_price_max && blank($this->input('price_note'))) {
                $validator->errors()->add('price_note', 'Harga di atas estimasi maksimal. Jelaskan tambahan biayanya (terlihat oleh client).');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'final_price.required' => 'Harga final wajib diisi.',
            'final_price.max' => 'Harga final terlalu besar.',
            'final_price.*' => 'Harga final tidak valid.',
            'price_note.max' => 'Keterangan maksimal 500 karakter.',
            'work_note.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
