<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchResultRequest;
use App\Http\Requests\ReviewResultRequest;
use App\Http\Requests\StoreResultRequest;
use App\Http\Resources\ResultResource;
use App\Models\Result;
use App\Services\ResultStatisticsService;
use App\Services\ResultValidationService;
use App\Traits\AppliesDataScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResultController extends Controller
{
    use AppliesDataScopes;
    protected ResultValidationService $validationService;
    protected ResultStatisticsService $statisticsService;

    public function __construct(ResultValidationService $validationService, ResultStatisticsService $statisticsService)
    {
        $this->validationService = $validationService;
        $this->statisticsService = $statisticsService;
    }

    /**
     * POST /api/v1/results
     * Manual single result entry.
     */
    public function store(StoreResultRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $outcome = $this->validationService->process(
                $data,
                $request->user()->id,
                'manual'
            );
        } catch (ValidationException $e) {
            return response()->json([
                'code' => 422,
                'msg'  => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $outcome['result']->load(['subject', 'measurementCode', 'reviewer', 'creator']);

        return response()->json([
            'data'     => new ResultResource($outcome['result']),
            'warnings' => $outcome['warnings'],
        ], 201);
    }

    /**
     * POST /api/v1/results/batch
     * FHIR-inspired batch import.
     */
    public function batch(BatchResultRequest $request): JsonResponse
    {
        $observations = $request->input('observations', []);
        $batchId = (string) Str::uuid();
        $results = [];
        $errors = [];

        foreach ($observations as $index => $obs) {
            try {
                $outcome = $this->validationService->process(
                    $obs,
                    $request->user()->id,
                    'rest_integration',
                    $batchId
                );
                $outcome['result']->load(['subject', 'measurementCode']);
                $results[] = [
                    'result'   => new ResultResource($outcome['result']),
                    'warnings' => $outcome['warnings'],
                ];
            } catch (ValidationException $e) {
                $errors[] = [
                    'index'   => $index,
                    'msg' => collect($e->errors())->flatten()->first(),
                ];
            }
        }

        return response()->json([
            'batch_id' => $batchId,
            'imported'  => count($results),
            'errors'    => $errors,
            'results'   => $results,
        ]);
    }

    /**
     * POST /api/v1/results/import-csv
     * CSV file upload and import.
     */
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('csv_file');
        $batchId = (string) Str::uuid();
        $results = [];
        $errors = [];

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return response()->json([
                'code' => 422,
                'msg'  => 'Unable to read uploaded file.',
            ], 422);
        }

        // Read header row
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return response()->json([
                'code' => 422,
                'msg'  => 'CSV file is empty.',
            ], 422);
        }

        // Normalize header names (trim whitespace)
        $header = array_map('trim', $header);

        $expectedColumns = ['code', 'subject_identifier', 'value', 'unit', 'observed_at'];
        $missingColumns = array_diff($expectedColumns, $header);
        if (!empty($missingColumns)) {
            fclose($handle);
            return response()->json([
                'code' => 422,
                'msg'  => 'Missing required CSV columns: ' . implode(', ', $missingColumns),
            ], 422);
        }

        $rowNumber = 1; // Data rows start at 1 (after header)
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (count($row) === 1 && ($row[0] === null || $row[0] === '')) {
                continue;
            }

            // Map columns to data
            $data = [];
            foreach ($header as $idx => $col) {
                $data[$col] = isset($row[$idx]) ? trim($row[$idx]) : null;
            }

            try {
                $outcome = $this->validationService->process(
                    $data,
                    $request->user()->id,
                    'csv_import',
                    $batchId
                );
                $results[] = $outcome['result']->id;
            } catch (ValidationException $e) {
                $errors[] = [
                    'row'     => $rowNumber,
                    'msg' => collect($e->errors())->flatten()->first(),
                ];
            }
        }

        fclose($handle);

        return response()->json([
            'batch_id' => $batchId,
            'imported'  => count($results),
            'errors'    => $errors,
        ]);
    }

    /**
     * GET /api/v1/results
     * List results with filters, paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Result::with(['subject', 'measurementCode', 'reviewer', 'creator']);

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }

        if ($request->filled('measurement_code_id')) {
            $query->where('measurement_code_id', $request->input('measurement_code_id'));
        }

        if ($request->filled('review_status')) {
            $query->where('review_status', $request->input('review_status'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        if ($request->has('is_outlier')) {
            $query->where('is_outlier', filter_var($request->input('is_outlier'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('date_from')) {
            $query->where('observed_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('observed_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        $query->orderBy('observed_at', 'desc')
              ->orderBy('id', 'desc');

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $results = $query->paginate($perPage);

        return response()->json([
            'data' => ResultResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'per_page'     => $results->perPage(),
                'total'        => $results->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/results/{id}
     * Show a single result.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = Result::with(['subject', 'measurementCode', 'reviewer', 'creator']);

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        $result = $query->findOrFail($id);

        return response()->json([
            'data' => new ResultResource($result),
        ]);
    }

    /**
     * GET /api/v1/results/flagged
     * Outliers needing review (is_outlier=true AND review_status=pending).
     */
    public function flagged(Request $request): JsonResponse
    {
        $query = Result::with(['subject', 'measurementCode', 'reviewer', 'creator'])
            ->where('is_outlier', true)
            ->where('review_status', 'pending');

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        $query->orderBy('observed_at', 'desc')
              ->orderBy('id', 'desc');

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $results = $query->paginate($perPage);

        return response()->json([
            'data' => ResultResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'per_page'     => $results->perPage(),
                'total'        => $results->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/results/{id}/review
     * Approve or reject a result.
     */
    public function review(ReviewResultRequest $request, int $id): JsonResponse
    {
        $query = Result::query();

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        $result = $query->findOrFail($id);

        // Reviewer cannot be the same user who created the result
        if ($result->created_by === $request->user()->id) {
            return response()->json([
                'code' => 422,
                'msg'  => 'You cannot review a result you created.',
            ], 422);
        }

        $result->update([
            'review_status'  => $request->input('decision'),
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
            'review_comment' => $request->input('review_comment'),
        ]);

        $result->load(['subject', 'measurementCode', 'reviewer', 'creator']);

        return response()->json([
            'data' => new ResultResource($result),
        ]);
    }

    /**
     * POST /api/v1/results/recompute-stats
     * Recompute statistics for all active measurement codes.
     */
    public function recomputeStats(): JsonResponse
    {
        $count = $this->statisticsService->recomputeAll();

        return response()->json([
            'code'          => 200,
            'msg'           => 'Statistics recomputed successfully.',
            'codes_updated' => $count,
        ]);
    }
}
