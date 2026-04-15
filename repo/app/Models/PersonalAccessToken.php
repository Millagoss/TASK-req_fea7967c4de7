<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'abilities'  => 'json',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    /**
     * Override Sanctum's token finder to reject revoked tokens.
     */
    public static function findToken($token): ?static
    {
        $model = parent::findToken($token);

        if ($model === null) {
            return null;
        }

        if ($model->revoked_at !== null) {
            return null;
        }

        return $model;
    }
}
