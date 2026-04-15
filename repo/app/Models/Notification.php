<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'notifications';

    /**
     * Immutable model — no updated_at column.
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'template_id',
        'recipient_id',
        'subject_rendered',
        'body_rendered',
        'variables_used',
        'batch_id',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'variables_used' => 'array',
        'read_at'        => 'datetime',
        'created_at'     => 'datetime',
    ];

    /**
     * Get the template for this notification.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /**
     * Get the recipient user.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
