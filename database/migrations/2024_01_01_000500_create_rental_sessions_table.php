<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->boolean('is_free_play')->default(false);
            $table->string('package_name')->nullable();
            $table->decimal('rental_fee', 10, 2)->default(0);
            $table->enum('status', ['RUNNING', 'COMPLETED', 'CANCELLED'])->default('RUNNING')->index();
            $table->enum('payment_method', ['CASH', 'QRIS'])->nullable();
            $table->unsignedInteger('extra_minutes')->default(0);
            $table->unsignedInteger('extra_charges_total')->default(0);
            $table->timestamp('time_up_notified_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'status']);
            $table->index('start_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_sessions');
    }
};
