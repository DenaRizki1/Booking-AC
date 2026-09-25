<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matriks harga Layanan × PK × Tipe.
     */
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ac_capacity_id')->constrained()->restrictOnDelete();
            $table->foreignId('ac_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('price_min');
            $table->unsignedInteger('price_max');
            $table->unsignedSmallInteger('est_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['service_id', 'ac_capacity_id', 'ac_type_id'], 'service_prices_combination_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE service_prices ADD CONSTRAINT service_prices_price_range_check CHECK (price_max >= price_min)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};
