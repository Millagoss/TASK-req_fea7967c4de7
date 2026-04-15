<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    private static array $preUpdateState = [];

    private static array $excludedFromHash = ['id', 'created_at', 'updated_at', 'created_by'];

    public static function bootAuditable(): void
    {
        static::updating(function ($model) {
            $filtered = array_diff_key($model->getOriginal(), array_flip(self::$excludedFromHash));
            self::$preUpdateState[get_class($model) . ':' . $model->getKey()] = $filtered;
        });

        static::created(function ($model) {
            self::writeAuditLog($model, 'created');
        });

        static::updated(function ($model) {
            self::writeAuditLog($model, 'updated');
        });

        static::deleted(function ($model) {
            self::writeAuditLog($model, 'deleted');
        });
    }

    private static function writeAuditLog($model, string $action): void
    {
        $beforeHash = null;
        $afterHash = null;

        $filterAttributes = fn (array $attrs) => array_diff_key($attrs, array_flip(self::$excludedFromHash));

        if ($action === 'updated') {
            $stateKey = get_class($model) . ':' . $model->getKey();
            $before = self::$preUpdateState[$stateKey] ?? $filterAttributes($model->getOriginal());
            unset(self::$preUpdateState[$stateKey]);
            $beforeHash = hash('sha256', json_encode($before));
            $afterHash = hash('sha256', json_encode($filterAttributes($model->getAttributes())));
        } elseif ($action === 'created') {
            $afterHash = hash('sha256', json_encode($filterAttributes($model->getAttributes())));
        } elseif ($action === 'deleted') {
            $beforeHash = hash('sha256', json_encode($filterAttributes($model->getOriginal())));
        }

        try {
            AuditLog::create([
                'actor_id'      => auth()->id(),
                'action'        => $action,
                'resource_type' => $model->getTable(),
                'resource_id'   => (string) $model->getKey(),
                'request_id'    => request()?->header('X-Request-Id'),
                'before_hash'   => $beforeHash,
                'after_hash'    => $afterHash,
                'metadata'      => $action === 'updated' ? ['changed' => array_keys($model->getDirty())] : null,
            ]);
        } catch (\Throwable $e) {
            // Don't let audit logging break the application
            \Illuminate\Support\Facades\Log::warning('Audit log write failed: ' . $e->getMessage());
        }
    }
}
