<?php

namespace App\Http\Requests\Teknisi;

use App\Enums\PhotoKind;
use App\Models\Order;
use App\Services\OrderReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Foto laporan unit: hanya gambar, maks. 5 MB per file (diperkecil lagi di server).
 */
class StoreReportPhotosRequest extends FormRequest
{
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
        return [
            'kind' => ['required', Rule::in([PhotoKind::SEBELUM->value, PhotoKind::SESUDAH->value])],
            'photos' => ['required', 'array', 'max:'.OrderReportService::MAX_PHOTOS_PER_KIND],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.required' => 'Pilih foto terlebih dahulu.',
            'photos.max' => 'Maksimal '.OrderReportService::MAX_PHOTOS_PER_KIND.' foto sekali unggah.',
            'photos.*.image' => 'File harus berupa foto.',
            'photos.*.mimes' => 'Foto harus JPG, PNG, atau WEBP.',
            'photos.*.max' => 'Ukuran foto maksimal 5 MB.',
            'kind.*' => 'Jenis foto tidak valid.',
        ];
    }

    public function kind(): PhotoKind
    {
        return PhotoKind::from((string) $this->validated('kind'));
    }
}
