<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardPenaltyTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'category'                => $this->category,
            'severity'                => $this->severity,
            'default_points'          => $this->default_points,
            'default_expiration_days' => $this->default_expiration_days,
            'is_active'               => $this->is_active,
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
