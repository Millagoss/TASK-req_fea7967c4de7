<?php

namespace App\Logging;

use App\Services\FieldMaskingService;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class SensitiveFieldProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = FieldMaskingService::maskArray($record->context);
        return $record->with(context: $context);
    }
}
