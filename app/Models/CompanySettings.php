<?php

namespace App\Models;

use Database\Factories\CompanySettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Profil perusahaan + pengaturan aplikasi. Hanya 1 baris; akses lewat CompanySettings::current().
 */
class CompanySettings extends Model
{
    /** @use HasFactory<CompanySettingsFactory> */
    use HasFactory;

    public const CACHE_KEY = 'company_settings';

    protected $table = 'company_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_name',
        'phone',
        'email',
        'address',
        'lat',
        'lng',
        'open_time',
        'close_time',
        'working_days',
        'slot_interval_minutes',
        'travel_buffer_minutes',
        'free_radius_km',
        'max_radius_km',
        'fee_per_km',
        'cancel_limit_hours',
        'warranty_days',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'working_days' => 'array',
            'slot_interval_minutes' => 'integer',
            'travel_buffer_minutes' => 'integer',
            'free_radius_km' => 'decimal:2',
            'max_radius_km' => 'decimal:2',
            'fee_per_km' => 'integer',
            'cancel_limit_hours' => 'integer',
            'warranty_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Nama perusahaan untuk tampilan; aman dipakai sebelum pengaturan diisi.
     */
    public static function companyName(): string
    {
        try {
            return static::current()->company_name;
        } catch (RuntimeException) {
            return (string) config('app.name');
        }
    }

    /**
     * Baris pengaturan (di-cache; cache dihapus otomatis saat disimpan).
     */
    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->first()
                ?? throw new RuntimeException('Pengaturan perusahaan belum diisi. Jalankan `php artisan db:seed`.');
        });
    }
}
