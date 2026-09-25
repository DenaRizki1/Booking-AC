<?php

namespace App\Http\Requests\Client;

use App\Models\CompanySettings;
use App\Services\DistanceService;
use App\Services\TransportFeeCalculator;
use App\Support\Format;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Simpan alamat client. Jarak ke kantor dihitung ulang di server; di luar radius maksimal ditolak.
 */
class SaveAddressRequest extends FormRequest
{
    private ?float $distanceKm = null;

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
            'label' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:1000'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Label alamat wajib diisi.',
            'label.max' => 'Label maksimal 50 karakter.',
            'address.required' => 'Detail alamat wajib diisi.',
            'address.max' => 'Detail alamat terlalu panjang.',
            'landmark.max' => 'Patokan maksimal 255 karakter.',
            'lat.required' => 'Pilih titik lokasi di peta.',
            'lng.required' => 'Pilih titik lokasi di peta.',
            'lat.*' => 'Titik lokasi tidak valid.',
            'lng.*' => 'Titik lokasi tidak valid.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['lat', 'lng'])) {
                    return;
                }

                $settings = CompanySettings::current();
                $this->distanceKm = app(DistanceService::class)->fromOffice((float) $this->input('lat'), (float) $this->input('lng'), $settings);

                if (! app(TransportFeeCalculator::class)->isWithinRange($this->distanceKm, $settings)) {
                    $validator->errors()->add('lat', sprintf(
                        'Alamat di luar jangkauan layanan (%s dari kantor, maksimal %s).',
                        Format::km($this->distanceKm),
                        Format::km((float) $settings->max_radius_km),
                    ));
                }
            },
        ];
    }

    /**
     * @return array{label: string, address: string, landmark: ?string, lat: float, lng: float, distance_km: float}
     */
    public function addressData(): array
    {
        return [
            'label' => trim($this->string('label')->toString()),
            'address' => trim($this->string('address')->toString()),
            'landmark' => $this->filled('landmark') ? trim($this->string('landmark')->toString()) : null,
            'lat' => round((float) $this->input('lat'), 7),
            'lng' => round((float) $this->input('lng'), 7),
            'distance_km' => (float) $this->distanceKm,
        ];
    }
}
