<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeasurementCodeRequest;
use App\Http\Requests\UpdateMeasurementCodeRequest;
use App\Http\Resources\MeasurementCodeResource;
use App\Models\MeasurementCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementCodeController extends Controller
{
    /**
     * GET /api/v1/measurement-codes
     * List measurement codes (auth, paginated, filterable by value_type, is_active).
     */
    public function index(Request $request): JsonResponse
    {
        $query = MeasurementCode::query();

        if ($request->filled('value_type')) {
            $query->where('value_type', $request->input('value_type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('code', 'asc');

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $codes = $query->paginate($perPage);

        return response()->json([
            'data' => MeasurementCodeResource::collection($codes->items()),
            'meta' => [
                'current_page' => $codes->currentPage(),
                'last_page'    => $codes->lastPage(),
                'per_page'     => $codes->perPage(),
                'total'        => $codes->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/measurement-codes
     * Create a new measurement code.
     */
    public function store(StoreMeasurementCodeRequest $request): JsonResponse
    {
        $code = MeasurementCode::create($request->validated());

        return response()->json([
            'data' => new MeasurementCodeResource($code),
        ], 201);
    }

    /**
     * GET /api/v1/measurement-codes/{id}
     * Show a single measurement code with unitConversions loaded.
     */
    public function show(int $id): JsonResponse
    {
        $code = MeasurementCode::with('unitConversions')->findOrFail($id);

        return response()->json([
            'data' => new MeasurementCodeResource($code),
        ]);
    }

    /**
     * PUT /api/v1/measurement-codes/{id}
     * Update a measurement code.
     */
    public function update(UpdateMeasurementCodeRequest $request, int $id): JsonResponse
    {
        $code = MeasurementCode::findOrFail($id);
        $code->update($request->validated());

        return response()->json([
            'data' => new MeasurementCodeResource($code),
        ]);
    }
}
