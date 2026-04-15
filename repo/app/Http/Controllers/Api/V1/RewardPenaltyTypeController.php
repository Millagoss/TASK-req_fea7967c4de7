<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRewardPenaltyTypeRequest;
use App\Http\Requests\UpdateRewardPenaltyTypeRequest;
use App\Http\Resources\RewardPenaltyTypeResource;
use App\Models\RewardPenaltyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardPenaltyTypeController extends Controller
{
    /**
     * GET /api/v1/reward-penalty-types
     * List reward/penalty types, filterable by category and is_active.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RewardPenaltyType::query();

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $query->orderBy('category')
              ->orderBy('name');

        $types = $query->get();

        return response()->json([
            'data' => RewardPenaltyTypeResource::collection($types),
        ]);
    }

    /**
     * POST /api/v1/reward-penalty-types
     * Create a new reward/penalty type.
     */
    public function store(StoreRewardPenaltyTypeRequest $request): JsonResponse
    {
        $data = $request->only([
            'name',
            'category',
            'severity',
            'default_points',
            'default_expiration_days',
            'is_active',
        ]);

        if (!isset($data['default_expiration_days'])) {
            $data['default_expiration_days'] = 365;
        }

        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $type = RewardPenaltyType::create($data);

        return response()->json([
            'data' => new RewardPenaltyTypeResource($type),
        ], 201);
    }

    /**
     * PUT /api/v1/reward-penalty-types/{id}
     * Update a reward/penalty type.
     */
    public function update(UpdateRewardPenaltyTypeRequest $request, int $id): JsonResponse
    {
        $type = RewardPenaltyType::findOrFail($id);

        $type->update($request->only([
            'name',
            'category',
            'severity',
            'default_points',
            'default_expiration_days',
            'is_active',
        ]));

        return response()->json([
            'data' => new RewardPenaltyTypeResource($type),
        ]);
    }
}
