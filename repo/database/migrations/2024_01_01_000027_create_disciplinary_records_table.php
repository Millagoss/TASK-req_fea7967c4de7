<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('reward_penalty_types');
            $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issuer_user_id')->constrained('users');
            $table->foreignId('evaluation_cycle_id')->nullable()->constrained('evaluation_cycles')->nullOnDelete();
            $table->foreignId('leader_profile_id')->nullable()->constrained('leader_profiles')->nullOnDelete();
            $table->enum('status', ['active', 'appealed', 'cleared'])->default('active');
            $table->text('reason');
            $table->integer('points');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('appealed_at')->nullable();
            $table->text('appeal_reason')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cleared_reason')->nullable();
            $table->timestamps();

            $table->index(['subject_user_id', 'status']);
            $table->index(['evaluation_cycle_id']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_records');
    }
};
