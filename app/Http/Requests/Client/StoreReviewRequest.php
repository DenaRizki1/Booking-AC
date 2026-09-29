<?php

namespace App\Http\Requests\Client;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cek kepemilikan order sebelum validasi agar pesan validasi tidak bocor ke pengguna lain.
        $order = $this->route('order');

        return $order instanceof Order && (bool) $this->user()?->can('view', $order);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Pilih rating 1–5 bintang.',
            'rating.*' => 'Rating harus 1–5 bintang.',
            'comment.max' => 'Ulasan maksimal 1.000 karakter.',
        ];
    }
}
