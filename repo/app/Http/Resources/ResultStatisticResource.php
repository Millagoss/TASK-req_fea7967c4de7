<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultStatisticResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'measurement_code_id'  => $this->measurement_code_id,
            'count'                => $this->count,
            'mean'                 => $this->mean,
            'stddev'               => $this->stddev,
            'last_computed_at'     => $this->last_computed_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
