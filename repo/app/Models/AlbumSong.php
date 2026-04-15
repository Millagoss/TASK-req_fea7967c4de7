<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AlbumSong extends Pivot
{
    public $timestamps = false;

    protected $fillable = [
        'album_id',
        'song_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];
}
