<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_code_id')->constrained('measurement_codes')->cascadeOnDelete();
            $table->string('from_unit', 50);
            $table->string('to_unit', 50);
            $table->decimal('factor', 18, 8);
            $table->decimal('offset', 18, 8)->default(0);
            $table->timestamps();

            $table->unique(['measurement_code_id', 'from_unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');
    }
};
