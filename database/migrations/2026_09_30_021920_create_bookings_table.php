<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('console_id')->constrained('units')->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->enum('status', ['PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED'])->default('PENDING')->index();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['console_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
