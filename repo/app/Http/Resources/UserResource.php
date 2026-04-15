<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                            => $this->id,
            'username'                      => $this->username,
            'display_name'                  => $this->display_name,
            'is_service_account'            => $this->is_service_account,
            'is_active'                     => $this->is_active,
            'locked_until'                  => $this->locked_until?->toIso8601String(),
            'service_credential_rotated_at' => $this->service_credential_rotated_at?->toIso8601String(),
            'roles'                         => $this->when(
                $this->relationLoaded('roles'),
                fn () => RoleResource::collection($this->roles)
            ),
            'permissions'                   => $this->when(
                $this->relationLoaded('roles'),
                fn () => $this->getAllPermissions()
            ),
            'created_at'                    => $this->created_at?->toIso8601String(),
            'updated_at'                    => $this->updated_at?->toIso8601String(),
        ];
    }
}
