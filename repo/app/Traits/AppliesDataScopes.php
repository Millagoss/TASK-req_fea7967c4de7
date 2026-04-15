<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesDataScopes
{
    protected function applyDataScopes(Builder $query, Request $request, array $scopeMapping = []): Builder
    {
        $scopes = $request->attributes->get('data_scopes', []);

        if (empty($scopes)) {
            return $query;
        }

        foreach ($scopes as $scopeType => $scopeValues) {
            if (empty($scopeValues)) {
                continue;
            }

            $column = $scopeMapping[$scopeType] ?? null;

            if ($column === null) {
                continue;
            }

            $query->whereIn($column, $scopeValues);
        }

        return $query;
    }
}
