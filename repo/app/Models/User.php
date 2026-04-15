<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use \App\Traits\Auditable;
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'password_hash',
        'display_name',
        'is_service_account',
        'service_credential_hash',
        'service_credential_rotated_at',
        'is_active',
        'locked_until',
    ];

    protected $hidden = [
        'password_hash',
        'service_credential_hash',
    ];

    protected $casts = [
        'is_service_account'            => 'boolean',
        'is_active'                     => 'boolean',
        'service_credential_rotated_at' => 'datetime',
        'locked_until'                  => 'datetime',
    ];

    /**
     * The column used for authentication (Sanctum / Auth).
     */
    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    /**
     * Laravel's Auth system looks for getAuthPassword().
     * Our column is password_hash.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function dataScopes(): HasMany
    {
        return $this->hasMany(DataScope::class);
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class, 'username', 'username');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    /**
     * Collect all permission names across all assigned roles.
     */
    public function getAllPermissions(): array
    {
        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getAllPermissions(), true);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
