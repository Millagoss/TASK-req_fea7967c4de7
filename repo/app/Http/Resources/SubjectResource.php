<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{
    public function toArray($request): array
    {
        $canViewPii = $request->user() && $request->user()->hasPermission('subjects.view_pii');

        return [
            'id'         => $this->id,
            'identifier' => $canViewPii ? $this->identifier : '***' . substr($this->identifier, -4),
            'name'       => $canViewPii ? $this->name : '***',
            'metadata'   => $this->metadata,
            'campus'     => $this->campus,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
