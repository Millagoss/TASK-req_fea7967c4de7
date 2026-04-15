<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('measurement_code_id')->constrained('measurement_codes');
            $table->string('value_raw', 255);
            $table->decimal('value_numeric', 18, 6)->nullable();
            $table->string('value_text', 255)->nullable();
            $table->string('unit_input', 50);
            $table->string('unit_normalized', 50);
            $table->timestamp('observed_at');
            $table->enum('source', ['manual', 'csv_import', 'rest_integration']);
            $table->boolean('is_outlier')->default(false);
            $table->decimal('z_score', 8, 4)->nullable();
            $table->decimal('outlier_threshold', 4, 2)->default(3.00);
            $table->enum('review_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->char('batch_id', 36)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['subject_id', 'measurement_code_id', 'observed_at']);
            $table->index(['measurement_code_id', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
