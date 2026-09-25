<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil perusahaan + pengaturan aplikasi (hanya 1 baris).
     */
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('address');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->time('open_time')->default('08:00:00');
            $table->time('close_time')->default('18:00:00');
            $table->json('working_days')->default(new Expression('(JSON_ARRAY(1, 2, 3, 4, 5, 6))'));
            $table->unsignedSmallInteger('slot_interval_minutes')->default(60);
            $table->unsignedSmallInteger('travel_buffer_minutes')->default(30);
            $table->decimal('free_radius_km', 6, 2)->default(5);
            $table->decimal('max_radius_km', 6, 2)->default(25);
            $table->unsignedInteger('fee_per_km')->default(3000);
            $table->unsignedSmallInteger('cancel_limit_hours')->default(3);
            $table->unsignedSmallInteger('warranty_days')->default(7);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('bank_account_name', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
