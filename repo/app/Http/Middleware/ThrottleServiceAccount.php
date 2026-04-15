<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottleServiceAccount
{
    private const MAX_REQUESTS  = 60;
    private const DECAY_SECONDS = 60;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_service_account) {
            return $next($request);
        }

        $key = 'service_account:'.$user->id;

        if ($this->limiter->tooManyAttempts($key, self::MAX_REQUESTS)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'code'        => 429,
                'msg'         => 'Too many requests. Service account rate limit exceeded.',
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After'         => $retryAfter,
                'X-RateLimit-Limit'   => self::MAX_REQUESTS,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $this->limiter->hit($key, self::DECAY_SECONDS);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', self::MAX_REQUESTS);
        $response->headers->set(
            'X-RateLimit-Remaining',
            max(0, self::MAX_REQUESTS - $this->limiter->attempts($key))
        );

        return $response;
    }
}
