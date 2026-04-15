<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryRecord extends Model
{
    use \App\Traits\Auditable;
    protected $fillable = [
        'type_id',
        'subject_user_id',
        'issuer_user_id',
        'evaluation_cycle_id',
        'leader_profile_id',
        'status',
        'reason',
        'points',
        'issued_at',
        'expires_at',
        'appealed_at',
        'appeal_reason',
        'cleared_at',
        'cleared_by',
        'cleared_reason',
    ];

    protected $casts = [
        'points'      => 'integer',
        'issued_at'   => 'datetime',
        'expires_at'  => 'datetime',
        'appealed_at' => 'datetime',
        'cleared_at'  => 'datetime',
    ];

    /**
     * The reward/penalty type of this record.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(RewardPenaltyType::class, 'type_id');
    }

    /**
     * The user this record is about.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /**
     * The user who issued this record.
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_user_id');
    }

    /**
     * The evaluation cycle this record belongs to.
     */
    public function evaluationCycle(): BelongsTo
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }

    /**
     * The leader profile associated with this record.
     */
    public function leaderProfile(): BelongsTo
    {
        return $this->belongsTo(LeaderProfile::class, 'leader_profile_id');
    }

    /**
     * The user who cleared this record.
     */
    public function clearedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }
}
