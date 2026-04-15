<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BehaviorEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'event_type'       => $this->event_type,
            'target_type'      => $this->target_type,
            'target_id'        => $this->target_id,
            'payload'          => $this->payload,
            'server_timestamp' => $this->server_timestamp?->toIso8601String(),
            'request_id'       => $this->request_id,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
