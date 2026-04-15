<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('publish_state', ['draft', 'published', 'unpublished'])->default('draft');
            $table->integer('version_major')->default(1);
            $table->integer('version_minor')->default(0);
            $table->integer('version_patch')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['publish_state', 'updated_at']);
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'description']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlists');
    }
};
