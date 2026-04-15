<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('scope_type', ['campus', 'org', 'subject', 'time_range']);
            $table->json('scope_value');
            $table->timestamps();

            $table->index(['user_id', 'scope_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_scopes');
    }
};
