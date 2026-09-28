<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    /** Ikon Lucide yang bisa dipilih admin untuk layanan. */
    public const ICONS = [
        'droplets', 'wrench', 'drill', 'thermometer-snowflake', 'air-vent', 'fan', 'wind',
        'snowflake', 'spray-can', 'hammer', 'plug-zap', 'search-check', 'shield-check', 'settings-2',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'requires_helper',
        'warranty_days',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_helper' => 'boolean',
            'warranty_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Matriks harga PK × Tipe untuk layanan ini.
     *
     * @return HasMany<ServicePrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Slug unik dari nama (termasuk layanan yang sudah dihapus): cuci-ac, cuci-ac-2, …
     */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'layanan';
        $slug = $base;

        for ($suffix = 2; static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
