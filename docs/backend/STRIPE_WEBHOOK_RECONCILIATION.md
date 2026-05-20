# Stripe Webhook Reconciliation

## Background

The live Stripe webhook controller ([StripeWebhookController.php](../../backend/app/Http/Controllers/Payment/StripeWebhookController.php)) implements **INSERT-first** idempotency: it inserts a `stripe_webhook_events` row with `status='processing'` *before* mutating the booking. The `stripe_event_id` UNIQUE constraint is the linearization point — duplicate Stripe deliveries fail INSERT and return 200 immediately. See BL-3 in `docs/FINDINGS_BACKLOG.md` and the test contracts in [tests/Feature/Payment/StripeWebhookIdempotencyTest.php](../../backend/tests/Feature/Payment/StripeWebhookIdempotencyTest.php).

That design has one silent failure mode: if the worker/request dies between `StripeWebhookEvent::create(...)` (status='processing') and the closing `markProcessed`/`markFailed`, the row stays `processing` forever. Stripe's at-least-once retry policy then becomes a no-op because every retry hits the UNIQUE constraint and short-circuits to 200. The guest's booking remains `PENDING` with no operator signal.

## Reaper

The `webhook:reconcile-stuck-events` artisan command ([ReconcileStuckStripeWebhookEvents.php](../../backend/app/Console/Commands/ReconcileStuckStripeWebhookEvents.php)) recovers from this failure mode.

```
php artisan webhook:reconcile-stuck-events --minutes=15 --limit=50
```

Behavior:

1. **Fail exhausted rows first.** Any `processing` row whose `reconcile_attempts >= booking.reconciliation.webhook_max_attempts` (default 12, env `BOOKING_WEBHOOK_RECONCILE_MAX_ATTEMPTS`) is transitioned to `failed` and surfaced via a `stripe_webhook_reconciler.reconciliation_exhausted` error log. The last transient `error` is preserved inline as forensic context. This is what stops a row that keeps deferring (persistent transient Stripe error, network blackhole, misconfigured PaymentIntent) from being re-claimed every 5 minutes forever with no operator signal.
2. Atomically **claim** stale `processing` rows (status=processing, type ∈ `StripeWebhookEvent::RECONCILABLE_TYPES`, created_at < cutoff, **and `reconcile_attempts < webhook_max_attempts`**). The claim bumps `reconcile_started_at` and `reconcile_attempts` under `SELECT ... FOR UPDATE` so concurrent reapers cannot double-process.
3. After the claim transaction commits, **re-fetch** each PaymentIntent from Stripe — Stripe is the source of truth.
4. Verify `paymentIntent.status === 'succeeded'` and that amount/currency match the local booking (defense in depth).
5. Apply the idempotent business effect via the shared [`StripePaymentIntentSucceededHandler`](../../backend/app/Services/Payment/StripePaymentIntentSucceededHandler.php) — the same handler the live controller uses.
6. Mark the event `processed` on success, `failed` (with sanitized `error` context) on unrecoverable error. Transient Stripe errors (`RateLimitException`, `ApiConnectionException`) leave the event `processing` so the next run retries; the row carries the last failure context in `error` + `reconcile_finished_at`. A row that keeps deferring this way climbs `reconcile_attempts` until step 1 retires it.

> **Threshold semantics.** `webhook_max_attempts` is the webhook reaper's own knob and is intentionally distinct from `booking.reconciliation.max_attempts` (default 5), which governs the unrelated `ReconcileRefundsJob`. A row gets up to `webhook_max_attempts` claim attempts; the run *after* the counter reaches the threshold auto-fails it.

Schedule: every 5 minutes ([routes/console.php](../../backend/routes/console.php)), with `withoutOverlapping(10)` and `onOneServer()` matching the rest of the project's scheduler conventions.

## Schema

Migration: [2026_05_18_000001_add_reconciliation_fields_to_stripe_webhook_events_table.php](../../backend/database/migrations/2026_05_18_000001_add_reconciliation_fields_to_stripe_webhook_events_table.php).

| Column | Type | Purpose |
|---|---|---|
| `error` | text nullable | Sanitized failure message (Stripe secrets + client_secret values stripped, clamped to `StripeWebhookEvent::ERROR_MAX_LENGTH = 1000`) |
| `failed_at` | timestamp nullable | Set when the event transitions to `failed` |
| `reconcile_started_at` | timestamp nullable | Bumped when the reaper claims the row |
| `reconcile_finished_at` | timestamp nullable | End-of-attempt timestamp (terminal or transient) |
| `reconcile_attempts` | unsigned integer default 0 | Increments on each claim. Gates re-claim: once it reaches `booking.reconciliation.webhook_max_attempts` the reaper auto-fails the row instead of re-claiming it |

The composite index `idx_stripe_webhook_events_status_created_at` backs the reaper's selection predicate.

## Alerts

These queries are intentionally specified in raw SQL so they can be wired into whichever alerting backend the operator deploys (Grafana SQL panel, Prometheus exporter, scheduled DB cron, etc.). The thresholds reflect the silent-failure scenario the reaper is designed to detect.

### P1 — Stuck `processing` events

The reaper should converge any `processing` row older than 15 minutes within the next scheduler tick (≤ 5 min). Anything older than that threshold escaping conversion means the reaper itself is failing.

```sql
SELECT COUNT(*) AS stuck_count
FROM stripe_webhook_events
WHERE status = 'processing'
  AND created_at < NOW() - INTERVAL '5 minutes';
```

**Page when** `stuck_count > 1`.

Why `> 1` and not `> 0`: a single in-flight live webhook may briefly sit in `processing` for a few seconds. Two simultaneously stuck rows means a real problem (failed worker, failed reaper, Stripe outage).

### P1 — Recently failed events

The reaper deliberately marks unrecoverable events `failed` (PaymentIntent not succeeded, amount/currency mismatch, booking in invalid state, business effect throw) rather than letting them rot in `processing`. Each `failed` event is operationally suspicious — Stripe says payment happened but our local model rejected the transition.

```sql
SELECT COUNT(*) AS recent_failures
FROM stripe_webhook_events
WHERE status = 'failed'
  AND failed_at > NOW() - INTERVAL '15 minutes';
```

**Page when** `recent_failures > 0`.

### P2 — Reconciliation-exhausted events

A row that exhausts `webhook_max_attempts` is auto-failed by step 1 and emits `stripe_webhook_reconciler.reconciliation_exhausted` at `error` level. These are distinguishable from other failures by the `error` column prefix `reconciliation exhausted after N attempts` and indicate a *persistent* upstream problem (Stripe outage, blackholed network, misconfigured PaymentIntent) rather than a one-off rejection.

```sql
SELECT COUNT(*) AS exhausted_count
FROM stripe_webhook_events
WHERE status = 'failed'
  AND error LIKE 'reconciliation exhausted after%'
  AND failed_at > NOW() - INTERVAL '1 hour';
```

**Page when** `exhausted_count > 0`. Log-based alerting can equivalently key on the `stripe_webhook_reconciler.reconciliation_exhausted` event name.

### Investigative query — current failure context

```sql
SELECT id, stripe_event_id, type, error, failed_at, reconcile_attempts
FROM stripe_webhook_events
WHERE status = 'failed'
ORDER BY failed_at DESC
LIMIT 20;
```

The `error` column is sanitized at write time (see `StripeWebhookEvent::markFailed`) — Stripe `sk_*`/`rk_*` keys and PaymentIntent `client_secret` values are redacted before persistence, and the column is clamped to 1000 chars.

## Runbook on alert

1. Identify the offending event with the investigative query above.
2. Check `reconcile_attempts`. If the row reached `webhook_max_attempts` and was auto-failed (`error` begins `reconciliation exhausted after`), the inline `(last error: …)` context names the recurring transient cause — typically a Stripe outage (check status.stripe.com) or a blackholed PaymentIntent. After the underlying issue clears, reset and re-run (step 5).
3. If `failed` due to PaymentIntent status mismatch → look up the PaymentIntent in the Stripe dashboard. If Stripe shows succeeded but local says otherwise, the booking row was modified out-of-band; verify with `bookings` table.
4. If `failed` due to "manual review required" (InvalidState outcome) → the booking is `CANCELLED`/`REFUND_PENDING`/`REFUND_FAILED` but Stripe charged the guest. This is a refund-due scenario; route to finance ops.
5. Recovery: once the underlying issue is resolved, an operator can re-claim a single event manually with a tighter window:

   ```
   php artisan webhook:reconcile-stuck-events --minutes=0 --limit=1
   ```

   The reaper is idempotent — re-running it against an already-`processed` row is a no-op because `staleProcessing` filters by `status='processing'`.

   For an **auto-failed exhausted row** (`status='failed'`), the reaper will not pick it up until it is put back in flight. Reset both the status and the attempt counter, then re-run the command above:

   ```sql
   UPDATE stripe_webhook_events
   SET status = 'processing', reconcile_attempts = 0, failed_at = NULL
   WHERE id = :id;
   ```

   Leaving `reconcile_attempts` at the threshold would cause step 1 to immediately re-fail the row.

## Security notes

- **No Stripe secret logging.** The reaper resolves the StripeClient via `Cashier::stripe()` / the bound container service. Error messages persisted to the `error` column are scrubbed of `sk_*` / `rk_*` / `pi_*_secret_*` / `client_secret` substrings via the regex set in `StripeWebhookEvent::sanitizeError`.
- **No raw response bodies.** The reaper persists `Throwable::getMessage()` only, not Stripe response bodies.
- **No dynamic dispatch.** The reaper supports only event types listed in `StripeWebhookEvent::RECONCILABLE_TYPES` (currently `payment_intent.succeeded`). Adding a new type requires an explicit code change + tests; the reaper will refuse to replay anything else.
- **PaymentIntent ownership verified.** Before applying the business effect, the reaper requires `paymentIntent.amount === booking.amount` (when local amount is known) and `paymentIntent.currency === cashier.currency`. Mismatch → `failed` with explicit context.
