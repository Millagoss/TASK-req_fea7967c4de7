<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['code' => 401, 'msg' => 'Unauthenticated.'], 401);
        }

        // Eager-load roles and permissions if not already loaded.
        if (! $user->relationLoaded('roles') || $user->roles->first()?->relationLoaded('permissions') === false) {
            $user->load('roles.permissions');
        }

        if (! $user->hasPermission($permission)) {
            return response()->json([
                'code' => 403,
                'msg'  => 'Forbidden. Required permission: '.$permission,
            ], 403);
        }

        return $next($request);
    }
}
