<?php

namespace App\Models;

use Database\Factories\ServicePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    /** @use HasFactory<ServicePriceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'ac_capacity_id',
        'ac_type_id',
        'price_min',
        'price_max',
        'est_minutes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_min' => 'integer',
            'price_max' => 'integer',
            'est_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<AcCapacity, $this>
     */
    public function capacity(): BelongsTo
    {
        return $this->belongsTo(AcCapacity::class, 'ac_capacity_id');
    }

    /**
     * @return BelongsTo<AcType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AcType::class, 'ac_type_id');
    }
}
