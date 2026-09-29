<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit_type', 50)->nullable()->index();
            $table->unsignedInteger('duration_minutes');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_packages');
    }
};
