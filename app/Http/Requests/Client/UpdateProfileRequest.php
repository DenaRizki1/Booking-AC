<?php

namespace App\Http\Requests\Client;

use App\Rules\IndonesianPhone;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new IndonesianPhone],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal 120 karakter.',
            'phone.required' => 'No. HP wajib diisi.',
        ];
    }

    /**
     * @return array{name: string, phone: string}
     */
    public function profile(): array
    {
        return [
            'name' => trim($this->string('name')->toString()),
            'phone' => IndonesianPhone::normalize($this->string('phone')->toString()),
        ];
    }
}
