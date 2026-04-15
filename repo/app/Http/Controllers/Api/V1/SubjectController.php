<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use App\Traits\AppliesDataScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    use AppliesDataScopes;
    /**
     * GET /api/v1/subjects
     * List subjects (auth, paginated, filterable by campus). PII masking applied via SubjectResource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subject::query();

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        if ($request->filled('campus')) {
            $query->where('campus', $request->input('campus'));
        }

        $query->orderBy('id', 'asc');

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $subjects = $query->paginate($perPage);

        return response()->json([
            'data' => SubjectResource::collection($subjects->items()),
            'meta' => [
                'current_page' => $subjects->currentPage(),
                'last_page'    => $subjects->lastPage(),
                'per_page'     => $subjects->perPage(),
                'total'        => $subjects->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/subjects
     * Create a new subject.
     */
    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = Subject::create($request->validated());

        return response()->json([
            'data' => new SubjectResource($subject),
        ], 201);
    }

    /**
     * GET /api/v1/subjects/{id}
     * Show a single subject. PII masking applied via SubjectResource.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = Subject::query();

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        $subject = $query->findOrFail($id);

        return response()->json([
            'data' => new SubjectResource($subject),
        ]);
    }

    /**
     * PUT /api/v1/subjects/{id}
     * Update a subject.
     */
    public function update(UpdateSubjectRequest $request, int $id): JsonResponse
    {
        $query = Subject::query();

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        $subject = $query->findOrFail($id);
        $subject->update($request->validated());

        return response()->json([
            'data' => new SubjectResource($subject),
        ]);
    }
}
