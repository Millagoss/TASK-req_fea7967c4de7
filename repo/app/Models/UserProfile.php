<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'interest_tags',
        'preference_vector',
        'last_computed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'interest_tags'     => 'array',
        'preference_vector' => 'array',
        'last_computed_at'  => 'datetime',
    ];

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
