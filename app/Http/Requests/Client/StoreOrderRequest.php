<?php

namespace App\Http\Requests\Client;

use App\Support\BookingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Langkah 2 buat order: tanggal, jam mulai, catatan keluhan.
 */
class StoreOrderRequest extends FormRequest
{
    public const BOOKING_DAYS = BookingCalendar::DAYS;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = today();

        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before:'.$today->copy()->addDays(self::BOOKING_DAYS)->toDateString()],
            'time' => ['required', 'date_format:H:i'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Pilih tanggal.',
            'date.*' => 'Tanggal harus dalam '.self::BOOKING_DAYS.' hari ke depan.',
            'time.required' => 'Pilih jam mulai.',
            'time.date_format' => 'Jam mulai tidak valid.',
            'customer_note.max' => 'Catatan maksimal 1.000 karakter.',
        ];
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->string('date').' '.$this->string('time'))->startOfMinute();
    }
}
