<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserProfileResource;
use App\Models\UserProfile;
use App\Services\ProfileComputationService;
use Illuminate\Http\JsonResponse;

class UserProfileController extends Controller
{
    /**
     * GET /api/v1/users/{id}/profile
     * Get the behavior profile for a user.
     */
    public function show(int $id): JsonResponse
    {
        $profile = UserProfile::where('user_id', $id)->first();

        if (!$profile) {
            // Return empty profile structure
            return response()->json([
                'data' => [
                    'user_id'           => $id,
                    'interest_tags'     => null,
                    'preference_vector' => null,
                    'last_computed_at'  => null,
                    'updated_at'        => null,
                ],
            ]);
        }

        return response()->json([
            'data' => new UserProfileResource($profile),
        ]);
    }

    /**
     * POST /api/v1/users/{id}/profile/recompute
     * Trigger a profile recomputation for a user.
     * Requires users.list permission.
     */
    public function recompute(int $id): JsonResponse
    {
        $service = new ProfileComputationService();
        $profile = $service->computeForUser($id);

        return response()->json([
            'data' => new UserProfileResource($profile),
        ]);
    }
}
