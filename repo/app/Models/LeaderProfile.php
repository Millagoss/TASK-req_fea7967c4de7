<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'department',
        'campus',
    ];

    /**
     * The user this leader profile belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Disciplinary records associated with this leader profile.
     */
    public function disciplinaryRecords(): HasMany
    {
        return $this->hasMany(DisciplinaryRecord::class, 'leader_profile_id');
    }
}
