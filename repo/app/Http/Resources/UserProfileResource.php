<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id'           => $this->user_id,
            'interest_tags'     => $this->interest_tags,
            'preference_vector' => $this->preference_vector,
            'last_computed_at'  => $this->last_computed_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
