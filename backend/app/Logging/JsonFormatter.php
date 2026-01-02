<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;
use Monolog\LogRecord;

/**
 * Custom JSON formatter for structured logging.
 *
 * This formatter outputs logs in a consistent JSON format optimized
 * for log aggregation tools like ELK Stack, Datadog, or CloudWatch.
 */
class JsonFormatter extends BaseJsonFormatter
{
    /**
     * {@inheritdoc}
     */
    public function format(LogRecord $record): string
    {
        $data = [
            '@timestamp' => $record->datetime->format('c'),
            'level' => strtolower($record->level->name),
            'level_code' => $record->level->value,
            'message' => $record->message,
            'channel' => $record->channel,
            'context' => $record->context ?: new \stdClass(),
            'extra' => $record->extra ?: new \stdClass(),
        ];

        // Flatten correlation_id to top level for easier querying
        if (isset($record->context['correlation_id'])) {
            $data['correlation_id'] = $record->context['correlation_id'];
        }

        // Flatten request_id to top level
        if (isset($record->context['request_id'])) {
            $data['request_id'] = $record->context['request_id'];
        }

        return $this->toJson($data) . "\n";
    }
}
