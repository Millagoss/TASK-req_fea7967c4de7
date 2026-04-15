<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeasurementCode extends Model
{
    use \App\Traits\Auditable;
    protected $fillable = [
        'code',
        'display_name',
        'unit',
        'value_type',
        'reference_range_low',
        'reference_range_high',
        'is_active',
    ];

    protected $casts = [
        'reference_range_low'  => 'decimal:4',
        'reference_range_high' => 'decimal:4',
        'is_active'            => 'boolean',
    ];

    /**
     * Unit conversions defined for this measurement code.
     */
    public function unitConversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'measurement_code_id');
    }

    /**
     * Results recorded for this measurement code.
     */
    public function results(): HasMany
    {
        return $this->hasMany(Result::class, 'measurement_code_id');
    }

    /**
     * Computed statistics for this measurement code.
     */
    public function statistics(): HasOne
    {
        return $this->hasOne(ResultStatistic::class, 'measurement_code_id');
    }
}
