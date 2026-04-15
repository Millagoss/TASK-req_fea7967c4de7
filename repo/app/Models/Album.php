<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Album extends Model
{
    use \App\Traits\Auditable;
    use HasFactory;

    protected $fillable = [
        'title',
        'artist',
        'publish_state',
        'version_major',
        'version_minor',
        'version_patch',
        'cover_art_path',
        'cover_art_sha256',
        'created_by',
    ];

    protected $casts = [
        'version_major' => 'integer',
        'version_minor' => 'integer',
        'version_patch' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'album_songs')
            ->using(AlbumSong::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function getVersionString(): string
    {
        return "{$this->version_major}.{$this->version_minor}.{$this->version_patch}";
    }
}
