<?php

namespace App\Providers;

use App\Models\Helper;
use App\Models\Technician;
use App\Models\User;
use App\View\Composers\AdminLayoutComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
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
        // URL resource berbahasa Indonesia: /alamat/tambah, /alamat/{id}/ubah.
        Route::resourceVerbs(['create' => 'tambah', 'edit' => 'ubah']);

        // Alias tipe polymorphic yang disimpan di database (staff_leaves.staff_type,
        // notifications.notifiable_type). Model lain tidak boleh dipakai di relasi morph.
        Relation::enforceMorphMap([
            'user' => User::class,
            'technician' => Technician::class,
            'helper' => Helper::class,
        ]);

        View::composer('components.layouts.admin', AdminLayoutComposer::class);

        // Deteksi N+1: relasi yang belum di-eager-load melempar exception di lokal & test.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
