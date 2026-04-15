<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('display_name', 200);
            $table->string('unit', 50);
            $table->enum('value_type', ['numeric', 'text', 'coded']);
            $table->decimal('reference_range_low', 12, 4)->nullable();
            $table->decimal('reference_range_high', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_codes');
    }
};
