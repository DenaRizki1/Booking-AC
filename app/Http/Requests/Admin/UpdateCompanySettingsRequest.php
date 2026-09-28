<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'working_days' => array_values(array_map('intval', (array) $this->input('working_days', []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\s.\-]{6,20}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['required', 'string', 'max:1000'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'open_time' => ['required', 'date_format:H:i'],
            'close_time' => ['required', 'date_format:H:i', 'after:open_time'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'distinct', Rule::in(range(1, 7))],
            'slot_interval_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'travel_buffer_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'free_radius_km' => ['required', 'numeric', 'min:0', 'max:999'],
            'max_radius_km' => ['required', 'numeric', 'gt:free_radius_km', 'max:9999'],
            'fee_per_km' => ['required', 'integer', 'min:0', 'max:1000000'],
            'cancel_limit_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'warranty_days' => ['required', 'integer', 'min:0', 'max:365'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => 'nama perusahaan',
            'phone' => 'no. telepon',
            'email' => 'email',
            'address' => 'alamat kantor',
            'open_time' => 'jam buka',
            'close_time' => 'jam tutup',
            'working_days' => 'hari kerja',
            'slot_interval_minutes' => 'interval slot',
            'travel_buffer_minutes' => 'buffer perjalanan',
            'free_radius_km' => 'radius gratis',
            'max_radius_km' => 'radius maksimal',
            'fee_per_km' => 'tarif per km',
            'cancel_limit_hours' => 'batas pembatalan',
            'warranty_days' => 'garansi',
            'bank_name' => 'nama bank',
            'bank_account_no' => 'no. rekening',
            'bank_account_name' => 'atas nama',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lat.required' => 'Pilih titik kantor di peta.',
            'lng.required' => 'Pilih titik kantor di peta.',
            'lat.*' => 'Titik kantor tidak valid.',
            'lng.*' => 'Titik kantor tidak valid.',
            'phone.regex' => 'Format no. telepon tidak valid.',
            'close_time.after' => 'Jam tutup harus setelah jam buka.',
            'max_radius_km.gt' => 'Radius maksimal harus lebih besar dari radius gratis.',
            'working_days.required' => 'Pilih minimal satu hari kerja.',
            'working_days.min' => 'Pilih minimal satu hari kerja.',
            'working_days.*' => 'Hari kerja tidak valid.',
        ];
    }
}
