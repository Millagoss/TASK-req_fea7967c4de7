<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminAction
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Audit all write operations across the API.
        if (
            in_array($request->method(), self::WRITE_METHODS, true)
            && $request->is('api/v1/*')
        ) {
            $this->writeAuditLog($request, $response);
        }

        return $response;
    }

    private function writeAuditLog(Request $request, Response $response): void
    {
        $user      = $request->user();
        $requestId = $request->attributes->get('request_id', $request->header('X-Request-Id'));

        $segments     = explode('/', trim($request->path(), '/'));
        $resourceType = $this->deriveResourceType($request->path());
        $resourceId   = $this->deriveResourceId($segments);

        $responseBody = $this->safeDecodeResponse($response);
        $afterHash    = $responseBody !== null
            ? hash('sha256', json_encode($responseBody, JSON_THROW_ON_ERROR))
            : null;

        AuditLog::create([
            'actor_id'      => $user?->id,
            'action'        => $request->method().'_'.strtoupper($resourceType),
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'request_id'    => $requestId,
            'before_hash'   => null,
            'after_hash'    => $afterHash,
            'metadata'      => [
                'url'     => $request->fullUrl(),
                'method'  => $request->method(),
                'status'  => $response->getStatusCode(),
                'ip'      => $request->ip(),
            ],
            'created_at' => Carbon::now(),
        ]);
    }

    private function deriveResourceType(string $path): string
    {
        $resourceMap = [
            'service-accounts' => 'service_account',
            'users' => 'user',
            'roles' => 'role',
            'permissions' => 'permission',
            'songs' => 'song',
            'albums' => 'album',
            'playlists' => 'playlist',
            'behavior' => 'behavior_event',
            'notifications' => 'notification',
            'notification-templates' => 'notification_template',
            'subscriptions' => 'subscription',
            'evaluation-cycles' => 'evaluation_cycle',
            'leader-profiles' => 'leader_profile',
            'reward-penalty-types' => 'reward_penalty_type',
            'disciplinary-records' => 'disciplinary_record',
            'measurement-codes' => 'measurement_code',
            'unit-conversions' => 'unit_conversion',
            'subjects' => 'subject',
            'results' => 'result',
        ];

        foreach ($resourceMap as $segment => $type) {
            if (str_contains($path, $segment)) {
                return $type;
            }
        }

        return 'unknown';
    }

    private function deriveResourceId(array $segments): string
    {
        // Look for first numeric segment as the resource ID
        foreach ($segments as $segment) {
            if (is_numeric($segment)) {
                return $segment;
            }
        }
        return '';
    }

    private function safeDecodeResponse(Response $response): mixed
    {
        $content = $response->getContent();
        if (empty($content)) {
            return null;
        }
        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
