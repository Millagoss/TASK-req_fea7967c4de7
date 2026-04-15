<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardPenaltyType extends Model
{
    use \App\Traits\Auditable;
    protected $fillable = [
        'name',
        'category',
        'severity',
        'default_points',
        'default_expiration_days',
        'is_active',
    ];

    protected $casts = [
        'default_points'          => 'integer',
        'default_expiration_days' => 'integer',
        'is_active'               => 'boolean',
    ];

    /**
     * Disciplinary records of this type.
     */
    public function records(): HasMany
    {
        return $this->hasMany(DisciplinaryRecord::class, 'type_id');
    }
}
