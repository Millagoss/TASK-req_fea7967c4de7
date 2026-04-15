<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'subject_id'           => $this->subject_id,
            'subject'              => $this->when(
                $this->relationLoaded('subject'),
                fn () => $this->subject ? new SubjectResource($this->subject) : null
            ),
            'measurement_code_id'  => $this->measurement_code_id,
            'measurement_code'     => $this->when(
                $this->relationLoaded('measurementCode'),
                fn () => $this->measurementCode ? new MeasurementCodeResource($this->measurementCode) : null
            ),
            'value_raw'            => $this->value_raw,
            'value_numeric'        => $this->value_numeric,
            'value_text'           => $this->value_text,
            'unit_input'           => $this->unit_input,
            'unit_normalized'      => $this->unit_normalized,
            'observed_at'          => $this->observed_at?->toIso8601String(),
            'source'               => $this->source,
            'is_outlier'           => $this->is_outlier,
            'z_score'              => $this->z_score,
            'outlier_threshold'    => $this->outlier_threshold,
            'review_status'        => $this->review_status,
            'reviewed_by'          => $this->reviewed_by,
            'reviewer'             => $this->when(
                $this->relationLoaded('reviewer'),
                function () {
                    return $this->reviewer ? [
                        'id'           => $this->reviewer->id,
                        'username'     => $this->reviewer->username,
                        'display_name' => $this->reviewer->display_name,
                    ] : null;
                }
            ),
            'reviewed_at'          => $this->reviewed_at?->toIso8601String(),
            'review_comment'       => $this->review_comment,
            'batch_id'             => $this->batch_id,
            'created_by'           => $this->created_by,
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
