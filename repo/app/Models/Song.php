<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    use \App\Traits\Auditable;
    use HasFactory;

    protected $fillable = [
        'title',
        'artist',
        'duration_seconds',
        'audio_quality',
        'cover_art_path',
        'cover_art_sha256',
        'publish_state',
        'version_major',
        'version_minor',
        'version_patch',
        'created_by',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'version_major'    => 'integer',
        'version_minor'    => 'integer',
        'version_patch'    => 'integer',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function tags(): HasMany
    {
        return $this->hasMany(SongTag::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'album_songs')
            ->using(AlbumSong::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_songs')
            ->using(PlaylistSong::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function getVersionString(): string
    {
        return "{$this->version_major}.{$this->version_minor}.{$this->version_patch}";
    }
}
