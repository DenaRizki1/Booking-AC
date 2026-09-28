<?php

namespace App\Http\Requests\Admin;

use App\Services\StaffLeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStaffLeaveRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'leave';

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
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ((int) $this->from()->diffInDays($this->to()) + 1 > StaffLeaveService::MAX_DAYS) {
                $validator->errors()->add('end_date', 'Rentang cuti maksimal '.StaffLeaveService::MAX_DAYS.' hari sekali input.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'Tanggal mulai cuti wajib diisi.',
            'start_date.after_or_equal' => 'Tanggal mulai cuti tidak boleh sebelum hari ini.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            '*.date_format' => 'Format tanggal tidak valid.',
            'reason.max' => 'Keterangan maksimal 255 karakter.',
        ];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('start_date'))->startOfDay();
    }

    public function to(): CarbonImmutable
    {
        return $this->filled('end_date')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('end_date'))->startOfDay()
            : $this->from();
    }
}
