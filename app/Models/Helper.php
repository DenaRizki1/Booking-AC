<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Helper pendamping teknisi (tanpa akun login). Relasi, cast & factory dilengkapi di T-205.
 */
class Helper extends Model
{
    use SoftDeletes;
}
