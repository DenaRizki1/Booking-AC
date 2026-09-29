<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignOrderRequest extends FormRequest
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
        return [
            'technician_id' => ['required', 'integer', 'exists:technicians,id'],
            'helper_id' => ['nullable', 'integer', 'exists:helpers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'technician_id.required' => 'Pilih teknisi yang akan ditugaskan.',
            'technician_id.exists' => 'Teknisi tidak ditemukan.',
            'helper_id.exists' => 'Helper tidak ditemukan.',
        ];
    }
}
