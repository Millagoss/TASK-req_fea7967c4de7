<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_penalty_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->enum('category', ['reward', 'penalty']);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->integer('default_points');
            $table->integer('default_expiration_days')->default(365);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_penalty_types');
    }
};
