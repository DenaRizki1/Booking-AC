<?php

namespace App\Http\Requests\Client;

use App\Support\BookingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarrantyClaimRequest extends FormRequest
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
        $today = today();

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'complaint' => ['required', 'string', 'min:5', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before:'.$today->copy()->addDays(BookingCalendar::DAYS)->toDateString()],
            'time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal 1 unit yang bermasalah.',
            'items.*' => 'Pilih minimal 1 unit yang bermasalah.',
            'complaint.required' => 'Jelaskan keluhan Anda.',
            'complaint.min' => 'Keluhan terlalu singkat.',
            'complaint.max' => 'Keluhan maksimal 1.000 karakter.',
            'photo.image' => 'File harus berupa foto.',
            'photo.mimes' => 'Foto harus JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
            'date.required' => 'Pilih tanggal.',
            'date.*' => 'Tanggal harus dalam '.BookingCalendar::DAYS.' hari ke depan.',
            'time.required' => 'Pilih jam mulai.',
            'time.date_format' => 'Jam mulai tidak valid.',
        ];
    }

    /**
     * @return list<int>
     */
    public function itemIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('items', [])));
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->string('date').' '.$this->string('time'))->startOfMinute();
    }
}
