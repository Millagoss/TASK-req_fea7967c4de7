<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultStatistic extends Model
{
    protected $fillable = [
        'measurement_code_id',
        'count',
        'mean',
        'stddev',
        'last_computed_at',
    ];

    protected $casts = [
        'count'            => 'integer',
        'mean'             => 'decimal:6',
        'stddev'           => 'decimal:6',
        'last_computed_at' => 'datetime',
    ];

    /**
     * The measurement code these statistics belong to.
     */
    public function measurementCode(): BelongsTo
    {
        return $this->belongsTo(MeasurementCode::class, 'measurement_code_id');
    }
}
