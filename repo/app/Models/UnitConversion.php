<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitConversion extends Model
{
    use \App\Traits\Auditable;
    protected $fillable = [
        'measurement_code_id',
        'from_unit',
        'to_unit',
        'factor',
        'offset',
    ];

    protected $casts = [
        'factor' => 'decimal:8',
        'offset' => 'decimal:8',
    ];

    /**
     * The measurement code this conversion belongs to.
     */
    public function measurementCode(): BelongsTo
    {
        return $this->belongsTo(MeasurementCode::class, 'measurement_code_id');
    }

    /**
     * Convert a value using this conversion's factor and offset.
     * normalized = value * factor + offset
     */
    public function convert(float $value): float
    {
        return $value * (float) $this->factor + (float) $this->offset;
    }
}
