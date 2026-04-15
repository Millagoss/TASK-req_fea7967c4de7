<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlist_songs', function (Blueprint $table) {
            $table->foreignId('playlist_id')->constrained('playlists')->cascadeOnDelete();
            $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();
            $table->integer('position')->unsigned();

            $table->primary(['playlist_id', 'song_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_songs');
    }
};
