<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'artist'           => $this->artist,
            'duration_seconds' => $this->duration_seconds,
            'audio_quality'    => $this->audio_quality,
            'cover_art_path'   => $this->cover_art_path,
            'publish_state'    => $this->publish_state,
            'tags'             => $this->when(
                $this->relationLoaded('tags'),
                fn () => SongTagResource::collection($this->tags)
            ),
            'score'            => $this->recommendation_score ?? null,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
