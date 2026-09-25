<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('order_type', 10)->default('REGULER');
            $table->unsignedBigInteger('parent_order_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('customer_address_id');
            $table->text('address_snapshot');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('distance_km', 6, 2);
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->unsignedBigInteger('helper_id')->nullable();
            $table->boolean('requires_helper')->default(false);
            $table->dateTime('scheduled_start');
            $table->dateTime('scheduled_end');
            $table->dateTime('blocked_until');
            $table->unsignedSmallInteger('total_est_minutes');
            $table->unsignedInteger('est_price_min');
            $table->unsignedInteger('est_price_max');
            $table->unsignedInteger('transport_fee')->default(0);
            $table->unsignedInteger('final_price')->nullable();
            $table->unsignedInteger('grand_total')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('technician_note')->nullable();
            $table->string('status', 20)->default('MENUNGGU');
            $table->text('status_reason')->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('departed_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('payment_method', 10)->nullable();
            $table->string('payment_proof')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->unsignedBigInteger('paid_confirmed_by')->nullable();
            $table->timestamps();

            // Index dibuat sebelum FK agar dipakai juga oleh FK (tanpa index duplikat).
            $table->index(['technician_id', 'scheduled_start'], 'orders_technician_schedule_index');
            $table->index(['helper_id', 'scheduled_start'], 'orders_helper_schedule_index');
            $table->index(['status', 'scheduled_start'], 'orders_status_schedule_index');
            $table->index(['user_id', 'created_at'], 'orders_user_created_index');
            $table->index('parent_order_id');

            $table->foreign('parent_order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('customer_address_id')->references('id')->on('customer_addresses')->restrictOnDelete();
            $table->foreign('technician_id')->references('id')->on('technicians')->restrictOnDelete();
            $table->foreign('helper_id')->references('id')->on('helpers')->restrictOnDelete();
            $table->foreign('paid_confirmed_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
