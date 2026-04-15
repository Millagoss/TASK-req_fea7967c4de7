<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $request = request();
        $extra = $record->extra;
        $extra['request_id'] = $request?->header('X-Request-Id');
        $extra['actor_id'] = auth()->id() ?? 'anonymous';
        $extra['timestamp'] = now()->toIso8601String();

        return $record->with(extra: $extra);
    }
}
