<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor to add context information to all log records.
 *
 * This processor adds environment, application, and request context
 * to every log entry for better filtering and analysis.
 */
class ContextProcessor implements ProcessorInterface
{
    /**
     * Process a log record.
     *
     * @param  \Monolog\LogRecord  $record
     * @return \Monolog\LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = array_merge($record->extra, [
            'environment' => config('app.env'),
            'app_name' => config('app.name'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_name' => gethostname(),
            'process_id' => getmypid(),
            'timestamp_unix' => time(),
        ]);

        // Add request context if available
        if (app()->runningInConsole() === false && request()) {
            $extra['request'] = [
                'method' => request()->method(),
                'uri' => request()->getRequestUri(),
                'ip' => request()->ip(),
            ];
        }

        return $record->with(extra: $extra);
    }
}
