<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tim: teknisi (punya akun users), helper (tanpa akun), cuti keduanya.
     */
    public function up(): void
    {
        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('helpers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Polymorphic: staff_type = 'technician' | 'helper' (lihat morph map di AppServiceProvider).
        Schema::create('staff_leaves', function (Blueprint $table) {
            $table->id();
            $table->string('staff_type', 191);
            $table->unsignedBigInteger('staff_id');
            $table->date('leave_date');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['staff_type', 'staff_id', 'leave_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leaves');
        Schema::dropIfExists('helpers');
        Schema::dropIfExists('technicians');
    }
};
