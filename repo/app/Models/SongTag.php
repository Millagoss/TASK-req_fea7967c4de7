<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongTag extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'song_id',
        'tag',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
