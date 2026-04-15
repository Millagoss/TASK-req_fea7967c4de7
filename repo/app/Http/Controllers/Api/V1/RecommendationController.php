<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecommendationResource;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * GET /api/v1/recommendations/{userId}
     * Get personalized recommendations for a user.
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        $authUser = $request->user();
        if ($authUser->id !== $userId && !$authUser->hasPermission('users.list')) {
            return response()->json([
                'code' => 403,
                'msg'  => 'You do not have permission to view this user\'s recommendations.',
            ], 403);
        }

        $service         = new RecommendationService();
        $recommendations = $service->recommend($userId);

        return response()->json([
            'data' => RecommendationResource::collection($recommendations),
        ]);
    }
}
