<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches active data-scope constraints to the request so downstream
 * query builders can apply them.  Scopes from different scope_types are
 * ANDed together; multiple values within the same scope_type are ORed.
 *
 * Consumers retrieve the resolved scopes via:
 *   $request->attributes->get('data_scopes')   // array keyed by scope_type
 */
class ApplyDataScopes
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $scopes = $user->dataScopes()
                ->with('role')
                ->get()
                ->groupBy('scope_type')
                ->map(fn ($group) => $group->pluck('scope_value')->all())
                ->all();

            $request->attributes->set('data_scopes', $scopes);
        }

        return $next($request);
    }
}
