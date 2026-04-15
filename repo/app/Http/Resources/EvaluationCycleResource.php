<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'name'                      => $this->name,
            'start_date'                => $this->start_date?->toDateString(),
            'end_date'                  => $this->end_date?->toDateString(),
            'status'                    => $this->status,
            'created_by'                => $this->created_by,
            'disciplinary_records_count' => $this->when(
                $this->disciplinary_records_count !== null,
                $this->disciplinary_records_count
            ),
            'created_at'                => $this->created_at?->toIso8601String(),
            'updated_at'                => $this->updated_at?->toIso8601String(),
        ];
    }
}
