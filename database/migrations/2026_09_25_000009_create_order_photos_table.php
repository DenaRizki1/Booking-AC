<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('kind', 10);
            $table->string('path');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['order_item_id', 'kind']);
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_photos');
    }
};
