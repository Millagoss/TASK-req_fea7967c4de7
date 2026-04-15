<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataScope extends Model
{
    protected $fillable = [
        'user_id',
        'role_id',
        'scope_type',
        'scope_value',
    ];

    protected $casts = [
        'scope_value' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
