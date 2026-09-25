<?php

namespace App\Providers;

use App\Models\Helper;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Alias tipe polymorphic yang disimpan di database (staff_leaves.staff_type,
        // notifications.notifiable_type). Model lain tidak boleh dipakai di relasi morph.
        Relation::enforceMorphMap([
            'user' => User::class,
            'technician' => Technician::class,
            'helper' => Helper::class,
        ]);
    }
}
