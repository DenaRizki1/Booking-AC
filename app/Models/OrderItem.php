<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit AC dalam order. Nama layanan, label unit, harga & durasi adalah snapshot.
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'claimed_item_id',
        'service_id',
        'service_price_id',
        'ac_brand_id',
        'ac_capacity_id',
        'ac_type_id',
        'service_name',
        'unit_label',
        'est_price_min',
        'est_price_max',
        'est_minutes',
        'final_price',
        'price_note',
        'work_note',
        'warranty_days',
        'warranty_until',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'est_price_min' => 'integer',
            'est_price_max' => 'integer',
            'est_minutes' => 'integer',
            'final_price' => 'integer',
            'warranty_days' => 'integer',
            'warranty_until' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Unit asal yang diklaim (pada order GARANSI).
     *
     * @return BelongsTo<OrderItem, $this>
     */
    public function claimedItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'claimed_item_id');
    }

    /**
     * Klaim garansi yang merujuk unit ini.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'claimed_item_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ServicePrice, $this>
     */
    public function servicePrice(): BelongsTo
    {
        return $this->belongsTo(ServicePrice::class);
    }

    /**
     * @return BelongsTo<AcBrand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(AcBrand::class, 'ac_brand_id');
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

    /**
     * @return HasMany<OrderPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(OrderPhoto::class);
    }
}
