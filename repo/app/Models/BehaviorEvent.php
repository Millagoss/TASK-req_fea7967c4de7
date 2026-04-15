<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehaviorEvent extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'behavior_events';

    /**
     * Immutable model — no updated_at column.
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'event_type',
        'target_type',
        'target_id',
        'payload',
        'server_timestamp',
        'request_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'payload'          => 'array',
        'server_timestamp' => 'datetime',
        'created_at'       => 'datetime',
    ];

    /**
     * Get the user that owns the behavior event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
