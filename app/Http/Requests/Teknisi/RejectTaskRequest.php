<?php

namespace App\Http\Requests\Teknisi;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alasan teknisi menolak tugas (wajib; order kembali MENUNGGU).
 */
class RejectTaskRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'reject';

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
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan menolak wajib diisi.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
