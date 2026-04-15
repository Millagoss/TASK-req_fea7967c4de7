<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitConversionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'measurement_code_id'  => $this->measurement_code_id,
            'from_unit'            => $this->from_unit,
            'to_unit'              => $this->to_unit,
            'factor'               => $this->factor,
            'offset'               => $this->offset,
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
