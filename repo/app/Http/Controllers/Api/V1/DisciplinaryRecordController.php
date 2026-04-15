<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppealDisciplinaryRecordRequest;
use App\Http\Requests\ClearDisciplinaryRecordRequest;
use App\Http\Requests\StoreDisciplinaryRecordRequest;
use App\Http\Resources\DisciplinaryRecordResource;
use App\Http\Resources\DisciplinaryStatsResource;
use App\Models\DisciplinaryRecord;
use App\Models\RewardPenaltyType;
use App\Traits\AppliesDataScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DisciplinaryRecordController extends Controller
{
    use AppliesDataScopes;
    /**
     * GET /api/v1/disciplinary-records
     * List disciplinary records with filters and eager loading.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DisciplinaryRecord::with([
            'type',
            'subject',
            'issuer',
            'evaluationCycle',
            'leaderProfile',
        ]);

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        if ($request->filled('subject_user_id')) {
            $query->where('subject_user_id', $request->input('subject_user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('evaluation_cycle_id')) {
            $query->where('evaluation_cycle_id', $request->input('evaluation_cycle_id'));
        }

        if ($request->filled('type_id')) {
            $query->where('type_id', $request->input('type_id'));
        }

        if ($request->filled('category')) {
            $category = $request->input('category');
            $query->whereHas('type', function ($q) use ($category) {
                $q->where('category', $category);
            });
        }

        $query->orderBy('issued_at', 'desc')
              ->orderBy('id', 'desc');

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $records = $query->paginate($perPage);

        return response()->json([
            'data' => DisciplinaryRecordResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
                'per_page'     => $records->perPage(),
                'total'        => $records->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/disciplinary-records
     * Create a new disciplinary record.
     */
    public function store(StoreDisciplinaryRecordRequest $request): JsonResponse
    {
        $type = RewardPenaltyType::findOrFail($request->input('type_id'));

        $points = $request->input('points') ?? $type->default_points;
        $issuedAt = now();
        $expiresAt = $type->default_expiration_days
            ? $issuedAt->copy()->addDays($type->default_expiration_days)
            : null;

        $record = DisciplinaryRecord::create([
            'type_id'             => $type->id,
            'subject_user_id'     => $request->input('subject_user_id'),
            'issuer_user_id'      => $request->user()->id,
            'evaluation_cycle_id' => $request->input('evaluation_cycle_id'),
            'leader_profile_id'   => $request->input('leader_profile_id'),
            'status'              => 'active',
            'reason'              => $request->input('reason'),
            'points'              => $points,
            'issued_at'           => $issuedAt,
            'expires_at'          => $expiresAt,
        ]);

        $record->load([
            'type',
            'subject',
            'issuer',
            'evaluationCycle',
            'leaderProfile',
        ]);

        return response()->json([
            'data' => new DisciplinaryRecordResource($record),
        ], 201);
    }

    /**
     * GET /api/v1/disciplinary-records/{id}
     * Show a single disciplinary record with all relationships.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = DisciplinaryRecord::with([
            'type',
            'subject',
            'issuer',
            'evaluationCycle',
            'leaderProfile',
            'clearedByUser',
        ]);

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        $record = $query->findOrFail($id);

        return response()->json([
            'data' => new DisciplinaryRecordResource($record),
        ]);
    }

    /**
     * POST /api/v1/disciplinary-records/{id}/appeal
     * Appeal a disciplinary record.
     */
    public function appeal(AppealDisciplinaryRecordRequest $request, int $id): JsonResponse
    {
        $query = DisciplinaryRecord::query();

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        $record = $query->findOrFail($id);

        // Only the subject or a user with disciplinary.appeal permission can appeal
        $user = $request->user();
        if ($record->subject_user_id !== $user->id && !$user->hasPermission('disciplinary.appeal')) {
            return response()->json([
                'code' => 403,
                'msg'  => 'You do not have permission to appeal this record.',
            ], 403);
        }

        if ($record->status !== 'active') {
            return response()->json([
                'code' => 422,
                'msg'  => 'Only active records can be appealed.',
            ], 422);
        }

        if ($record->appealed_at !== null) {
            return response()->json([
                'code' => 422,
                'msg'  => 'This record has already been appealed.',
            ], 422);
        }

        if ($record->expires_at !== null && now()->greaterThan($record->expires_at)) {
            return response()->json([
                'code' => 422,
                'msg'  => 'Expired records cannot be appealed.',
            ], 422);
        }

        $record->update([
            'status'        => 'appealed',
            'appealed_at'   => now(),
            'appeal_reason' => $request->input('appeal_reason'),
        ]);

        $record->load([
            'type',
            'subject',
            'issuer',
            'evaluationCycle',
            'leaderProfile',
        ]);

        return response()->json([
            'data' => new DisciplinaryRecordResource($record),
        ]);
    }

    /**
     * POST /api/v1/disciplinary-records/{id}/clear
     * Clear an appealed disciplinary record.
     */
    public function clear(ClearDisciplinaryRecordRequest $request, int $id): JsonResponse
    {
        $query = DisciplinaryRecord::query();

        $this->applyDataScopes($query, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        $record = $query->findOrFail($id);

        if ($record->status !== 'appealed') {
            return response()->json([
                'code' => 422,
                'msg'  => 'Only appealed records can be cleared.',
            ], 422);
        }

        $record->update([
            'status'         => 'cleared',
            'cleared_at'     => now(),
            'cleared_by'     => $request->user()->id,
            'cleared_reason' => $request->input('cleared_reason'),
        ]);

        $record->load([
            'type',
            'subject',
            'issuer',
            'evaluationCycle',
            'leaderProfile',
            'clearedByUser',
        ]);

        return response()->json([
            'data' => new DisciplinaryRecordResource($record),
        ]);
    }

    /**
     * GET /api/v1/disciplinary-records/stats
     * Statistics grouped by role, period, or category.
     */
    public function stats(Request $request): JsonResponse
    {
        $groupBy = $request->input('group_by');

        if (!in_array($groupBy, ['role', 'period', 'category'], true)) {
            return response()->json([
                'code' => 422,
                'msg'  => 'The group_by parameter is required and must be one of: role, period, category.',
            ], 422);
        }

        $baseQuery = DisciplinaryRecord::query();

        $this->applyDataScopes($baseQuery, $request, [
            'campus'       => 'campus',
            'organization' => 'organization',
        ]);

        if ($request->filled('evaluation_cycle_id')) {
            $baseQuery->where('evaluation_cycle_id', $request->input('evaluation_cycle_id'));
        }

        $results = [];

        if ($groupBy === 'role') {
            $results = $baseQuery
                ->join('user_role', 'disciplinary_records.subject_user_id', '=', 'user_role.user_id')
                ->join('roles', 'user_role.role_id', '=', 'roles.id')
                ->select(
                    'roles.name as group_name',
                    DB::raw('SUM(DISTINCT disciplinary_records.points) as total_points'),
                    DB::raw('COUNT(DISTINCT disciplinary_records.id) as record_count')
                )
                ->groupBy('roles.name')
                ->get()
                ->map(function ($row) {
                    return [
                        'group'        => $row->group_name,
                        'total_points' => $row->total_points,
                        'record_count' => $row->record_count,
                    ];
                })
                ->all();
        } elseif ($groupBy === 'period') {
            $results = $baseQuery
                ->select(
                    DB::raw("DATE_FORMAT(disciplinary_records.issued_at, '%Y-%m') as group_name"),
                    DB::raw('SUM(disciplinary_records.points) as total_points'),
                    DB::raw('COUNT(disciplinary_records.id) as record_count')
                )
                ->groupBy('group_name')
                ->orderBy('group_name')
                ->get()
                ->map(function ($row) {
                    return [
                        'group'        => $row->group_name,
                        'total_points' => $row->total_points,
                        'record_count' => $row->record_count,
                    ];
                })
                ->all();
        } elseif ($groupBy === 'category') {
            $results = $baseQuery
                ->join('reward_penalty_types', 'disciplinary_records.type_id', '=', 'reward_penalty_types.id')
                ->select(
                    'reward_penalty_types.category as group_name',
                    DB::raw('SUM(disciplinary_records.points) as total_points'),
                    DB::raw('COUNT(disciplinary_records.id) as record_count')
                )
                ->groupBy('reward_penalty_types.category')
                ->get()
                ->map(function ($row) {
                    return [
                        'group'        => $row->group_name,
                        'total_points' => $row->total_points,
                        'record_count' => $row->record_count,
                    ];
                })
                ->all();
        }

        return response()->json([
            'data' => DisciplinaryStatsResource::collection($results),
        ]);
    }
}
