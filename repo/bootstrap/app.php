<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuditAdminAction;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ThrottleServiceAccount;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prependToGroup('api', AssignRequestId::class);

        $middleware->alias([
            'permission'            => CheckPermission::class,
            'throttle.service'      => ThrottleServiceAccount::class,
            'audit.admin'           => AuditAdminAction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['code' => 401, 'msg' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'code'   => 422,
                    'msg'    => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['code' => $e->getStatusCode(), 'msg' => $e->getMessage() ?: 'HTTP error.'], $e->getStatusCode());
            }
        });
    })->create();
