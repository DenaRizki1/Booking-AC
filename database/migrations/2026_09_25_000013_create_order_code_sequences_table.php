<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor urut kode order per bulan (ORD-YYMM-NNNN), dipakai OrderCodeGenerator.
     */
    public function up(): void
    {
        Schema::create('order_code_sequences', function (Blueprint $table) {
            $table->char('period', 4)->primary(); // YYMM
            $table->unsignedInteger('last_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_code_sequences');
    }
};
