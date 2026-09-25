<?php

namespace App\Http\Requests\Client;

use App\Exceptions\UnavailableUnitsException;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\OrderEstimator;
use App\Services\TransportFeeCalculator;
use App\Support\OrderDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Langkah 1 buat order: alamat + daftar unit AC.
 */
class StoreOrderUnitsRequest extends FormRequest
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
        /** @var User $client */
        $client = $this->user();
        $active = fn (string $table) => Rule::exists($table, 'id')->where('is_active', true);

        return [
            'customer_address_id' => ['required', 'integer', Rule::exists('customer_addresses', 'id')->where('user_id', $client->id)->whereNull('deleted_at')],
            'units' => ['required', 'array', 'min:1', 'max:'.OrderDraft::MAX_UNITS],
            'units.*.ac_brand_id' => ['required', 'integer', $active('ac_brands')],
            'units.*.ac_capacity_id' => ['required', 'integer', $active('ac_capacities')],
            'units.*.ac_type_id' => ['required', 'integer', $active('ac_types')],
            'units.*.service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_address_id.required' => 'Pilih alamat terlebih dahulu.',
            'customer_address_id.exists' => 'Alamat tidak ditemukan.',
            'units.required' => 'Tambahkan minimal 1 unit AC.',
            'units.min' => 'Tambahkan minimal 1 unit AC.',
            'units.max' => 'Maksimal '.OrderDraft::MAX_UNITS.' unit AC per order.',
            'units.*.ac_brand_id.*' => 'Pilih merk AC.',
            'units.*.ac_capacity_id.*' => 'Pilih kapasitas (PK).',
            'units.*.ac_type_id.*' => 'Pilih tipe AC.',
            'units.*.service_id.*' => 'Pilih layanan.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $address = CustomerAddress::findOrFail($this->integer('customer_address_id'));
                if (! app(TransportFeeCalculator::class)->isWithinRange((float) $address->distance_km)) {
                    $validator->errors()->add('customer_address_id', 'Alamat ini di luar jangkauan layanan. Pilih alamat lain.');
                }

                try {
                    app(OrderEstimator::class)->estimate($this->units());
                } catch (UnavailableUnitsException $e) {
                    foreach ($e->unitErrors as $index => $message) {
                        $validator->errors()->add("units.{$index}.service_id", $message);
                    }
                }
            },
        ];
    }

    /**
     * @return list<array{ac_brand_id: int, ac_capacity_id: int, ac_type_id: int, service_id: int}>
     */
    public function units(): array
    {
        return array_values(array_map(fn (array $unit) => [
            'ac_brand_id' => (int) $unit['ac_brand_id'],
            'ac_capacity_id' => (int) $unit['ac_capacity_id'],
            'ac_type_id' => (int) $unit['ac_type_id'],
            'service_id' => (int) $unit['service_id'],
        ], (array) $this->input('units', [])));
    }
}
