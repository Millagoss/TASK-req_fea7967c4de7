<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'artist'            => $this->artist,
            'duration_seconds'  => $this->duration_seconds,
            'audio_quality'     => $this->audio_quality,
            'cover_art_path'    => $this->cover_art_path,
            'cover_art_sha256'  => $this->cover_art_sha256,
            'publish_state'     => $this->publish_state,
            'version'           => $this->getVersionString(),
            'version_major'     => $this->version_major,
            'version_minor'     => $this->version_minor,
            'version_patch'     => $this->version_patch,
            'tags'              => $this->when(
                $this->relationLoaded('tags'),
                fn () => SongTagResource::collection($this->tags)
            ),
            'created_by'        => $this->created_by,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
