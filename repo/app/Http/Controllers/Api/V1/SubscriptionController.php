<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionsRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\NotificationSubscription;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * GET /api/v1/subscriptions
     * List all notification templates with the current user's subscription status.
     * Templates without an explicit subscription record default to is_subscribed=true.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $templates = NotificationTemplate::orderBy('name')->get();

        // Fetch user's subscription records keyed by template_id
        $subscriptions = NotificationSubscription::where('user_id', $userId)
            ->get()
            ->keyBy('template_id');

        $result = $templates->map(function ($template) use ($subscriptions) {
            $sub = $subscriptions->get($template->id);

            return (object) [
                'template_id'   => $template->id,
                'template_name' => $template->name,
                'is_subscribed'  => $sub ? $sub->is_subscribed : true,
            ];
        });

        return response()->json([
            'data' => SubscriptionResource::collection($result),
        ]);
    }

    /**
     * PUT /api/v1/subscriptions
     * Update the current user's subscription preferences.
     */
    public function update(UpdateSubscriptionsRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        foreach ($request->input('subscriptions') as $item) {
            NotificationSubscription::updateOrCreate(
                [
                    'user_id'     => $userId,
                    'template_id' => $item['template_id'],
                ],
                [
                    'is_subscribed' => $item['is_subscribed'],
                ]
            );
        }

        // Return the updated full list
        $templates = NotificationTemplate::orderBy('name')->get();

        $subscriptions = NotificationSubscription::where('user_id', $userId)
            ->get()
            ->keyBy('template_id');

        $result = $templates->map(function ($template) use ($subscriptions) {
            $sub = $subscriptions->get($template->id);

            return (object) [
                'template_id'   => $template->id,
                'template_name' => $template->name,
                'is_subscribed'  => $sub ? $sub->is_subscribed : true,
            ];
        });

        return response()->json([
            'data' => SubscriptionResource::collection($result),
        ]);
    }
}
