<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePriceMatrixRequest;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\CompanySettings;
use App\Models\Service;
use App\Services\PriceMatrixService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Tab "Matriks harga": grid PK × Tipe untuk satu layanan.
 */
class ServicePriceController extends Controller
{
    public function edit(Service $service): View
    {
        $capacities = AcCapacity::query()->orderBy('sort_order')->orderBy('pk_value')->get();
        $types = AcType::query()->orderBy('sort_order')->orderBy('name')->get();
        $prices = $service->prices()->get()->keyBy(fn ($price) => $price->ac_capacity_id.'-'.$price->ac_type_id);
        $old = old('cells');

        // PK/tipe baru otomatis tampil sebagai sel kosong.
        $cells = [];
        foreach ($capacities as $capacity) {
            foreach ($types as $type) {
                $price = $prices->get($capacity->id.'-'.$type->id);
                $cells[$capacity->id.'-'.$type->id] = is_array($old)
                    ? [
                        'min' => (string) ($old[$capacity->id][$type->id]['min'] ?? ''),
                        'max' => (string) ($old[$capacity->id][$type->id]['max'] ?? ''),
                        'hours' => (string) ($old[$capacity->id][$type->id]['hours'] ?? ''),
                        'active' => filter_var($old[$capacity->id][$type->id]['active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ]
                    : [
                        'min' => $price ? number_format($price->price_min, 0, ',', '.') : '',
                        'max' => $price ? number_format($price->price_max, 0, ',', '.') : '',
                        'hours' => $price ? self::hours($price->est_minutes) : '',
                        'active' => $price ? $price->is_active : true,
                    ];
            }
        }

        return view('admin.services.prices', [
            'service' => $service,
            'capacities' => $capacities,
            'types' => $types,
            'cells' => $cells,
            'defaultWarranty' => CompanySettings::current()->warranty_days,
        ]);
    }

    public function update(UpdatePriceMatrixRequest $request, Service $service, PriceMatrixService $matrix): RedirectResponse
    {
        $result = $matrix->save($service, $request->matrix());

        $message = "Matriks harga disimpan ({$result['saved']} kombinasi berharga".($result['removed'] ? ", {$result['removed']} dikosongkan" : '').').';

        return to_route('admin.services.prices.edit', $service)->with('success', $message);
    }

    /** 90 → "1,5" */
    private static function hours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 2, ',', ''), '0'), ',');
    }
}
