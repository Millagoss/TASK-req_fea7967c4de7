<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'template_id'   => $this->template_id,
            'template_name' => $this->template_name,
            'is_subscribed'  => $this->is_subscribed,
        ];
    }
}
