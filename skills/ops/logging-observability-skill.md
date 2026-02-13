# Logging and Observability Skill

Use this skill when adding logs, tracing request flow, or changing monitoring-related backend behavior.

## When to Use This Skill

- You add or change backend logging statements.
- You modify correlation ID, performance logging, or query logging behavior.
- You touch health endpoints or monitoring-related middleware/providers.

## Non-negotiables

- Preserve request tracing via correlation IDs.
  - `AddCorrelationId` is globally prepended middleware.
- Keep structured logging channels and processors usable.
  - `json`, `performance`, `query`, `security` channels exist in logging config.
- Keep sensitive data masking active.
  - `SensitiveDataProcessor` should remain in relevant channel processors.
- Do not log secrets/tokens/passwords in plain text.
- Keep performance and query logging meaningful but not noisy by default.

Channel intent guide:

- `performance`: request latency, memory, response status patterns.
- `query`: slow query diagnostics and optional full query traces.
- `security`: auth and suspicious activity events with masked context.

## Implementation Checklist

1. Add logs with actionable context (IDs, status, durations, resource identifiers).
2. Ensure correlation ID is propagated through request lifecycle.
3. Choose the correct channel (`performance`, `query`, `security`, or default stack).
4. Confirm sensitive fields are masked or excluded from context.
5. If query/perf behavior changes, verify thresholds and environment flags.
6. Update monitoring tests when observability behavior changes.
7. Keep log volume practical for the target environment.
8. Ensure response headers still include correlation IDs for API requests.

## Verification / DoD

```bash
# Monitoring and logging related tests
cd backend && php artisan test tests/Feature/MonitoringLoggingTest.php
cd backend && php artisan test tests/Feature/HealthCheck/HealthControllerTest.php

# Baseline repo gates
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

## Common Failure Modes

- Logging without identifiers, making incident triage slow.
- Dropping correlation-id middleware order or response header propagation.
- High-volume debug logging in production paths.
- Leaking auth/session/token data in context fields.
- Adding logs but skipping test updates for health/monitoring behavior.
- Logging query bindings without masking sensitive values.
- Losing request tracing continuity across middleware and service logs.

Minimal logging payload pattern:

```php
Log::info('Booking confirmed', [
    'booking_id' => $booking->id,
    'user_id' => $booking->user_id,
]);
```

## References

- `../../AGENTS.md`
- `../../backend/app/Http/Middleware/AddCorrelationId.php`
- `../../backend/app/Http/Middleware/LogPerformance.php`
- `../../backend/app/Providers/QueryLogServiceProvider.php`
- `../../backend/config/logging.php`
- `../../backend/app/Logging/ContextProcessor.php`
- `../../backend/app/Logging/SensitiveDataProcessor.php`
- `../../backend/tests/Feature/MonitoringLoggingTest.php`
- `../../backend/tests/Feature/HealthCheck/HealthControllerTest.php`
