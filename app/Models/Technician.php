<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Profil teknisi. Relasi, cast & factory dilengkapi di T-205.
 */
class Technician extends Model
{
    use SoftDeletes;
}
