<?php

namespace App\Http\Requests\Client;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Alasan pembatalan terlalu singkat.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
