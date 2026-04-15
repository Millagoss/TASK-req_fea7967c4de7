<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationTemplateRequest;
use App\Http\Requests\UpdateNotificationTemplateRequest;
use App\Http\Resources\NotificationTemplateResource;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;

class NotificationTemplateController extends Controller
{
    /**
     * GET /api/v1/notification-templates
     * List all notification templates (paginated, ordered by name).
     */
    public function index(): JsonResponse
    {
        $templates = NotificationTemplate::orderBy('name')->paginate(20);

        return response()->json([
            'data' => NotificationTemplateResource::collection($templates->items()),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page'    => $templates->lastPage(),
                'per_page'     => $templates->perPage(),
                'total'        => $templates->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/notification-templates
     * Create a new notification template.
     */
    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        $template = NotificationTemplate::create([
            'name'       => $request->input('name'),
            'subject'    => $request->input('subject'),
            'body'       => $request->input('body'),
            'variables'  => $request->input('variables'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new NotificationTemplateResource($template),
        ], 201);
    }

    /**
     * PUT /api/v1/notification-templates/{id}
     * Update an existing notification template.
     */
    public function update(UpdateNotificationTemplateRequest $request, int $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);

        $data = array_filter([
            'name'      => $request->input('name'),
            'subject'   => $request->input('subject'),
            'body'      => $request->input('body'),
            'variables' => $request->input('variables'),
        ], fn ($v) => $v !== null);

        $template->update($data);

        return response()->json([
            'data' => new NotificationTemplateResource($template),
        ]);
    }

    /**
     * DELETE /api/v1/notification-templates/{id}
     * Delete a notification template if no notifications reference it.
     */
    public function destroy(int $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);

        // Check if any notifications reference this template
        $hasNotifications = Notification::where('template_id', $id)->exists();

        if ($hasNotifications) {
            return response()->json([
                'code' => 422,
                'msg'  => 'Cannot delete template that has associated notifications. Notifications still reference this template.',
            ], 422);
        }

        $template->delete();

        return response()->json([
            'msg' => 'Template deleted successfully.',
        ]);
    }
}
