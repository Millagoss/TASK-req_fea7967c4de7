<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * GET /api/v1/admin/roles
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->orderBy('id')->get();

        return response()->json(['data' => RoleResource::collection($roles)]);
    }

    /**
     * POST /api/v1/admin/roles
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        $role = Role::create([
            'name'        => $request->input('name'),
            'description' => $request->input('description', ''),
        ]);

        if ($request->filled('permissions')) {
            $permIds = Permission::whereIn('name', $request->input('permissions'))->pluck('id');
            $role->permissions()->sync($permIds);
        }

        $role->load('permissions');

        return response()->json(new RoleResource($role), 201);
    }

    /**
     * PUT /api/v1/admin/roles/{id}
     */
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $role->update(array_filter([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
        ], fn ($v) => $v !== null));

        $role->load('permissions');

        return response()->json(new RoleResource($role));
    }

    /**
     * POST /api/v1/admin/roles/{id}/permissions
     */
    public function assignPermissions(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role    = Role::findOrFail($id);
        $permIds = Permission::whereIn('name', $request->input('permissions'))->pluck('id');
        $role->permissions()->syncWithoutDetaching($permIds);
        $role->load('permissions');

        return response()->json(new RoleResource($role));
    }

    /**
     * GET /api/v1/admin/permissions
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get();

        return response()->json(['data' => PermissionResource::collection($permissions)]);
    }
}
