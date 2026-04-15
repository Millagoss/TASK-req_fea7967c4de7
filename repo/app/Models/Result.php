<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    use \App\Traits\Auditable;
    protected $fillable = [
        'subject_id',
        'measurement_code_id',
        'value_raw',
        'value_numeric',
        'value_text',
        'unit_input',
        'unit_normalized',
        'observed_at',
        'source',
        'is_outlier',
        'z_score',
        'outlier_threshold',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
        'batch_id',
        'created_by',
    ];

    protected $casts = [
        'value_numeric'     => 'decimal:6',
        'z_score'           => 'decimal:4',
        'outlier_threshold' => 'decimal:2',
        'is_outlier'        => 'boolean',
        'observed_at'       => 'datetime',
        'reviewed_at'       => 'datetime',
    ];

    /**
     * The subject this result belongs to.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * The measurement code for this result.
     */
    public function measurementCode(): BelongsTo
    {
        return $this->belongsTo(MeasurementCode::class, 'measurement_code_id');
    }

    /**
     * The user who reviewed this result.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The user who created this result.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
