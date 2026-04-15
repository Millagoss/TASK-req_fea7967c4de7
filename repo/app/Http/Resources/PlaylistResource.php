<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'publish_state' => $this->publish_state,
            'version'       => $this->getVersionString(),
            'version_major' => $this->version_major,
            'version_minor' => $this->version_minor,
            'version_patch' => $this->version_patch,
            'created_by'    => $this->created_by,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
