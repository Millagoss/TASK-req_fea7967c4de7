<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('album_songs', function (Blueprint $table) {
            $table->foreignId('album_id')->constrained('albums')->cascadeOnDelete();
            $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();
            $table->integer('position')->unsigned();

            $table->primary(['album_id', 'song_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_songs');
    }
};
