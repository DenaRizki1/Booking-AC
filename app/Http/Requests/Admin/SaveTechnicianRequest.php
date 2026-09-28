<?php

namespace App\Http\Requests\Admin;

use App\Models\Technician;
use App\Rules\IndonesianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $technician = $this->route('technician');
        $userId = $technician instanceof Technician ? $technician->user_id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['required', 'string', new IndonesianPhone],
            'password' => $userId ? ['prohibited'] : ['required', 'string', Password::min(8)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'no. HP',
            'password' => 'password awal',
            'photo' => 'foto',
            'notes' => 'catatan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
            'photo.mimes' => 'Foto harus JPG, PNG, atau WEBP.',
            'password.min' => 'Password awal minimal 8 karakter.',
        ];
    }

    /**
     * @return array{name: string, email: string, phone: string, notes: ?string, is_active: bool}
     */
    public function profile(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'phone' => IndonesianPhone::normalize((string) $this->validated('phone')),
            'notes' => $this->validated('notes'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
