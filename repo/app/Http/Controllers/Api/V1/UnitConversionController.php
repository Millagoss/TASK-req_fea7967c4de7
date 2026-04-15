<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitConversionRequest;
use App\Http\Resources\UnitConversionResource;
use App\Models\UnitConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitConversionController extends Controller
{
    /**
     * GET /api/v1/unit-conversions
     * List unit conversions (auth, filterable by measurement_code_id).
     */
    public function index(Request $request): JsonResponse
    {
        $query = UnitConversion::query();

        if ($request->filled('measurement_code_id')) {
            $query->where('measurement_code_id', $request->input('measurement_code_id'));
        }

        $conversions = $query->orderBy('id', 'asc')->get();

        return response()->json([
            'data' => UnitConversionResource::collection($conversions),
        ]);
    }

    /**
     * POST /api/v1/unit-conversions
     * Create a new unit conversion.
     */
    public function store(StoreUnitConversionRequest $request): JsonResponse
    {
        $conversion = UnitConversion::create($request->validated());

        return response()->json([
            'data' => new UnitConversionResource($conversion),
        ], 201);
    }
}
