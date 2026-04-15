<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementCodeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'code'                 => $this->code,
            'display_name'         => $this->display_name,
            'unit'                 => $this->unit,
            'value_type'           => $this->value_type,
            'reference_range_low'  => $this->reference_range_low,
            'reference_range_high' => $this->reference_range_high,
            'is_active'            => $this->is_active,
            'unit_conversions'     => $this->when(
                $this->relationLoaded('unitConversions'),
                fn () => UnitConversionResource::collection($this->unitConversions)
            ),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
