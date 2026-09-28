<?php

namespace App\Http\Requests\Admin;

use App\Models\AcCapacity;
use App\Models\AcType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Input matriks: cells[ac_capacity_id][ac_type_id][min|max|hours|active].
 * Harga boleh berformat "75.000"; durasi dalam jam ("1,5" = 90 menit). Sel kosong = tidak ada harga.
 */
class UpdatePriceMatrixRequest extends FormRequest
{
    public const MAX_PRICE = 100_000_000;

    public const MAX_MINUTES = 24 * 60;

    /** @var array<int, array<int, array{price_min: int, price_max: int, est_minutes: int, is_active: bool}|null>> */
    private array $matrix = [];

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
            'cells' => ['present', 'array'],
            'cells.*' => ['array'],
            'cells.*.*' => ['array'],
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

            $capacities = AcCapacity::query()->pluck('label', 'id');
            $types = AcType::query()->pluck('name', 'id');

            foreach ((array) $this->input('cells', []) as $capacityId => $row) {
                foreach ((array) $row as $typeId => $cell) {
                    if (! $capacities->has($capacityId) || ! $types->has($typeId)) {
                        $validator->errors()->add('cells', 'Kapasitas atau tipe AC tidak dikenal. Muat ulang halaman.');

                        return;
                    }

                    $error = $this->parseCell((int) $capacityId, (int) $typeId, (array) $cell);
                    if ($error !== null) {
                        $validator->errors()->add("cells.{$capacityId}.{$typeId}", "{$capacities[$capacityId]} · {$types[$typeId]}: {$error}");
                    }
                }
            }
        }];
    }

    /**
     * @return array<int, array<int, array{price_min: int, price_max: int, est_minutes: int, is_active: bool}|null>>
     */
    public function matrix(): array
    {
        return $this->matrix;
    }

    /**
     * @param  array<string, mixed>  $cell
     * @return string|null pesan error, atau null bila valid
     */
    private function parseCell(int $capacityId, int $typeId, array $cell): ?string
    {
        $min = self::money($cell['min'] ?? null);
        $max = self::money($cell['max'] ?? null);
        $hours = trim((string) ($cell['hours'] ?? ''));

        if ($min === null && $max === null && $hours === '') {
            $this->matrix[$capacityId][$typeId] = null;

            return null;
        }

        if ($min === null) {
            return 'harga mulai wajib diisi.';
        }
        if ($max === null) {
            return 'harga sampai wajib diisi.';
        }
        if ($min > $max) {
            return 'harga mulai tidak boleh lebih besar dari harga sampai.';
        }
        if ($max > self::MAX_PRICE) {
            return 'harga terlalu besar.';
        }

        $hours = str_replace(',', '.', $hours);
        $minutes = is_numeric($hours) ? (int) round((float) $hours * 60) : 0;
        if ($minutes <= 0) {
            return 'estimasi durasi harus lebih dari 0 jam.';
        }
        if ($minutes > self::MAX_MINUTES) {
            return 'estimasi durasi maksimal 24 jam.';
        }

        $this->matrix[$capacityId][$typeId] = [
            'price_min' => $min,
            'price_max' => $max,
            'est_minutes' => $minutes,
            'is_active' => filter_var($cell['active'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        return null;
    }

    /** "75.000" / "Rp75.000" → 75000; kosong → null. */
    private static function money(mixed $value): ?int
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' || $digits === null ? null : (int) substr($digits, 0, 12);
    }
}
