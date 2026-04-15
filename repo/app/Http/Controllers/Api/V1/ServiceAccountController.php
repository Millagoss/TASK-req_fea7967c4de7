<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ServiceAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceAccountController extends Controller
{
    public function __construct(private readonly ServiceAccountService $service) {}

    /**
     * POST /api/v1/admin/service-accounts
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'username'     => ['required', 'string', 'max:100', 'unique:users,username'],
            'display_name' => ['required', 'string', 'max:255'],
        ]);

        ['user' => $user, 'credential' => $credential] = $this->service->create(
            $request->input('username'),
            $request->input('display_name'),
        );

        $user->load('roles.permissions');

        return response()->json([
            'user'       => new UserResource($user),
            'credential' => $credential,
            'msg'        => 'Store this credential securely — it will not be shown again.',
        ], 201);
    }

    /**
     * POST /api/v1/admin/service-accounts/{id}/rotate
     */
    public function rotate(int $id): JsonResponse
    {
        $user = User::where('id', $id)->where('is_service_account', true)->firstOrFail();

        ['user' => $user, 'credential' => $credential] = $this->service->rotate($user);

        return response()->json([
            'user'       => new UserResource($user),
            'credential' => $credential,
            'msg'        => 'Credential rotated. Store this value securely — it will not be shown again.',
        ]);
    }
}
