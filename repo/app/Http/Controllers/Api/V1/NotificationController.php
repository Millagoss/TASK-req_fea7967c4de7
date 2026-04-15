<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendBulkNotificationRequest;
use App\Http\Requests\SendNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * POST /api/v1/notifications/send
     * Send notification to specific user(s).
     */
    public function send(SendNotificationRequest $request): JsonResponse
    {
        $result = $this->notificationService->send(
            $request->input('template_id'),
            $request->input('recipient_ids'),
            $request->input('variables')
        );

        return response()->json($result);
    }

    /**
     * POST /api/v1/notifications/send-bulk
     * Bulk send notifications.
     */
    public function sendBulk(SendBulkNotificationRequest $request): JsonResponse
    {
        $recipientIds = $request->input('recipient_ids');

        if (count($recipientIds) > NotificationService::BULK_CAP) {
            return response()->json([
                'code' => 422,
                'msg'  => 'Bulk send cannot exceed 10,000 recipients',
            ], 422);
        }

        $batchId = Str::uuid()->toString();

        $result = $this->notificationService->send(
            $request->input('template_id'),
            $recipientIds,
            $request->input('variables'),
            $batchId
        );

        $result['batch_id'] = $batchId;

        return response()->json($result);
    }

    /**
     * GET /api/v1/notifications
     * List current user's notifications with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Notification::where('recipient_id', $userId);

        // Filter by read status
        if ($request->has('read')) {
            $read = filter_var($request->input('read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($read === true) {
                $query->whereNotNull('read_at');
            } elseif ($read === false) {
                $query->whereNull('read_at');
            }
        }

        // Filter by template_id
        if ($request->filled('template_id')) {
            $query->where('template_id', $request->input('template_id'));
        }

        // Deterministic ordering
        $query->orderBy('created_at', 'desc')
              ->orderBy('id', 'desc');

        // Pagination
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $notifications = $query->paginate($perPage);

        return response()->json([
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/notifications/{id}/read
     * Mark a single notification as read (idempotent).
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('recipient_id', $request->user()->id)
            ->firstOrFail();

        // Idempotent — don't update if already read
        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json([
            'data' => new NotificationResource($notification),
        ]);
    }

    /**
     * POST /api/v1/notifications/read-all
     * Mark all unread notifications as read for the current user.
     */
    public function readAll(Request $request): JsonResponse
    {
        $count = Notification::where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'updated' => $count,
        ]);
    }

    /**
     * GET /api/v1/notifications/unread-count
     * Get the count of unread notifications for the current user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }
}
