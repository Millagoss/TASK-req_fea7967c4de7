<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * GET /api/v1/admin/users
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles.permissions')
            ->orderBy('id')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/users
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = User::create([
            'username'     => $request->input('username'),
            'password_hash' => AuthService::makeHash($request->input('password')),
            'display_name' => $request->input('display_name', $request->input('username')),
            'is_active'    => $request->boolean('is_active', true),
        ]);

        if ($request->filled('roles')) {
            $roleIds = Role::whereIn('name', $request->input('roles'))->pluck('id');
            $user->roles()->sync($roleIds);
        }

        $user->load('roles.permissions');

        return response()->json(new UserResource($user), 201);
    }

    /**
     * PUT /api/v1/admin/users/{id}
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = array_filter([
            'display_name' => $request->input('display_name'),
            'is_active'    => $request->has('is_active') ? $request->boolean('is_active') : null,
        ], fn ($v) => $v !== null);

        if ($request->filled('password')) {
            $data['password_hash'] = AuthService::makeHash($request->input('password'));
        }

        $user->update($data);
        $user->load('roles.permissions');

        return response()->json(new UserResource($user));
    }

    /**
     * POST /api/v1/admin/users/{id}/roles
     */
    public function assignRoles(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'roles'   => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user    = User::findOrFail($id);
        $roleIds = Role::whereIn('name', $request->input('roles'))->pluck('id');
        $user->roles()->syncWithoutDetaching($roleIds);
        $user->load('roles.permissions');

        return response()->json(new UserResource($user));
    }

    /**
     * DELETE /api/v1/admin/users/{id}/roles/{roleId}
     */
    public function removeRole(int $id, int $roleId): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->roles()->detach($roleId);
        $user->load('roles.permissions');

        return response()->json(new UserResource($user));
    }
}
