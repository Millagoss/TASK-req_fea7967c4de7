<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSubscription extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'notification_subscriptions';

    /**
     * No created_at column — only updated_at.
     */
    const CREATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'template_id',
        'is_subscribed',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_subscribed' => 'boolean',
    ];

    /**
     * Get the user for this subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template for this subscription.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }
}
