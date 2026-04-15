<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PlaylistSong extends Pivot
{
    public $timestamps = false;

    protected $fillable = [
        'playlist_id',
        'song_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];
}
