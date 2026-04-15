<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisciplinaryRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'type_id'             => $this->type_id,
            'type'                => $this->when(
                $this->relationLoaded('type'),
                fn () => new RewardPenaltyTypeResource($this->type)
            ),
            'subject_user_id'     => $this->subject_user_id,
            'subject'             => $this->when(
                $this->relationLoaded('subject'),
                function () {
                    return $this->subject ? [
                        'id'           => $this->subject->id,
                        'username'     => $this->subject->username,
                        'display_name' => $this->subject->display_name,
                    ] : null;
                }
            ),
            'issuer_user_id'      => $this->issuer_user_id,
            'issuer'              => $this->when(
                $this->relationLoaded('issuer'),
                function () {
                    return $this->issuer ? [
                        'id'           => $this->issuer->id,
                        'username'     => $this->issuer->username,
                        'display_name' => $this->issuer->display_name,
                    ] : null;
                }
            ),
            'evaluation_cycle_id' => $this->evaluation_cycle_id,
            'evaluation_cycle'    => $this->when(
                $this->relationLoaded('evaluationCycle'),
                fn () => $this->evaluationCycle ? new EvaluationCycleResource($this->evaluationCycle) : null
            ),
            'leader_profile_id'   => $this->leader_profile_id,
            'leader_profile'      => $this->when(
                $this->relationLoaded('leaderProfile'),
                fn () => $this->leaderProfile ? new LeaderProfileResource($this->leaderProfile) : null
            ),
            'status'              => $this->status,
            'reason'              => $this->reason,
            'points'              => $this->points,
            'issued_at'           => $this->issued_at?->toIso8601String(),
            'expires_at'          => $this->expires_at?->toIso8601String(),
            'appealed_at'         => $this->appealed_at?->toIso8601String(),
            'appeal_reason'       => $this->appeal_reason,
            'cleared_at'          => $this->cleared_at?->toIso8601String(),
            'cleared_by'          => $this->cleared_by,
            'cleared_by_user'     => $this->when(
                $this->relationLoaded('clearedByUser'),
                function () {
                    return $this->clearedByUser ? [
                        'id'           => $this->clearedByUser->id,
                        'username'     => $this->clearedByUser->username,
                        'display_name' => $this->clearedByUser->display_name,
                    ] : null;
                }
            ),
            'cleared_reason'      => $this->cleared_reason,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
