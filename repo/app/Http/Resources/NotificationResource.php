<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'template_id'      => $this->template_id,
            'subject_rendered' => $this->subject_rendered,
            'body_rendered'    => $this->body_rendered,
            'variables_used'   => $this->variables_used,
            'batch_id'         => $this->batch_id,
            'read_at'          => $this->read_at?->toIso8601String(),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
