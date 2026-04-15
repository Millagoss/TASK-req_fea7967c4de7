<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvaluationCycleRequest;
use App\Http\Requests\UpdateEvaluationCycleRequest;
use App\Http\Resources\EvaluationCycleResource;
use App\Models\EvaluationCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationCycleController extends Controller
{
    /**
     * GET /api/v1/evaluation-cycles
     * List evaluation cycles, filterable by status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EvaluationCycle::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query->orderBy('start_date', 'desc')
              ->orderBy('id', 'desc');

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $cycles = $query->paginate($perPage);

        return response()->json([
            'data' => EvaluationCycleResource::collection($cycles->items()),
            'meta' => [
                'current_page' => $cycles->currentPage(),
                'last_page'    => $cycles->lastPage(),
                'per_page'     => $cycles->perPage(),
                'total'        => $cycles->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/evaluation-cycles
     * Create a new evaluation cycle.
     */
    public function store(StoreEvaluationCycleRequest $request): JsonResponse
    {
        $cycle = EvaluationCycle::create([
            'name'       => $request->input('name'),
            'start_date' => $request->input('start_date'),
            'end_date'   => $request->input('end_date'),
            'status'     => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new EvaluationCycleResource($cycle),
        ], 201);
    }

    /**
     * GET /api/v1/evaluation-cycles/{id}
     * Show a single evaluation cycle with disciplinary records count.
     */
    public function show(int $id): JsonResponse
    {
        $cycle = EvaluationCycle::withCount('disciplinaryRecords')->findOrFail($id);

        return response()->json([
            'data' => new EvaluationCycleResource($cycle),
        ]);
    }

    /**
     * PUT /api/v1/evaluation-cycles/{id}
     * Update an evaluation cycle (only if draft).
     */
    public function update(UpdateEvaluationCycleRequest $request, int $id): JsonResponse
    {
        $cycle = EvaluationCycle::findOrFail($id);

        if ($cycle->status !== 'draft') {
            return response()->json([
                'code' => 422,
                'msg'  => 'Only evaluation cycles in draft status can be updated.',
            ], 422);
        }

        $cycle->update($request->only(['name', 'start_date', 'end_date']));

        return response()->json([
            'data' => new EvaluationCycleResource($cycle),
        ]);
    }

    /**
     * POST /api/v1/evaluation-cycles/{id}/activate
     * Transition from draft to active.
     */
    public function activate(int $id): JsonResponse
    {
        $cycle = EvaluationCycle::findOrFail($id);

        if ($cycle->status !== 'draft') {
            return response()->json([
                'code' => 422,
                'msg'  => 'Only draft evaluation cycles can be activated.',
            ], 422);
        }

        $cycle->update(['status' => 'active']);

        return response()->json([
            'data' => new EvaluationCycleResource($cycle),
        ]);
    }

    /**
     * POST /api/v1/evaluation-cycles/{id}/close
     * Transition from active to closed.
     */
    public function close(int $id): JsonResponse
    {
        $cycle = EvaluationCycle::findOrFail($id);

        if ($cycle->status !== 'active') {
            return response()->json([
                'code' => 422,
                'msg'  => 'Only active evaluation cycles can be closed.',
            ], 422);
        }

        $cycle->update(['status' => 'closed']);

        return response()->json([
            'data' => new EvaluationCycleResource($cycle),
        ]);
    }
}
