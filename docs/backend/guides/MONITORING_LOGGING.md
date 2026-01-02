# Monitoring & Logging Infrastructure

> **Last Updated:** January 2, 2026 | **Laravel 11** | **Status:** Implementation Guide

## Table of Contents

1. [Analysis & Rationale](#analysis--rationale)
2. [Architecture Overview](#architecture-overview)
3. [Step-by-Step Implementation](#step-by-step-implementation)
4. [Code Examples](#code-examples)
5. [Configuration Files](#configuration-files)
6. [Testing Strategy](#testing-strategy)
7. [Production Recommendations](#production-recommendations)
8. [Potential Pitfalls](#potential-pitfalls)

---

## Analysis & Rationale

### Why Default Laravel Logging is Insufficient

Laravel's built-in `Log::info()` approach has critical limitations for production hostel booking systems:

```php
// ❌ INSUFFICIENT - Scattered, unstructured, no context
Log::info('Booking created');
Log::error('Payment failed');
```

| Problem                 | Impact                                               | Root Cause                         |
| ----------------------- | ---------------------------------------------------- | ---------------------------------- |
| **No Correlation**      | Can't trace a request through HTTP → Service → Queue | Each log is isolated               |
| **Unstructured Text**   | Can't search/filter in log aggregators               | Human-readable ≠ machine-parseable |
| **Missing Context**     | "Payment failed" - which user? which room?           | Developer must remember to add     |
| **No Performance Data** | "Booking slow" - how slow? which query?              | No automatic instrumentation       |
| **Async Blindness**     | Queue job logs disconnected from HTTP request        | No correlation ID propagation      |
| **Silent Failures**     | Job fails at 3am, nobody knows until user complains  | No alerting integration            |

### The Real-World Problem

```
User: "I tried to book Room 5 at 2:47pm but got an error"

Without proper logging:
┌─────────────────────────────────────────────────────────────────┐
│ [2026-01-02 14:47:23] ERROR: Payment failed                     │
│ [2026-01-02 14:47:24] ERROR: Payment failed                     │
│ [2026-01-02 14:47:25] ERROR: Booking error                      │
│                                                                 │
│ Which one? Which user? What was the actual error? 🤷            │
└─────────────────────────────────────────────────────────────────┘

With proper observability:
┌─────────────────────────────────────────────────────────────────┐
│ {                                                               │
│   "timestamp": "2026-01-02T14:47:23.456Z",                     │
│   "level": "error",                                            │
│   "message": "Payment gateway timeout",                         │
│   "correlation_id": "req_abc123",                              │
│   "user_id": 42,                                               │
│   "room_id": 5,                                                │
│   "booking_dates": {"check_in": "2026-01-15", "check_out":...},│
│   "gateway": "stripe",                                         │
│   "response_time_ms": 30045,                                   │
│   "error_code": "GATEWAY_TIMEOUT",                             │
│   "trace_id": "span_xyz789"                                    │
│ }                                                               │
│                                                                 │
│ → Instant diagnosis: Stripe timed out after 30s                 │
└─────────────────────────────────────────────────────────────────┘
```

### What We Need: The Three Pillars of Observability

```
                    ┌─────────────────────────────────────┐
                    │         OBSERVABILITY               │
                    │   "What is happening and why?"      │
                    └─────────────────────────────────────┘
                                    │
            ┌───────────────────────┼───────────────────────┐
            ▼                       ▼                       ▼
    ┌───────────────┐       ┌───────────────┐       ┌───────────────┐
    │    LOGS       │       │   METRICS     │       │    TRACES     │
    │  (Events)     │       │  (Aggregates) │       │  (Journeys)   │
    │               │       │               │       │               │
    │ • Structured  │       │ • Response    │       │ • Request →   │
    │   JSON        │       │   times       │       │   Service →   │
    │ • Contextual  │       │ • Error rates │       │   Queue →     │
    │ • Searchable  │       │ • DB queries  │       │   External    │
    │ • Correlated  │       │ • Throughput  │       │ • Waterfall   │
    └───────────────┘       └───────────────┘       └───────────────┘
```

---

## Architecture Overview

### Component Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         SOLEIL HOSTEL                                    │
│                                                                         │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                  │
│  │   HTTP      │───▶│   Services  │───▶│   Queue     │                  │
│  │  Request    │    │   Layer     │    │   Jobs      │                  │
│  └──────┬──────┘    └──────┬──────┘    └──────┬──────┘                  │
│         │                  │                  │                          │
│         │    Correlation ID: req_abc123       │                          │
│         ▼                  ▼                  ▼                          │
│  ┌──────────────────────────────────────────────────────────────┐       │
│  │                   LOGGING LAYER                               │       │
│  │  • CorrelationIdMiddleware (generates/propagates ID)         │       │
│  │  • ContextProcessor (adds user, room, booking context)       │       │
│  │  • PerformanceMiddleware (measures response time)            │       │
│  │  • QueryLogger (captures slow queries)                        │       │
│  └──────────────────────────────────────────────────────────────┘       │
│                              │                                           │
└──────────────────────────────┼───────────────────────────────────────────┘
                               │
                               ▼
        ┌──────────────────────────────────────────────────────────┐
        │                 OUTPUTS                                   │
        │                                                           │
        │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐      │
        │  │ JSON    │  │ Sentry  │  │ Metrics │  │ Alerts  │      │
        │  │ Logs    │  │ (Errors)│  │ (Pulse) │  │ (Slack) │      │
        │  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘      │
        │       │            │            │            │            │
        │       ▼            ▼            ▼            ▼            │
        │    ELK/         Sentry       Prometheus    PagerDuty     │
        │   Datadog        Cloud        /Grafana      /Slack       │
        └──────────────────────────────────────────────────────────┘
```

### Data Flow for a Booking Request

```
1. Request arrives → CorrelationIdMiddleware generates req_abc123
                  │
2. Controller     │  {"correlation_id": "req_abc123", "event": "booking.started"}
                  │
3. RoomService    │  {"correlation_id": "req_abc123", "query_ms": 45, "room_id": 5}
                  │
4. BookingService │  {"correlation_id": "req_abc123", "event": "availability.checked"}
                  │
5. PaymentGateway │  {"correlation_id": "req_abc123", "external_call_ms": 850}
                  │
6. Queue dispatch │  Job metadata includes correlation_id
                  │
7. Queue worker   │  {"correlation_id": "req_abc123", "job": "SendConfirmationEmail"}
                  │
8. Response       │  {"correlation_id": "req_abc123", "total_ms": 1247, "status": 201}
```

---

## Step-by-Step Implementation

### Step 1: Install Dependencies

```bash
cd backend

# Core: Structured logging & error tracking
composer require sentry/sentry-laravel

# Optional: Performance monitoring (choose one)
composer require laravel/pulse           # Laravel native (simple)
# OR
composer require spatie/laravel-ray      # Development debugging
```

### Step 2: Create Correlation ID Middleware

```bash
php artisan make:middleware AddCorrelationId
```

### Step 3: Create Context Logging Processor

```bash
php artisan make:class Logging/ContextProcessor
```

### Step 4: Configure Logging Channels

Update `config/logging.php` with JSON formatter and context processor.

### Step 5: Create Query Logger Service Provider

```bash
php artisan make:provider QueryLogServiceProvider
```

### Step 6: Create Performance Middleware

```bash
php artisan make:middleware LogPerformance
```

### Step 7: Configure Sentry Integration

```bash
php artisan sentry:publish --dsn=YOUR_SENTRY_DSN
```

### Step 8: Create Queue Job Tracing

Add middleware for queue jobs to propagate correlation ID.

### Step 9: Create Health Check Endpoint

Add monitoring endpoint with basic system metrics.

### Step 10: Configure Alerts

Set up Sentry alerts for critical booking failures.

---

## Code Examples

### File: `app/Http/Middleware/AddCorrelationId.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates and propagates correlation ID for distributed tracing.
 *
 * The correlation ID follows the request through:
 * - HTTP request/response
 * - Service layer
 * - Queue jobs
 * - External API calls
 *
 * Format: req_{ulid} (e.g., req_01ARZ3NDEKTSV4RRFFQ69G5FAV)
 */
class AddCorrelationId
{
    public const HEADER_NAME = 'X-Correlation-ID';
    public const CONTEXT_KEY = 'correlation_id';

    public function handle(Request $request, Closure $next): Response
    {
        // Use existing correlation ID (from upstream service) or generate new
        $correlationId = $request->header(self::HEADER_NAME)
            ?? 'req_' . Str::ulid()->toBase32();

        // Store in request for access throughout the application
        $request->attributes->set(self::CONTEXT_KEY, $correlationId);

        // Add to Laravel's logging context (available in all Log:: calls)
        Log::shareContext([
            self::CONTEXT_KEY => $correlationId,
        ]);

        // Store in app container for queue job propagation
        app()->instance(self::CONTEXT_KEY, $correlationId);

        // Log request start
        Log::info('Request started', [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $response = $next($request);

        // Add correlation ID to response headers for client debugging
        $response->headers->set(self::HEADER_NAME, $correlationId);

        return $response;
    }
}
```

### File: `app/Http/Middleware/LogPerformance.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs performance metrics for each request.
 *
 * Captures:
 * - Total response time
 * - Memory usage
 * - HTTP status code
 * - Route name
 */
class LogPerformance
{
    /**
     * Slow request threshold in milliseconds.
     */
    private const SLOW_THRESHOLD_MS = 1000;

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsedMb = round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2);

        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName() ?? 'unnamed',
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'memory_mb' => $memoryUsedMb,
            'user_id' => $request->user()?->id,
        ];

        // Log level based on response time
        if ($durationMs >= self::SLOW_THRESHOLD_MS) {
            Log::warning('Slow request detected', $context);
        } else {
            Log::info('Request completed', $context);
        }

        return $response;
    }
}
```

### File: `app/Logging/ContextProcessor.php`

```php
<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that adds global context to all log entries.
 *
 * Adds:
 * - Application name and environment
 * - Hostname for multi-server identification
 * - Timestamp in ISO 8601 format
 * - PHP version and Laravel version
 */
class ContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = array_merge($record->extra, [
            'app_name' => config('app.name'),
            'environment' => config('app.env'),
            'hostname' => gethostname(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ]);

        return $record->with(extra: $extra);
    }
}
```

### File: `app/Logging/SensitiveDataProcessor.php`

```php
<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Redacts sensitive data from log entries.
 *
 * Prevents accidental logging of:
 * - Passwords, tokens, secrets
 * - Credit card numbers
 * - Personal identification (SSN, etc.)
 */
class SensitiveDataProcessor implements ProcessorInterface
{
    /**
     * Keys to completely redact.
     */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'api_key',
        'secret',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'authorization',
    ];

    /**
     * Patterns to mask (show partial).
     */
    private const MASKED_PATTERNS = [
        // Credit card: show last 4
        '/\b\d{12,16}\b/' => fn($m) => str_repeat('*', strlen($m[0]) - 4) . substr($m[0], -4),
        // Email: mask username
        '/\b([^@]+)@([^@]+)\b/' => fn($m) => substr($m[1], 0, 2) . '***@' . $m[2],
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->redactArray($record->context);
        $extra = $this->redactArray($record->extra);
        $message = $this->maskPatterns($record->message);

        return $record->with(
            message: $message,
            context: $context,
            extra: $extra
        );
    }

    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactArray($value);
            } elseif ($this->shouldRedact($key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_string($value)) {
                $data[$key] = $this->maskPatterns($value);
            }
        }

        return $data;
    }

    private function shouldRedact(string $key): bool
    {
        $normalizedKey = strtolower(str_replace(['-', '_'], '', $key));

        foreach (self::REDACTED_KEYS as $redactedKey) {
            if (str_contains($normalizedKey, strtolower(str_replace('_', '', $redactedKey)))) {
                return true;
            }
        }

        return false;
    }

    private function maskPatterns(string $value): string
    {
        foreach (self::MASKED_PATTERNS as $pattern => $replacement) {
            $value = preg_replace_callback($pattern, $replacement, $value);
        }

        return $value;
    }
}
```

### File: `app/Providers/QueryLogServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Logs database queries for performance analysis.
 *
 * Features:
 * - Logs all queries in local/staging
 * - Only slow queries in production (>200ms)
 * - Includes query bindings and execution time
 */
class QueryLogServiceProvider extends ServiceProvider
{
    /**
     * Slow query threshold in milliseconds.
     */
    private const SLOW_QUERY_THRESHOLD_MS = 200;

    public function boot(): void
    {
        // Skip in testing to avoid noise
        if (app()->runningUnitTests()) {
            return;
        }

        DB::listen(function (QueryExecuted $query) {
            $this->logQuery($query);
        });
    }

    private function logQuery(QueryExecuted $query): void
    {
        $timeMs = round($query->time, 2);
        $isSlow = $timeMs >= self::SLOW_QUERY_THRESHOLD_MS;

        // In production, only log slow queries
        if (app()->isProduction() && !$isSlow) {
            return;
        }

        $context = [
            'sql' => $query->sql,
            'bindings' => $this->formatBindings($query->bindings),
            'time_ms' => $timeMs,
            'connection' => $query->connectionName,
        ];

        if ($isSlow) {
            Log::warning('Slow query detected', $context);
        } else {
            Log::debug('Query executed', $context);
        }
    }

    /**
     * Format bindings for logging, handling special types.
     */
    private function formatBindings(array $bindings): array
    {
        return array_map(function ($binding) {
            if ($binding instanceof \DateTimeInterface) {
                return $binding->format('Y-m-d H:i:s');
            }

            if (is_object($binding)) {
                return get_class($binding);
            }

            return $binding;
        }, $bindings);
    }
}
```

### File: `app/Jobs/Middleware/PropagateCorrelationId.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use App\Http\Middleware\AddCorrelationId;

/**
 * Queue job middleware that propagates correlation ID.
 *
 * Ensures logs from queue jobs can be traced back to the
 * originating HTTP request.
 *
 * Usage in job class:
 * public function middleware(): array
 * {
 *     return [new PropagateCorrelationId($this->correlationId)];
 * }
 */
class PropagateCorrelationId
{
    public function __construct(
        private readonly ?string $correlationId = null
    ) {}

    public function handle(object $job, Closure $next): void
    {
        $correlationId = $this->correlationId
            ?? $job->correlationId  // From job property
            ?? 'job_' . uniqid();   // Fallback for jobs without correlation

        // Add to logging context
        Log::shareContext([
            AddCorrelationId::CONTEXT_KEY => $correlationId,
        ]);

        Log::info('Job started', [
            'job' => get_class($job),
            'job_id' => $job->job?->getJobId(),
            'queue' => $job->queue ?? 'default',
            'attempts' => $job->attempts(),
        ]);

        $startTime = microtime(true);

        try {
            $next($job);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Job completed', [
                'job' => get_class($job),
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Job failed', [
                'job' => get_class($job),
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            throw $e;
        }
    }
}
```

### File: `app/Jobs/Concerns/TracksCorrelation.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Http\Middleware\AddCorrelationId;
use App\Jobs\Middleware\PropagateCorrelationId;

/**
 * Trait for queue jobs that need correlation ID tracking.
 *
 * Usage:
 * class SendBookingConfirmation implements ShouldQueue
 * {
 *     use TracksCorrelation;
 *
 *     public function __construct(public Booking $booking)
 *     {
 *         $this->captureCorrelationId();
 *     }
 * }
 */
trait TracksCorrelation
{
    public ?string $correlationId = null;

    /**
     * Capture correlation ID from current request context.
     * Call this in job constructor.
     */
    protected function captureCorrelationId(): void
    {
        $this->correlationId = app()->bound(AddCorrelationId::CONTEXT_KEY)
            ? app(AddCorrelationId::CONTEXT_KEY)
            : null;
    }

    /**
     * Add correlation middleware to job.
     */
    public function middleware(): array
    {
        return [
            new PropagateCorrelationId($this->correlationId),
        ];
    }
}
```

### File: `app/Services/Concerns/LogsOperations.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for service classes that need structured operation logging.
 *
 * Provides consistent logging patterns for CRUD operations
 * with automatic context extraction from models.
 */
trait LogsOperations
{
    /**
     * Log the start of an operation.
     */
    protected function logOperationStart(string $operation, array $context = []): float
    {
        Log::info("{$this->getServiceName()}: {$operation} started", $context);

        return microtime(true);
    }

    /**
     * Log successful completion of an operation.
     */
    protected function logOperationSuccess(
        string $operation,
        float $startTime,
        array $context = []
    ): void {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info("{$this->getServiceName()}: {$operation} completed", array_merge($context, [
            'duration_ms' => $durationMs,
        ]));
    }

    /**
     * Log operation failure.
     */
    protected function logOperationFailure(
        string $operation,
        \Throwable $exception,
        array $context = []
    ): void {
        Log::error("{$this->getServiceName()}: {$operation} failed", array_merge($context, [
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]));
    }

    /**
     * Extract loggable context from a model.
     */
    protected function modelContext(Model $model, array $additional = []): array
    {
        return array_merge([
            'model' => get_class($model),
            'model_id' => $model->getKey(),
        ], $additional);
    }

    /**
     * Get service name for log messages.
     */
    protected function getServiceName(): string
    {
        return class_basename(static::class);
    }
}
```

### File: `app/Services/BookingService.php` (Example Usage)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Services\Concerns\LogsOperations;
use App\Events\BookingCreated;
use App\Exceptions\BookingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    use LogsOperations;

    /**
     * Create a new booking with comprehensive logging.
     *
     * @throws BookingException
     */
    public function createBooking(array $data, int $userId): Booking
    {
        $startTime = $this->logOperationStart('createBooking', [
            'user_id' => $userId,
            'room_id' => $data['room_id'],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
        ]);

        try {
            return DB::transaction(function () use ($data, $userId, $startTime) {
                // Step 1: Check room availability
                Log::debug('BookingService: Checking room availability', [
                    'room_id' => $data['room_id'],
                    'dates' => [$data['check_in'], $data['check_out']],
                ]);

                $room = Room::lockForUpdate()->findOrFail($data['room_id']);

                if (!$this->isRoomAvailable($room, $data['check_in'], $data['check_out'])) {
                    throw BookingException::roomNotAvailable($room->id, $data['check_in'], $data['check_out']);
                }

                Log::debug('BookingService: Room available, creating booking');

                // Step 2: Create booking
                $booking = Booking::create([
                    'user_id' => $userId,
                    'room_id' => $data['room_id'],
                    'check_in' => $data['check_in'],
                    'check_out' => $data['check_out'],
                    'status' => 'confirmed',
                    'total_price' => $this->calculatePrice($room, $data['check_in'], $data['check_out']),
                ]);

                // Step 3: Dispatch event (async email, etc.)
                event(new BookingCreated($booking));

                $this->logOperationSuccess('createBooking', $startTime, [
                    'booking_id' => $booking->id,
                    'room_id' => $room->id,
                    'total_price' => $booking->total_price,
                ]);

                return $booking;
            });
        } catch (BookingException $e) {
            $this->logOperationFailure('createBooking', $e, [
                'room_id' => $data['room_id'],
                'user_id' => $userId,
                'reason' => 'business_rule_violation',
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->logOperationFailure('createBooking', $e, [
                'room_id' => $data['room_id'],
                'user_id' => $userId,
                'reason' => 'unexpected_error',
            ]);
            throw $e;
        }
    }

    // ... other methods
}
```

### File: `app/Http/Controllers/HealthController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

/**
 * Health check endpoint for monitoring systems.
 *
 * Returns system status for:
 * - Load balancer health checks
 * - Kubernetes liveness/readiness probes
 * - External monitoring services
 */
class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = !in_array(false, array_column($checks, 'healthy'));
        $statusCode = $healthy ? 200 : 503;

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'checks' => $checks,
            'metrics' => $this->getBasicMetrics(),
        ], $statusCode);
    }

    /**
     * Lightweight liveness probe (just confirms app is running).
     */
    public function liveness(): JsonResponse
    {
        return response()->json(['status' => 'alive']);
    }

    /**
     * Readiness probe (confirms app can serve traffic).
     */
    public function readiness(): JsonResponse
    {
        $dbHealthy = $this->checkDatabase()['healthy'];

        return response()->json([
            'status' => $dbHealthy ? 'ready' : 'not_ready',
        ], $dbHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            Cache::put('health_check', true, 10);
            $result = Cache::get('health_check');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => $result === true,
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getBasicMetrics(): array
    {
        return [
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'uptime_seconds' => defined('LARAVEL_START')
                ? round(microtime(true) - LARAVEL_START, 2)
                : null,
        ];
    }
}
```

---

## Configuration Files

### File: `config/logging.php`

```php
<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'daily,stderr')),
            'ignore_exceptions' => false,
        ],

        // Production: JSON formatted logs
        'production' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => env('LOG_DAYS', 14),
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                'includeStacktraces' => true,
            ],
            'tap' => [
                App\Logging\CustomizeFormatter::class,
            ],
        ],

        // Structured JSON for log aggregators
        'json' => [
            'driver' => 'single',
            'path' => storage_path('logs/app.json.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                'includeStacktraces' => true,
            ],
        ],

        // Stderr for Docker/containerized deployments
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => JsonFormatter::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [
                PsrLogMessageProcessor::class,
            ],
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAYS', 14),
            'replace_placeholders' => true,
        ],

        // Slow queries dedicated channel
        'slow_queries' => [
            'driver' => 'daily',
            'path' => storage_path('logs/slow-queries.log'),
            'level' => 'warning',
            'days' => 30,
            'formatter' => JsonFormatter::class,
        ],

        // Security events dedicated channel
        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'info',
            'days' => 90,
            'formatter' => JsonFormatter::class,
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
```

### File: `app/Logging/CustomizeFormatter.php`

```php
<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;

/**
 * Customizes Monolog logger with processors and formatters.
 */
class CustomizeFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            // Add custom processors
            $handler->pushProcessor(new ContextProcessor());
            $handler->pushProcessor(new SensitiveDataProcessor());

            // Use JSON formatter in production
            if (app()->isProduction()) {
                $handler->setFormatter(new JsonFormatter());
            }
        }
    }
}
```

### File: `config/sentry.php`

```php
<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Performance monitoring sample rate (0.0 to 1.0)
    // 0.1 = 10% of transactions
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    // Profile sampling rate (requires traces)
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    // Send default PII (user info)
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // Environment
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Release version
    'release' => env('SENTRY_RELEASE', env('APP_VERSION', '1.0.0')),

    // Breadcrumbs configuration
    'breadcrumbs' => [
        // Capture queries as breadcrumbs
        'sql_queries' => true,
        // Capture query bindings
        'sql_bindings' => true,
        // Capture log messages as breadcrumbs
        'logs' => true,
        // Capture queue job info
        'queue_info' => true,
        // Capture command info
        'command_info' => true,
        // Capture HTTP client requests
        'http_client_requests' => true,
    ],

    // Controllers to trace (for performance)
    'controllers_base_namespace' => 'App\\Http\\Controllers',

    // Ignore specific exceptions
    'ignore_exceptions' => [
        Illuminate\Auth\AuthenticationException::class,
        Illuminate\Validation\ValidationException::class,
        Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
    ],

    // Ignore specific transactions (reduce noise)
    'ignore_transactions' => [
        '/health',
        '/health/liveness',
        '/health/readiness',
        '/horizon/*',
    ],

    // Before send callback for filtering
    'before_send' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
        // Filter out specific errors if needed
        return $event;
    },

    // Before send transaction callback
    'before_send_transaction' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
        // Skip health checks from performance monitoring
        $transaction = $event->getTransaction();
        if ($transaction && str_starts_with($transaction, 'GET /health')) {
            return null;
        }

        return $event;
    },
];
```

### File: `.env` additions

```bash
# ===========================================
# MONITORING & LOGGING CONFIGURATION
# ===========================================

# Logging
LOG_CHANNEL=stack
LOG_STACK=daily,stderr
LOG_LEVEL=info
LOG_DAYS=14

# Slow query threshold (ms)
DB_SLOW_QUERY_THRESHOLD=200

# Sentry Error Tracking
SENTRY_LARAVEL_DSN=https://xxx@xxx.ingest.sentry.io/xxx
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_PROFILES_SAMPLE_RATE=0.1
SENTRY_ENVIRONMENT=production
SENTRY_RELEASE=1.0.0
SENTRY_SEND_DEFAULT_PII=false

# Laravel Pulse (if using)
PULSE_ENABLED=true
PULSE_PATH=pulse
PULSE_DOMAIN=

# External Log Shipping (optional)
LOG_DATADOG_API_KEY=
LOG_ELASTICSEARCH_HOST=
```

### File: `routes/api.php` additions

```php
<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Health check endpoints (no auth required)
Route::prefix('health')->withoutMiddleware(['auth:sanctum', 'throttle'])->group(function () {
    Route::get('/', [HealthController::class, 'check']);
    Route::get('/liveness', [HealthController::class, 'liveness']);
    Route::get('/readiness', [HealthController::class, 'readiness']);
});
```

### File: `app/Http/Kernel.php` (Middleware Registration)

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        // Add correlation ID FIRST (before other middleware)
        \App\Http\Middleware\AddCorrelationId::class,

        // ... existing middleware
        \Illuminate\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        // ...
    ];

    protected $middlewareGroups = [
        'api' => [
            // Performance logging for API routes
            \App\Http\Middleware\LogPerformance::class,

            // ... existing middleware
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];
}
```

### File: `app/Providers/AppServiceProvider.php` additions

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Add user context to Sentry when authenticated
        if (class_exists(\Sentry\SentrySdk::class)) {
            auth()->extend('sentry', function ($app, $name, array $config) {
                return new class extends \Illuminate\Auth\SessionGuard {
                    public function login($user, $remember = false)
                    {
                        parent::login($user, $remember);

                        \Sentry\configureScope(function (Scope $scope) use ($user): void {
                            $scope->setUser([
                                'id' => $user->id,
                                'email' => $user->email,
                                'role' => $user->role ?? 'user',
                            ]);
                        });
                    }
                };
            });
        }

        // Register custom log channel taps
        Log::extend('custom_json', function ($app, array $config) {
            return new \Monolog\Logger(
                $config['name'] ?? 'custom',
                [
                    new \Monolog\Handler\StreamHandler(
                        $config['path'] ?? storage_path('logs/custom.log')
                    ),
                ],
                [
                    new \App\Logging\ContextProcessor(),
                    new \App\Logging\SensitiveDataProcessor(),
                ]
            );
        });
    }
}
```

---

## Testing Strategy

### 1. Test Correlation ID Propagation

```php
<?php

namespace Tests\Feature\Logging;

use Tests\TestCase;
use App\Http\Middleware\AddCorrelationId;

class CorrelationIdTest extends TestCase
{
    public function test_correlation_id_generated_for_request(): void
    {
        $response = $this->getJson('/api/rooms');

        $response->assertHeader(AddCorrelationId::HEADER_NAME);
        $this->assertStringStartsWith('req_', $response->headers->get(AddCorrelationId::HEADER_NAME));
    }

    public function test_existing_correlation_id_is_preserved(): void
    {
        $existingId = 'req_existing123';

        $response = $this->withHeader(AddCorrelationId::HEADER_NAME, $existingId)
            ->getJson('/api/rooms');

        $response->assertHeader(AddCorrelationId::HEADER_NAME, $existingId);
    }

    public function test_correlation_id_appears_in_logs(): void
    {
        // Clear log file
        file_put_contents(storage_path('logs/laravel.log'), '');

        $response = $this->getJson('/api/rooms');
        $correlationId = $response->headers->get(AddCorrelationId::HEADER_NAME);

        $logContent = file_get_contents(storage_path('logs/laravel.log'));

        $this->assertStringContainsString($correlationId, $logContent);
    }
}
```

### 2. Test Slow Query Logging

```php
<?php

namespace Tests\Feature\Logging;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlowQueryLoggingTest extends TestCase
{
    public function test_slow_queries_are_logged(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Slow query')
                    && isset($context['time_ms'])
                    && $context['time_ms'] >= 200;
            });

        // Simulate slow query with pg_sleep or similar
        if (config('database.default') === 'pgsql') {
            DB::select('SELECT pg_sleep(0.3)'); // 300ms
        } else {
            DB::select('SELECT SLEEP(0.3)'); // MySQL
        }
    }
}
```

### 3. Test Sentry Error Reporting

```php
<?php

namespace Tests\Feature\Logging;

use Tests\TestCase;

class SentryIntegrationTest extends TestCase
{
    public function test_exceptions_are_reported_to_sentry(): void
    {
        // Create a test route that throws an exception
        $this->app['router']->get('/test-sentry', function () {
            throw new \RuntimeException('Test Sentry Integration');
        });

        // This should report to Sentry (check Sentry dashboard)
        $response = $this->get('/test-sentry');

        $response->assertStatus(500);
    }

    public function test_validation_exceptions_are_not_reported(): void
    {
        // Validation exceptions should be ignored per config
        $response = $this->postJson('/api/bookings', [
            // Invalid data
            'room_id' => 'not-an-integer',
        ]);

        $response->assertStatus(422);
        // Verify not in Sentry (manual check or mock)
    }
}
```

### 4. Test Queue Job Correlation

```php
<?php

namespace Tests\Feature\Logging;

use Tests\TestCase;
use App\Jobs\SendBookingConfirmation;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class QueueCorrelationTest extends TestCase
{
    public function test_queue_job_preserves_correlation_id(): void
    {
        Queue::fake();

        // Make request that dispatches job
        $correlationId = 'req_test123';

        $response = $this->withHeader('X-Correlation-ID', $correlationId)
            ->postJson('/api/bookings', [
                'room_id' => 1,
                'check_in' => now()->addDays(1)->toDateString(),
                'check_out' => now()->addDays(3)->toDateString(),
            ]);

        Queue::assertPushed(SendBookingConfirmation::class, function ($job) use ($correlationId) {
            return $job->correlationId === $correlationId;
        });
    }
}
```

### 5. Test Health Check Endpoint

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthCheckTest extends TestCase
{
    public function test_health_check_returns_healthy_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
            ])
            ->assertJsonStructure([
                'status',
                'timestamp',
                'version',
                'checks' => [
                    'database' => ['healthy', 'latency_ms'],
                    'cache' => ['healthy', 'latency_ms'],
                    'redis' => ['healthy'],
                ],
                'metrics',
            ]);
    }

    public function test_health_check_returns_unhealthy_on_db_failure(): void
    {
        // Temporarily break database connection
        config(['database.connections.pgsql.host' => 'invalid-host']);
        DB::purge('pgsql');

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'checks' => [
                    'database' => [
                        'healthy' => false,
                    ],
                ],
            ]);
    }

    public function test_liveness_probe_is_fast(): void
    {
        $start = microtime(true);

        $response = $this->getJson('/api/health/liveness');

        $duration = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(0.1, $duration, 'Liveness probe should be < 100ms');
    }
}
```

---

## Production Recommendations

### 1. Log Shipping Architecture

```
┌────────────────────────────────────────────────────────────────────┐
│                    PRODUCTION LOG PIPELINE                          │
│                                                                     │
│  Laravel App          Filebeat/Fluentd       Log Aggregator        │
│  ┌─────────┐         ┌─────────────┐        ┌─────────────┐        │
│  │ JSON    │────────▶│   Shipper   │───────▶│  ELK/       │        │
│  │ Logs    │         │             │        │  Datadog/   │        │
│  └─────────┘         └─────────────┘        │  CloudWatch │        │
│                                              └──────┬──────┘        │
│                                                     │               │
│                                              ┌──────▼──────┐        │
│                                              │  Dashboards │        │
│                                              │  & Alerts   │        │
│                                              └─────────────┘        │
└────────────────────────────────────────────────────────────────────┘
```

### 2. Sampling Strategy for High Traffic

```php
// config/logging.php - Add sampling for high-traffic endpoints
'sampled' => [
    'driver' => 'monolog',
    'handler' => \App\Logging\SampledHandler::class,
    'with' => [
        'sample_rate' => 0.1, // 10% of requests
        'always_log_errors' => true,
    ],
],
```

### 3. Alert Configuration

| Alert Type           | Condition                | Severity | Notify    |
| -------------------- | ------------------------ | -------- | --------- |
| Overbooking Attempt  | `booking.conflict` event | Critical | PagerDuty |
| Payment Failure Rate | > 5% in 5min             | High     | Slack     |
| API Error Rate       | > 1% 5xx in 5min         | High     | Slack     |
| Slow Response        | p99 > 5s for 10min       | Medium   | Slack     |
| Database Connection  | Health check fails       | Critical | PagerDuty |
| Queue Backlog        | > 1000 jobs pending      | Medium   | Slack     |

### 4. Performance Impact Minimization

| Technique         | Implementation                                    |
| ----------------- | ------------------------------------------------- |
| **Async Logging** | Use `async` Monolog handler for non-critical logs |
| **Sampling**      | Log 10% of successful requests, 100% of errors    |
| **Buffering**     | Buffer logs and flush in batches                  |
| **Compression**   | Enable gzip for log shipping                      |
| **Retention**     | Auto-delete logs > 14 days (keep errors 90 days)  |

### 5. Environment-Specific Configuration

```php
// Production
LOG_CHANNEL=stack
LOG_STACK=stderr,sentry
LOG_LEVEL=info
SENTRY_TRACES_SAMPLE_RATE=0.05  // 5% sampling

// Staging
LOG_CHANNEL=stack
LOG_STACK=daily,stderr
LOG_LEVEL=debug
SENTRY_TRACES_SAMPLE_RATE=0.5  // 50% sampling

// Local
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=debug
SENTRY_TRACES_SAMPLE_RATE=1.0  // 100% (all requests)
```

---

## Potential Pitfalls

### 1. Logging Sensitive Data

**Problem:** Accidentally logging passwords, tokens, credit cards.

**Solution:** Use `SensitiveDataProcessor` (provided above) and review logs regularly.

```php
// ❌ WRONG
Log::info('User login', ['password' => $request->password]);

// ✅ CORRECT - processor will redact automatically
Log::info('User login', $request->only(['email', 'password']));
// Output: {"email": "user@example.com", "password": "[REDACTED]"}
```

### 2. Log Volume Explosion

**Problem:** Debug logging in production fills disk/costs money.

**Solution:** Environment-aware log levels and sampling.

```php
// Only log queries in development
if (app()->isLocal()) {
    DB::enableQueryLog();
}
```

### 3. Alert Fatigue

**Problem:** Too many alerts = ignored alerts.

**Solution:** Tiered alerting with proper thresholds.

```php
// Sentry: Only notify on new issues, not every occurrence
'before_send' => function ($event, $hint) {
    // Don't send if same error happened < 5 min ago
    $fingerprint = $event->getFingerprint();
    $cacheKey = 'sentry_rate_limit_' . md5(json_encode($fingerprint));

    if (Cache::has($cacheKey)) {
        return null; // Skip this occurrence
    }

    Cache::put($cacheKey, true, now()->addMinutes(5));
    return $event;
},
```

### 4. Correlation ID Not Propagating to External Services

**Problem:** Calls to Stripe, Twilio, etc. lose correlation.

**Solution:** Add correlation ID to external API headers.

```php
// In your HTTP client wrapper
Http::withHeaders([
    'X-Correlation-ID' => app(AddCorrelationId::CONTEXT_KEY),
])->post('https://api.stripe.com/...');
```

### 5. Performance Impact of Logging

**Problem:** Synchronous file writes block request.

**Solution:** Use async handlers in production.

```php
// config/logging.php
'async' => [
    'driver' => 'monolog',
    'handler' => \Monolog\Handler\BufferHandler::class,
    'with' => [
        'handler' => new \Monolog\Handler\StreamHandler(
            storage_path('logs/laravel.log')
        ),
        'bufferLimit' => 100, // Flush every 100 logs
    ],
],
```

### 6. Missing Context in Queue Jobs

**Problem:** Job fails but no user/booking context in logs.

**Solution:** Always include context in job properties.

```php
class SendBookingConfirmation implements ShouldQueue
{
    use TracksCorrelation;

    public function __construct(
        public Booking $booking,
        public int $userId,  // Include for logging
    ) {
        $this->captureCorrelationId();
    }

    public function handle(): void
    {
        Log::info('Sending confirmation', [
            'booking_id' => $this->booking->id,
            'user_id' => $this->userId,
            'room_id' => $this->booking->room_id,
        ]);

        // ... send email
    }
}
```

---

## Summary Checklist

| Step                               | Status | Priority |
| ---------------------------------- | ------ | -------- |
| Install Sentry package             | ⬜     | High     |
| Create CorrelationId middleware    | ⬜     | High     |
| Create LogPerformance middleware   | ⬜     | High     |
| Configure JSON logging             | ⬜     | High     |
| Create SensitiveDataProcessor      | ⬜     | High     |
| Create QueryLogServiceProvider     | ⬜     | Medium   |
| Create queue job correlation trait | ⬜     | Medium   |
| Create health check endpoint       | ⬜     | Medium   |
| Configure Sentry breadcrumbs       | ⬜     | Medium   |
| Set up log shipping (ELK/Datadog)  | ⬜     | Low      |
| Configure alerting rules           | ⬜     | Low      |
| Add integration tests              | ⬜     | Low      |

---

## Quick Start Commands

```bash
# Install dependencies
composer require sentry/sentry-laravel

# Publish Sentry config
php artisan sentry:publish --dsn=YOUR_DSN

# Create middleware
php artisan make:middleware AddCorrelationId
php artisan make:middleware LogPerformance

# Create service provider
php artisan make:provider QueryLogServiceProvider

# Register provider in bootstrap/providers.php
# Add middleware to Kernel.php

# Test
php artisan test --filter=CorrelationId
php artisan test --filter=HealthCheck

# Verify in browser
curl http://localhost:8000/api/health | jq
```

This infrastructure provides **true observability**: answering "What exactly is happening and why?" with minimal overhead while being production-grade and maintainable.
