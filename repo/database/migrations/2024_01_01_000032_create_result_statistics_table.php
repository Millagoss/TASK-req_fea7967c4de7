<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_code_id')->unique()->constrained('measurement_codes');
            $table->unsignedInteger('count')->default(0);
            $table->decimal('mean', 18, 6)->nullable();
            $table->decimal('stddev', 18, 6)->nullable();
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_statistics');
    }
};
