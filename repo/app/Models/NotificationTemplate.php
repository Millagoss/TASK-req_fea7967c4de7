<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    use \App\Traits\Auditable;
    /**
     * The table associated with the model.
     */
    protected $table = 'notification_templates';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'subject',
        'body',
        'variables',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'variables' => 'array',
    ];

    /**
     * Get the user who created this template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the subscriptions for this template.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(NotificationSubscription::class, 'template_id');
    }
}
