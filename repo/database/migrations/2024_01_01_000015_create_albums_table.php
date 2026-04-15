<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('artist', 200);
            $table->enum('publish_state', ['draft', 'published', 'unpublished'])->default('draft');
            $table->integer('version_major')->default(1);
            $table->integer('version_minor')->default(0);
            $table->integer('version_patch')->default(0);
            $table->string('cover_art_path')->nullable();
            $table->char('cover_art_sha256', 64)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['publish_state', 'updated_at']);
            $table->index(['artist', 'title']);
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'artist']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
