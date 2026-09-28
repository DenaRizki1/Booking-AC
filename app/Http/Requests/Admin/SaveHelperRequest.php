<?php

namespace App\Http\Requests\Admin;

use App\Rules\IndonesianPhone;
use Illuminate\Foundation\Http\FormRequest;

class SaveHelperRequest extends FormRequest
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
            'phone' => ['nullable', 'string', new IndonesianPhone],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nama', 'phone' => 'no. HP', 'notes' => 'catatan'];
    }

    /**
     * @return array{name: string, phone: ?string, notes: ?string, is_active: bool}
     */
    public function helperData(): array
    {
        $phone = $this->validated('phone');

        return [
            'name' => (string) $this->validated('name'),
            'phone' => $phone ? IndonesianPhone::normalize((string) $phone) : null,
            'notes' => $this->validated('notes'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
