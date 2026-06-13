# RCA Task: Concurrent Booking Stress Test — 0% Success Rate (CI)

## Role

You are operating inside the `soleil-hostel` repo under its `CLAUDE.md` constitution. Read and follow `CLAUDE.md`, `.claude/memory/global-invariants.md`, `.claude/memory/repo-truth.md`, and `docs/agents/AGENT_LEARNINGS_OPERATING_RULES.md` (tag-scoped reads per R-01–R-04) before acting. This is a **runtime/CI failure** → output style is `.claude/output-styles/rca.md`. Tag every finding `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]`, or `[ACTION]`.

This investigation touches `CreateBookingService` and the booking-overlap locking path — per the escalation rules, **stop and confirm with the user before changing booking overlap logic**, lock acquisition order, retry counts/timeouts, or any migration/exclusion-constraint definitions. Diagnosis and a proposed fix plan do not require pre-approval; applying the fix to overlap logic does.

## Symptom (raw CI log, ground truth — do not re-derive from memory)

```
Backend accepting connections after 1s
Starting concurrent booking stress test...
Total concurrent requests: 50
Room ID: 1
Date range: 2026-06-15 to 2026-06-17

Successful bookings: 0
Failed bookings: 50
Status code distribution:
  HTTP 422 (Unprocessable Entity): 9 requests
  HTTP 0 (Other): 41 requests

First non-429 response:
  Request 1: HTTP 422 — {"success":false,"message":"Không thể tạo booking sau 3 lần thử do xung đột database. Vui lòng thử lại."}

TEST FAILED: no booking succeeded.
```

Expected outcome per `backend/tests/stubs/concurrent_booking_test.php`: **exactly 1 of 50** concurrent requests for the same room/date range returns HTTP 201; the rest return 409/422 conflict. `successCount === 0` is a hard failure — the single-winner locking invariant was never exercised.

## Two distinct failure signals — investigate both

1. **9× HTTP 422** — `CreateBookingService::createWithDeadlockRetry()` exhausted `MAX_RETRY_ATTEMPTS = 3` and threw the Vietnamese "xung đột database" `RuntimeException`. This means `PDOException`s were classified as retryable (`deadlock`/`serialization`/`lock_timeout`/`sqlite_busy`) on **every** attempt for these requests, for all 3 attempts, with nobody ever winning the lock. Under correct `SELECT ... FOR UPDATE` semantics with a half-open `[check_in, check_out)` exclusion window, contenders should *block* and then see the now-committed conflicting row (→ clean 409/422 "already booked"), not perpetually re-throw retryable PDO errors. Determine: which SQLSTATE is actually firing here, and why retries never converge.

2. **41× HTTP 0** — these are connection-level failures (curl never got an HTTP response), not application errors. Given the harness is `php artisan serve --no-reload` with `PHP_CLI_SERVER_WORKERS=4` against Postgres, candidate causes include: PHP built-in server's connection backlog/accept queue overflowing at 50 concurrent (4 workers), Postgres `max_connections` exhaustion if each request holds a connection for the full retry+backoff duration (worst case ~`100+200+400` ms backoff × FOR UPDATE wait per attempt), a worker crashing/segfaulting on the `40001`/`40P01`/exclusion-constraint (`23P01`) path and not respawning under `--no-reload`, or `DB::transaction()` deadlocking the PHP process itself (e.g., a fatal error inside the closure leaving a connection in a bad transaction state that hangs subsequent requests on that worker).

The two signals may share one root cause (e.g., a worker that throws an uncaught/fatal error on the conflict path becomes permanently wedged, producing 422s from the surviving workers and HTTP 0 from requests routed to the wedged one).

## Investigation steps

1. `soleil-ai-review-engine_query({query: "concurrent booking creation lock"})` and `soleil-ai-review-engine_context({name: "CreateBookingService"})` — confirm callers, the `overlappingBookings` scope, and the `withLock()` implementation (`Booking::query()->overlappingBookings(...)->withLock()`).
2. Read the migration defining the exclusion constraint on `bookings` (per `CLAUDE.md`: half-open interval, `deleted_at IS NULL`) and confirm its SQLSTATE on violation is `23P01`. Check whether `classifyDatabaseError()` / `isRetryableException()` in `CreateBookingService.php` (lines ~190–256) handle `23P01` at all — if `23P01` falls through to `'other'` and `'other'` is treated as non-retryable, confirm that path actually surfaces as a clean business error rather than an uncaught exception bubbling to a 500/connection-drop.
3. Reproduce locally if possible: run `backend/tests/stubs/concurrent_booking_test.php` against a local `php artisan serve --no-reload` with `PHP_CLI_SERVER_WORKERS=4` + Postgres, and capture `storage/logs/laravel.log` plus Postgres logs (`log_lock_waits`, `log_min_error_statement`) for the actual SQLSTATEs and any fatal errors during the burst.
4. Check `config/database.php` / `.env.testing` for the Postgres connection pool/timeout settings (`pgsql.options`, `statement_timeout`, `lock_timeout`, persistent connections) — a too-low `lock_timeout` combined with FOR UPDATE contention across 50 simultaneous transactions could be the source of the retryable errors that never converge.
5. Check whether `assertPendingLimitNotExceeded()` (locks the `users` row + a `lockForUpdate()` subquery on `bookings`) introduces a **second** lock acquired before the room/date overlap lock — if lock acquisition order isn't consistent across all 50 concurrent transactions, this is a textbook deadlock generator (`40P01`) that retries can't fix because every retry re-acquires locks in the same racy order. `[CONFIRMED]`/`[INFERRED]` whether this is the deadlock source.
6. For the HTTP 0s: `soleil-ai-review-engine_query({query: "PHP built-in server worker connection handling"})` and inspect whether any code path in the request lifecycle (middleware, observers, `BookingObserver`, the `trg_booking_set_location` trigger) can throw *outside* the try/catch in `createWithDeadlockRetry`, which only catches `PDOException` — any other `Throwable` (e.g., `QueryException` not matching `PDOException`, or a `TypeError`) would propagate uncaught and could crash a worker.
7. Run `soleil-ai-review-engine_detect_changes({scope: "compare", base_ref: "main"})` to check whether this is a regression from a recent change to `CreateBookingService`, the exclusion constraint migration, or `database.php`/CI workflow, vs. a long-standing latent bug newly exposed by CI infra changes (e.g., the `PHP_CLI_SERVER_WORKERS`/`--no-reload` setup itself, per the comment in `tests.yml` lines 296–303 — was this recently added?).

## Deliverable (rca.md format)

- Root cause(s), each tagged `[CONFIRMED]`/`[INFERRED]`/`[UNPROVEN]`, for both the 422-exhaustion signal and the HTTP-0 signal, including whether they're the same root cause.
- Exact SQLSTATEs observed and where `classifyDatabaseError`/`isRetryableException` mis-handle them, if applicable.
- Whether `QueryException`-vs-`PDOException` catch-type mismatch or lock-ordering in `assertPendingLimitNotExceeded` is implicated.
- A proposed fix plan as `[ACTION]` items, explicitly flagging which actions touch booking-overlap/locking logic (require user confirmation before applying per `CLAUDE.md` escalation rules) vs. which are safe to apply directly (e.g., catching a broader exception type, adding `23P01` to the classifier as non-retryable-but-mapped-to-409, fixing lock ordering, CI/server config tuning).
- Any out-of-scope issues discovered along the way → log to `docs/FINDINGS_BACKLOG.md`, do not fix inline.
