<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveServiceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', Rule::in(Service::ICONS)],
            'requires_helper' => ['nullable', 'boolean'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama layanan',
            'description' => 'deskripsi',
            'icon' => 'ikon',
            'warranty_days' => 'garansi',
        ];
    }

    /**
     * Data siap simpan (boolean dari sakelar, garansi kosong = null → pakai default).
     *
     * @return array{name: string, description: ?string, icon: ?string, requires_helper: bool, warranty_days: ?int, is_active: bool}
     */
    public function serviceData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'description' => $this->validated('description'),
            'icon' => $this->validated('icon'),
            'requires_helper' => $this->boolean('requires_helper'),
            'warranty_days' => $this->filled('warranty_days') ? (int) $this->validated('warranty_days') : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
