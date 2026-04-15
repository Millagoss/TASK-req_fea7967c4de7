<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisciplinaryStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group'        => $this->resource['group'],
            'total_points' => (int) $this->resource['total_points'],
            'record_count' => (int) $this->resource['record_count'],
        ];
    }
}
