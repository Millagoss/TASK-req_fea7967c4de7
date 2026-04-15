<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListEventsRequest;
use App\Http\Requests\RecordEventRequest;
use App\Http\Resources\BehaviorEventResource;
use App\Models\BehaviorEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BehaviorEventController extends Controller
{
    /**
     * POST /api/v1/behavior/events
     * Record a new behavior event.
     */
    public function store(RecordEventRequest $request): JsonResponse
    {
        $serverTimestamp = now();
        $userId          = $request->user()->id;
        $eventType       = $request->input('event_type');
        $targetId        = $request->input('target_id');

        // Dedup check with transaction + locking to prevent race conditions
        return DB::transaction(function () use ($request, $userId, $eventType, $targetId, $serverTimestamp) {
            $dedupWindow = Carbon::now()->subSeconds(5);

            $existing = BehaviorEvent::where('user_id', $userId)
                ->where('event_type', $eventType)
                ->where('target_type', $request->input('target_type'))
                ->where('target_id', $targetId)
                ->where('server_timestamp', '>=', $dedupWindow)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return response()->json([
                    'data'         => new BehaviorEventResource($existing),
                    'deduplicated' => true,
                ], 200);
            }

            $event = BehaviorEvent::create([
                'user_id'          => $userId,
                'event_type'       => $eventType,
                'target_type'      => $request->input('target_type'),
                'target_id'        => $targetId,
                'payload'          => $request->input('payload'),
                'server_timestamp' => $serverTimestamp,
                'request_id'       => $request->header('X-Request-Id'),
            ]);

            return response()->json([
                'data' => new BehaviorEventResource($event),
            ], 201);
        });
    }

    /**
     * GET /api/v1/behavior/events
     * List behavior events with filtering and pagination.
     * Requires users.list permission.
     */
    public function index(ListEventsRequest $request): JsonResponse
    {
        $query = BehaviorEvent::query();

        // Apply filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->input('target_type'));
        }

        if ($request->filled('target_id')) {
            $query->where('target_id', $request->input('target_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        // Deterministic ordering: server_timestamp DESC, id DESC
        $sortDir = $request->input('sort_dir', 'desc');
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $query->orderBy('server_timestamp', $sortDir)
              ->orderBy('id', $sortDir);

        // Pagination
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $events = $query->paginate($perPage);

        return response()->json([
            'data' => BehaviorEventResource::collection($events->items()),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
                'per_page'     => $events->perPage(),
                'total'        => $events->total(),
            ],
        ]);
    }
}
