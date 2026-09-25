<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master unit AC: merk, kapasitas (PK), tipe.
     */
    public function up(): void
    {
        Schema::create('ac_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ac_capacities', function (Blueprint $table) {
            $table->id();
            $table->string('label', 20);
            $table->decimal('pk_value', 4, 2)->unique();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ac_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ac_types');
        Schema::dropIfExists('ac_capacities');
        Schema::dropIfExists('ac_brands');
    }
};
