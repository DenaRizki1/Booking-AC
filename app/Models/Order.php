<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use Database\Factories\OrderFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Perubahan `status` hanya lewat OrderStatusService (T-504).
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'order_type',
        'parent_order_id',
        'user_id',
        'customer_address_id',
        'address_snapshot',
        'lat',
        'lng',
        'distance_km',
        'technician_id',
        'helper_id',
        'requires_helper',
        'scheduled_start',
        'scheduled_end',
        'blocked_until',
        'total_est_minutes',
        'est_price_min',
        'est_price_max',
        'transport_fee',
        'final_price',
        'grand_total',
        'customer_note',
        'technician_note',
        'status',
        'status_reason',
        'assigned_at',
        'confirmed_at',
        'departed_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'payment_method',
        'payment_proof',
        'paid_at',
        'paid_confirmed_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'order_type' => 'REGULER',
        'status' => 'MENUNGGU',
        'requires_helper' => false,
        'transport_fee' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'distance_km' => 'decimal:2',
            'requires_helper' => 'boolean',
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'blocked_until' => 'datetime',
            'total_est_minutes' => 'integer',
            'est_price_min' => 'integer',
            'est_price_max' => 'integer',
            'transport_fee' => 'integer',
            'final_price' => 'integer',
            'grand_total' => 'integer',
            'assigned_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'departed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Order yang masih mengunci jadwal (semua status kecuali DITOLAK & DIBATALKAN).
     *
     * @param  Builder<Order>  $query
     */
    public function scopeLocksSchedule(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::scheduleLockingValues());
    }

    /**
     * Order yang rentang terkuncinya [scheduled_start, blocked_until) beririsan dengan rentang baru.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeOverlapping(Builder $query, DateTimeInterface $start, DateTimeInterface $blockedUntil): void
    {
        $query->where('scheduled_start', '<', $blockedUntil)
            ->where('blocked_until', '>', $start);
    }

    /**
     * Client pemilik order.
     *
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<CustomerAddress, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Technician, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Helper, $this>
     */
    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class)->withTrashed();
    }

    /**
     * Order asal (untuk order GARANSI).
     *
     * @return BelongsTo<Order, $this>
     */
    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    /**
     * Order garansi yang diklaim dari order ini.
     *
     * @return HasMany<Order, $this>
     */
    public function warrantyClaims(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<OrderPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(OrderPhoto::class);
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Admin yang mengonfirmasi pembayaran.
     *
     * @return BelongsTo<User, $this>
     */
    public function paidConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_confirmed_by')->withTrashed();
    }
}
