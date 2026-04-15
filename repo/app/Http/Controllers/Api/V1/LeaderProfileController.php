<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaderProfileRequest;
use App\Http\Requests\UpdateLeaderProfileRequest;
use App\Http\Resources\LeaderProfileResource;
use App\Models\LeaderProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderProfileController extends Controller
{
    /**
     * GET /api/v1/leader-profiles
     * List leader profiles with user relationship.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeaderProfile::with('user');

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $profiles = $query->paginate($perPage);

        return response()->json([
            'data' => LeaderProfileResource::collection($profiles->items()),
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page'    => $profiles->lastPage(),
                'per_page'     => $profiles->perPage(),
                'total'        => $profiles->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/leader-profiles
     * Create a new leader profile.
     */
    public function store(StoreLeaderProfileRequest $request): JsonResponse
    {
        $profile = LeaderProfile::create($request->only([
            'user_id',
            'title',
            'department',
            'campus',
        ]));

        $profile->load('user');

        return response()->json([
            'data' => new LeaderProfileResource($profile),
        ], 201);
    }

    /**
     * GET /api/v1/leader-profiles/{id}
     * Show a single leader profile.
     */
    public function show(int $id): JsonResponse
    {
        $profile = LeaderProfile::with('user')->findOrFail($id);

        return response()->json([
            'data' => new LeaderProfileResource($profile),
        ]);
    }

    /**
     * PUT /api/v1/leader-profiles/{id}
     * Update a leader profile.
     */
    public function update(UpdateLeaderProfileRequest $request, int $id): JsonResponse
    {
        $profile = LeaderProfile::findOrFail($id);

        $profile->update($request->only(['title', 'department', 'campus']));

        $profile->load('user');

        return response()->json([
            'data' => new LeaderProfileResource($profile),
        ]);
    }
}
