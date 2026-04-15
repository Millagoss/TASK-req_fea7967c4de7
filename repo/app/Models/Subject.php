<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use \App\Traits\Auditable;
    protected $fillable = [
        'identifier',
        'name',
        'metadata',
        'campus',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Results recorded for this subject.
     */
    public function results(): HasMany
    {
        return $this->hasMany(Result::class, 'subject_id');
    }
}
