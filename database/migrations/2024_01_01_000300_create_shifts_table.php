<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->decimal('starting_cash', 12, 2)->default(0);
            $table->decimal('system_cash_revenue', 12, 2)->default(0);
            $table->decimal('system_qris_revenue', 12, 2)->default(0);
            $table->decimal('actual_physical_cash', 12, 2)->nullable();
            $table->decimal('discrepancy', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN')->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('start_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
