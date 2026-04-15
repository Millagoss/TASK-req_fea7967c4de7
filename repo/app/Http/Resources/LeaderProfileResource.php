<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaderProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->user_id,
            'user'       => $this->when(
                $this->relationLoaded('user'),
                function () {
                    return [
                        'id'           => $this->user->id,
                        'username'     => $this->user->username,
                        'display_name' => $this->user->display_name,
                    ];
                }
            ),
            'title'      => $this->title,
            'department' => $this->department,
            'campus'     => $this->campus,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
