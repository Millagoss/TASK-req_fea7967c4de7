<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('event_type', ['browse', 'search', 'click', 'favorite', 'rate', 'comment']);
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id');
            $table->json('payload')->nullable();
            $table->timestamp('server_timestamp', 3); // millisecond precision
            $table->char('request_id', 36)->nullable();
            $table->timestamp('created_at')->nullable();

            // User timeline index
            $table->index(['user_id', 'created_at']);
            // Dedup lookups index
            $table->index(['user_id', 'event_type', 'target_id', 'server_timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_events');
    }
};
