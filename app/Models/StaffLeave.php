<?php

namespace App\Models;

use Database\Factories\StaffLeaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Cuti teknisi / helper (1 baris per hari).
 */
class StaffLeave extends Model
{
    /** @use HasFactory<StaffLeaveFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_type',
        'staff_id',
        'leave_date',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function staff(): MorphTo
    {
        return $this->morphTo();
    }
}
