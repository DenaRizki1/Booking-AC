<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unit AC dalam order (harga, durasi & nama di-snapshot saat order dibuat).
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claimed_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_price_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ac_brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('ac_capacity_id')->constrained()->restrictOnDelete();
            $table->foreignId('ac_type_id')->constrained()->restrictOnDelete();
            $table->string('service_name', 80);
            $table->string('unit_label', 120);
            $table->unsignedInteger('est_price_min');
            $table->unsignedInteger('est_price_max');
            $table->unsignedSmallInteger('est_minutes');
            $table->unsignedInteger('final_price')->nullable();
            $table->text('price_note')->nullable();
            $table->text('work_note')->nullable();
            $table->unsignedSmallInteger('warranty_days');
            $table->date('warranty_until')->nullable()->index();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
