<?php

namespace App\Enums;

use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Jenis master data AC di admin (satu pola CRUD untuk ketiganya). Nilai = segmen URL.
 */
enum AcMasterKind: string
{
    case BRAND = 'merk';
    case CAPACITY = 'pk';
    case TYPE = 'tipe';

    public function label(): string
    {
        return match ($this) {
            self::BRAND => 'Merk',
            self::CAPACITY => 'Kapasitas (PK)',
            self::TYPE => 'Tipe AC',
        };
    }

    /** Kata benda untuk pesan: "Merk disimpan." */
    public function noun(): string
    {
        return match ($this) {
            self::BRAND => 'Merk',
            self::CAPACITY => 'Kapasitas',
            self::TYPE => 'Tipe AC',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BRAND => 'Pilihan merk AC saat client menambah unit.',
            self::CAPACITY => 'Baris pada matriks harga. Nilai PK dipakai untuk mengurutkan dan harus unik.',
            self::TYPE => 'Kolom pada matriks harga (mis. Standar, Low Watt, Inverter).',
        };
    }

    /**
     * @return class-string<AcBrand|AcCapacity|AcType>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::BRAND => AcBrand::class,
            self::CAPACITY => AcCapacity::class,
            self::TYPE => AcType::class,
        };
    }

    /** Kolom nama yang ditampilkan. */
    public function nameColumn(): string
    {
        return $this === self::CAPACITY ? 'label' : 'name';
    }

    /** Punya harga di matriks (ikut dihapus bila data belum pernah dipakai order). */
    public function hasPrices(): bool
    {
        return $this !== self::BRAND;
    }

    /**
     * Query terurut sesuai tampilan client.
     *
     * @return Builder<AcBrand>|Builder<AcCapacity>|Builder<AcType>
     */
    public function query(): Builder
    {
        $class = $this->modelClass();

        return $class::query()
            ->orderBy('sort_order')
            ->orderBy($this === self::CAPACITY ? 'pk_value' : 'name');
    }

    public function find(int|string $id): AcBrand|AcCapacity|AcType
    {
        $class = $this->modelClass();

        return $class::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(?Model $ignore = null): array
    {
        $table = (new ($this->modelClass()))->getTable();

        return match ($this) {
            self::BRAND => [
                'name' => ['required', 'string', 'max:60', Rule::unique($table, 'name')->ignore($ignore)],
            ],
            self::CAPACITY => [
                'label' => ['required', 'string', 'max:20', Rule::unique($table, 'label')->ignore($ignore)],
                'pk_value' => ['required', 'numeric', 'min:0.25', 'max:99.99', 'decimal:0,2', Rule::unique($table, 'pk_value')->ignore($ignore)],
            ],
            self::TYPE => [
                'name' => ['required', 'string', 'max:40', Rule::unique($table, 'name')->ignore($ignore)],
            ],
        } + ['is_active' => ['nullable', 'boolean']];
    }
}
