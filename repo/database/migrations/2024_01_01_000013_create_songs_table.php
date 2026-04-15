<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('artist', 200);
            $table->integer('duration_seconds')->unsigned();
            $table->enum('audio_quality', ['MP3_320', 'FLAC_16_44', 'FLAC_24_96']);
            $table->string('cover_art_path')->nullable();
            $table->char('cover_art_sha256', 64)->nullable();
            $table->enum('publish_state', ['draft', 'published', 'unpublished'])->default('draft');
            $table->integer('version_major')->default(1);
            $table->integer('version_minor')->default(0);
            $table->integer('version_patch')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['publish_state', 'updated_at']);
            $table->index(['artist', 'title']);
            $table->fullText(['title', 'artist']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
