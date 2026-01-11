# 📋 Booking Cancellation & Refund Architecture

> Principal-level production design for Laravel 12.46+ with Cashier (Stripe)

---

## High-Level Summary

1. **Dedicated Endpoint**: `POST /bookings/{id}/cancel` — never embed cancellation in PATCH/PUT updates
2. **State Machine**: `pending → confirmed → cancelled`; invariant: `refunded ⟹ cancelled`
3. **Refund via Cashier**: Native `$charge->refund()` — no custom gateway calls
4. **Atomic DB, Eventually Consistent Refund**: Status update is transactional; refund is external (Stripe API) — cannot be in same ACID transaction
5. **Idempotency**: Booking ID + status check guards; Stripe's idempotency keys for refunds
6. **Failure Recovery**: Refund failure → booking stays `cancellation_pending`, compensation job retries
7. **Async Notifications**: Queued with `afterCommit()`, exponential backoff, dead-letter logging
8. **Observability**: Structured logs (level: info/warning/error), metrics (refund success rate), alerts (refund failure threshold)
9. **Multi-Tenant Safe**: All queries scoped by `user_id` or `tenant_id`; refunds scoped to owning payment
10. **Extensible**: Cancellation fees, partial refunds, multi-gateway all additive — core flow unchanged

---

## 1. Laravel Provides vs. You Configure vs. Do Not Touch

### Out-of-the-Box (Laravel Native)

| Capability        | Provider                             | Notes                                     |
| ----------------- | ------------------------------------ | ----------------------------------------- |
| Refund issuance   | `Laravel\Cashier\Charge::refund()`   | Wraps Stripe API                          |
| Idempotency       | Stripe SDK (via Cashier)             | Requires explicit `idempotencyKey` option |
| DB transactions   | `DB::transaction()`                  | ACID for local state                      |
| Route middleware  | `auth:sanctum`, `can:cancel,booking` | Policy-based authorization                |
| Queue dispatching | `ShouldQueue`, `afterCommit()`       | Async notifications                       |
| Rate limiting     | `RateLimiter::for()`                 | Per-user throttling                       |

### You Must Configure

| Item          | Location                               | Notes                             |
| ------------- | -------------------------------------- | --------------------------------- |
| Stripe keys   | `.env` → `STRIPE_KEY`, `STRIPE_SECRET` | Never commit secrets              |
| Cashier setup | `config/cashier.php`                   | Currency, webhook secret          |
| Refund policy | `App\Services\RefundPolicyService`     | Business logic: window, partial % |
| Queue worker  | Supervisor / Horizon                   | `notifications` queue             |
| Webhook route | `routes/api.php`                       | `POST /stripe/webhook`            |

### DO NOT Customize (Security/Resilience)

| Component                      | Reason                                                   |
| ------------------------------ | -------------------------------------------------------- |
| Cashier's `refund()` internals | Handles Stripe error normalization, retries, logging     |
| Stripe idempotency mechanism   | Attempting custom idempotency breaks Stripe's guarantees |
| Sanctum token validation       | Custom token logic introduces auth bypass vectors        |
| Webhook signature verification | `Cashier::handleWebhook()` verifies `Stripe-Signature`   |

---

## 2. State Model & Invariants (MANDATORY)

### Booking State Machine

```
┌─────────────┐     confirm()     ┌─────────────┐
│   PENDING   │ ─────────────────▶│  CONFIRMED  │
└─────────────┘                   └──────┬──────┘
                                         │
                                   cancel()
                                         │
                                         ▼
                             ┌───────────────────────┐
                             │   REFUND_PENDING      │  ← (intermediate state)
                             └───────────┬───────────┘
                                         │
                         ┌───────────────┴───────────────┐
                         │                               │
                   refund succeeds                 refund fails
                         │                               │
                         ▼                               ▼
                  ┌────────────┐                ┌─────────────────┐
                  │  CANCELLED │                │  REFUND_FAILED  │
                  │ (refunded) │                │ (needs manual)  │
                  └────────────┘                └─────────────────┘
```

> **Note:** The enum uses `REFUND_PENDING` (not `cancellation_pending`) as the intermediate state name.
> See `App\Enums\BookingStatus` for the authoritative status definitions.

### Allowed Transitions (Exhaustive)

| From             | To               | Trigger                              | Transactional? |
| ---------------- | ---------------- | ------------------------------------ | -------------- |
| `PENDING`        | `CONFIRMED`      | Admin confirmation                   | Yes            |
| `PENDING`        | `CANCELLED`      | User/admin cancellation (no payment) | Yes            |
| `PENDING`        | `REFUND_PENDING` | Cancellation with refund             | Yes            |
| `CONFIRMED`      | `REFUND_PENDING` | Cancellation initiated               | Yes            |
| `CONFIRMED`      | `CANCELLED`      | Cancel without refund                | Yes            |
| `REFUND_PENDING` | `CANCELLED`      | Refund succeeds                      | Yes (via job)  |
| `REFUND_PENDING` | `REFUND_FAILED`  | Refund exhausts retries              | Yes (via job)  |
| `REFUND_FAILED`  | `REFUND_PENDING` | Retry refund                         | Yes            |
| `REFUND_FAILED`  | `CANCELLED`      | Manual admin resolution              | Yes            |

### BookingStatus Enum

```php
// app/Enums/BookingStatus.php

enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case REFUND_PENDING = 'refund_pending';
    case CANCELLED = 'cancelled';
    case REFUND_FAILED = 'refund_failed';

    public function isCancellable(): bool;         // PENDING, CONFIRMED, REFUND_FAILED
    public function isTerminal(): bool;            // CANCELLED only
    public function isRefundInProgress(): bool;    // REFUND_PENDING only
    public function canTransitionTo(self $target): bool;
}
```

### Invariants (MUST NEVER Violate)

```php
// app/Models/Booking.php

public function isRefunded(): bool
{
    return $this->refund_id !== null && $this->refund_status === 'succeeded';
}

// INVARIANT: refunded ⟹ cancelled
public static function bootBooking(): void
{
    static::saving(function (Booking $booking) {
        if ($booking->isRefunded() && $booking->status !== BookingStatus::CANCELLED) {
            throw new InvariantViolationException(
                "Invariant violation: refunded booking must be cancelled"
            );
        }
    });
}
```

### Why Intermediate State `REFUND_PENDING`?

1. **Refund is external**: Stripe API call cannot be in DB transaction
2. **User visibility**: Shows "refund in progress" vs ambiguous state
3. **Retry-safe**: Job can retry without re-triggering duplicate refunds
4. **Audit trail**: Clear indication of where failure occurred

---

## 3. Full Lifecycle Flow

### Sequence Diagram: Success Path

```
User                Controller              CancellationService      Stripe/Cashier          Queue
 │                      │                        │                        │                    │
 ├─ POST /cancel ──────▶│                        │                        │                    │
 │                      ├── authorize(cancel) ──▶│                        │                    │
 │                      │                        │                        │                    │
 │                      ├── DB::transaction ────▶│                        │                    │
 │                      │   status → REFUND_PENDING                       │                    │
 │                      │◀─ commit ──────────────┤                        │                    │
 │                      │                        │                        │                    │
 │                      │                        ├── processRefund() ────▶│                    │
 │                      │                        │◀── RefundResult ───────┤                    │
 │                      │                        │                        │                    │
 │                      │                        ├── DB::transaction      │                    │
 │                      │                        │   status → CANCELLED   │                    │
 │                      │◀──────────────────────┤                        │                    │
 │◀─ 200 OK ────────────┤                        │                        │                    │
 │   {status: "cancelled"}                       │                        │                    │
 │                      │                        │                        │                    │
 │                      │                        ├── event(BookingCancelled) ────────────────▶│      │                    │
 │                      │                        │   status → cancelled   │                    │
 │                      │                        │   refund_id = xyz      │                    │
 │                      │                        │                        │                    │
 │                      │                        ├── notify(BookingCancelled) ───────────────▶│
 │                      │                        │                        │                    │
```

### Sequence Diagram: Refund Failure Path

```
Queue Worker          ProcessRefundJob         Stripe/Cashier          BookingService
     │                      │                        │                        │
     ├── process() ────────▶│                        │                        │
     │                      ├── $charge->refund() ──▶│                        │
     │                      │◀── StripeException ────┤                        │
     │                      │                        │                        │
     │                      ├── Log::warning(...)    │                        │
     │                      │                        │                        │
     │                      ├── retry (attempt 2/3)  │                        │
     │                      │       ...              │                        │
     │                      ├── all retries failed   │                        │
     │                      │                        │                        │
     │                      ├── failed() ───────────────────────────────────▶│
     │                      │                        │   status → refund_failed
     │                      │                        │   Log::error(...)      │
     │                      │                        │   Alert::refundFailed()│
     │                      │                        │                        │
```

---

## 4. Implementation (Step-by-Step)

### 4.1 Update Booking Model: Add Refund Tracking

**File:** `app/Models/Booking.php`

```php
// Add to $fillable array
protected $fillable = [
    // ... existing fields
    'payment_id',          // Stripe PaymentIntent ID
    'refund_id',           // Stripe Refund ID (null until refunded)
    'refund_status',       // 'pending', 'succeeded', 'failed'
    'refund_amount',       // Amount refunded (cents)
    'cancelled_at',        // Timestamp of cancellation
    'cancelled_by',        // User ID who cancelled
];

// Add constants
public const STATUS_CANCELLATION_PENDING = 'cancellation_pending';
public const STATUS_REFUND_FAILED = 'refund_failed';

public const REFUNDABLE_STATUSES = ['confirmed'];
```

**Rationale:** Explicit refund tracking columns enable idempotency checks and audit trail without querying Stripe.

---

### 4.2 Migration: Add Refund Columns

**File:** `database/migrations/2026_01_10_add_refund_columns_to_bookings.php`

```php
public function up(): void
{
    Schema::table('bookings', function (Blueprint $table) {
        $table->string('payment_id')->nullable()->after('status');
        $table->string('refund_id')->nullable()->after('payment_id');
        $table->string('refund_status')->nullable()->after('refund_id');
        $table->unsignedBigInteger('refund_amount')->nullable()->after('refund_status');
        $table->timestamp('cancelled_at')->nullable()->after('refund_amount');
        $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

        $table->index(['status', 'refund_status']); // For monitoring queries
    });
}
```

**Rationale:** `refund_status` stored locally avoids Stripe API calls for status checks; index supports refund monitoring dashboards.

---

### 4.3 Refund Policy Service

**File:** `app/Services/RefundPolicyService.php`

```php
<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;

class RefundPolicyService
{
    // Business rules: configurable via config/booking.php
    private const FULL_REFUND_HOURS = 48;      // 48h before check-in
    private const PARTIAL_REFUND_HOURS = 24;   // 24h before check-in
    private const PARTIAL_REFUND_PERCENT = 50;

    public function isRefundable(Booking $booking): bool
    {
        if (!in_array($booking->status, Booking::REFUNDABLE_STATUSES)) {
            return false;
        }

        if (!$booking->payment_id) {
            return false; // No payment to refund
        }

        return $booking->check_in->isFuture();
    }

    public function calculateRefundAmount(Booking $booking): int
    {
        $hoursUntilCheckIn = now()->diffInHours($booking->check_in, false);

        if ($hoursUntilCheckIn >= self::FULL_REFUND_HOURS) {
            return $booking->amount; // Full refund (amount in cents)
        }

        if ($hoursUntilCheckIn >= self::PARTIAL_REFUND_HOURS) {
            return (int) ($booking->amount * self::PARTIAL_REFUND_PERCENT / 100);
        }

        return 0; // No refund within 24h
    }

    public function getRefundDenialReason(Booking $booking): ?string
    {
        if ($booking->status === Booking::STATUS_CANCELLED) {
            return 'Booking is already cancelled';
        }

        if (!$booking->payment_id) {
            return 'No payment associated with this booking';
        }

        if ($booking->check_in->isPast()) {
            return 'Cannot refund past bookings';
        }

        return null;
    }
}
```

**Rationale:** Policy logic isolated for testability and business rule changes without touching cancellation flow.

---

### 4.4 Updated BookingService: Cancellation with Refund

**File:** `app/Services/BookingService.php` (add/modify methods)

```php
use App\Jobs\ProcessRefundJob;
use App\Exceptions\RefundNotAllowedException;

/**
 * Initiate cancellation with refund.
 *
 * ATOMICITY BOUNDARY:
 * - DB status update: TRANSACTIONAL (immediate consistency)
 * - Stripe refund: NOT in transaction (external API, eventually consistent)
 * - Notification: QUEUED after commit
 *
 * WHY NOT WRAP REFUND IN TRANSACTION?
 * - Stripe API is external; DB transaction cannot rollback Stripe operations
 * - If refund succeeds but commit fails → money refunded, booking not updated → BAD
 * - Solution: Update status FIRST, then attempt refund async
 */
public function initiateCancellation(
    Booking $booking,
    ?int $cancelledByUserId = null
): Booking {
    // Idempotency: already in cancellation flow
    if (in_array($booking->status, [
        Booking::STATUS_CANCELLED,
        Booking::STATUS_CANCELLATION_PENDING,
    ])) {
        return $booking;
    }

    $refundPolicy = app(RefundPolicyService::class);

    // Check if refund is needed
    $needsRefund = $booking->payment_id && $refundPolicy->isRefundable($booking);

    return DB::transaction(function () use ($booking, $cancelledByUserId, $needsRefund, $refundPolicy) {
        if ($needsRefund) {
            // Set intermediate state — refund will complete async
            $booking->update([
                'status' => Booking::STATUS_CANCELLATION_PENDING,
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledByUserId ?? auth()->id(),
                'refund_amount' => $refundPolicy->calculateRefundAmount($booking),
                'refund_status' => 'pending',
            ]);

            // Dispatch refund job AFTER transaction commits
            ProcessRefundJob::dispatch($booking)
                ->onQueue('refunds')
                ->afterCommit();

        } else {
            // No payment or no refund needed — cancel immediately
            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledByUserId ?? auth()->id(),
            ]);

            // Notification for non-refund cancellation
            $booking->user->notify(new BookingCancelled($booking));
        }

        $this->invalidateBooking($booking->id, $booking->user_id);

        Log::info('Booking cancellation initiated', [
            'booking_id' => $booking->id,
            'needs_refund' => $needsRefund,
            'status' => $booking->status,
        ]);

        return $booking->fresh();
    });
}
```

**Rationale:**

- `cancellation_pending` state allows user to see progress
- Refund dispatched `afterCommit()` ensures job only runs if DB update persists
- Non-refund path completes synchronously (no external dependency)

---

### 4.5 ProcessRefundJob

**File:** `app/Jobs/ProcessRefundJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Notifications\BookingCancelled;
use App\Notifications\RefundFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Exception\ApiErrorException;

class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly Booking $booking
    ) {}

    public function handle(): void
    {
        // Idempotency: already processed
        if ($this->booking->refund_id !== null) {
            Log::info('Refund already processed, skipping', [
                'booking_id' => $this->booking->id,
                'refund_id' => $this->booking->refund_id,
            ]);
            return;
        }

        // Idempotency: wrong state
        if ($this->booking->status !== Booking::STATUS_CANCELLATION_PENDING) {
            Log::warning('Booking not in cancellation_pending state', [
                'booking_id' => $this->booking->id,
                'status' => $this->booking->status,
            ]);
            return;
        }

        try {
            $refund = $this->issueRefund();
            $this->completeRefund($refund);

        } catch (ApiErrorException $e) {
            Log::warning('Stripe refund attempt failed', [
                'booking_id' => $this->booking->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode(),
            ]);

            throw $e; // Let queue retry
        }
    }

    private function issueRefund(): \Stripe\Refund
    {
        $user = $this->booking->user;

        // Get the charge/payment intent from Cashier
        $payment = $user->findPayment($this->booking->payment_id);

        if (!$payment) {
            throw new \RuntimeException("Payment not found: {$this->booking->payment_id}");
        }

        // Issue refund via Cashier with idempotency key
        return $payment->refund([
            'amount' => $this->booking->refund_amount,
        ], [
            'idempotency_key' => "refund-booking-{$this->booking->id}",
        ]);
    }

    private function completeRefund(\Stripe\Refund $refund): void
    {
        DB::transaction(function () use ($refund) {
            $this->booking->update([
                'status' => Booking::STATUS_CANCELLED,
                'refund_id' => $refund->id,
                'refund_status' => $refund->status, // 'succeeded' or 'pending'
            ]);
        });

        // Queue notification after successful refund
        $this->booking->user->notify(new BookingCancelled($this->booking));

        Log::info('Refund completed successfully', [
            'booking_id' => $this->booking->id,
            'refund_id' => $refund->id,
            'amount' => $this->booking->refund_amount,
        ]);
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        DB::transaction(function () {
            $this->booking->update([
                'status' => Booking::STATUS_REFUND_FAILED,
                'refund_status' => 'failed',
            ]);
        });

        // Notify user of failure
        $this->booking->user->notify(new RefundFailed($this->booking));

        // Alert ops team
        Log::error('Refund failed permanently', [
            'booking_id' => $this->booking->id,
            'payment_id' => $this->booking->payment_id,
            'error' => $exception->getMessage(),
            'alert' => 'refund_failure', // For log-based alerting
        ]);

        // Metric for monitoring
        app('metrics')->increment('refunds.failed');
    }
}
```

**Rationale:**

- Idempotency key prevents duplicate refunds on retry
- `failed()` updates status to `refund_failed` for manual resolution
- Structured logging enables alerting (e.g., Datadog, Sentry)

---

### 4.6 Updated Controller: Return 202 for Async

**File:** `app/Http/Controllers/BookingController.php` (modify cancel method)

```php
public function cancel(Booking $booking): JsonResponse
{
    $this->authorize('cancel', $booking);

    // Idempotency: already cancelled
    if ($booking->status === Booking::STATUS_CANCELLED) {
        return response()->json([
            'success' => true,
            'message' => 'Booking is already cancelled',
            'data' => new BookingResource($booking),
        ], 200);
    }

    // Check refund eligibility
    $refundPolicy = app(RefundPolicyService::class);
    $denialReason = $refundPolicy->getRefundDenialReason($booking);

    if ($denialReason && $booking->payment_id) {
        return response()->json([
            'success' => false,
            'message' => $denialReason,
        ], 422);
    }

    try {
        $booking = $this->bookingService->initiateCancellation($booking, auth()->id());

        // Different response based on whether refund is pending
        $isPending = $booking->status === Booking::STATUS_CANCELLATION_PENDING;

        return response()->json([
            'success' => true,
            'message' => $isPending
                ? 'Cancellation initiated. Refund is being processed.'
                : 'Booking cancelled successfully.',
            'data' => new BookingResource($booking->load('room')),
        ], $isPending ? 202 : 200);

    } catch (\RuntimeException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
}
```

**Rationale:**

- `202 Accepted` signals async processing to client
- Idempotent: repeated calls return success without re-processing

---

## 5. Failure Modes & Edge Cases

### Matrix of Failure Scenarios

| Scenario                            | Detection                         | Recovery                           | User Sees                              |
| ----------------------------------- | --------------------------------- | ---------------------------------- | -------------------------------------- |
| Already cancelled                   | Status check                      | Return 200 (idempotent)            | "Already cancelled"                    |
| Non-refundable window               | Policy check                      | Reject with reason                 | "Cannot refund within 24h of check-in" |
| Payment not found                   | `findPayment()` returns null      | Mark `refund_failed`, alert        | "Refund issue, contact support"        |
| Stripe timeout                      | `ApiErrorException`               | Job retry (3x backoff)             | "Processing..."                        |
| Stripe permanent error              | `ApiErrorException` after retries | Mark `refund_failed`, notify       | Email with manual refund steps         |
| Race condition (2 requests)         | `cancellation_pending` check      | First wins, second returns 200     | Both see success                       |
| User not owner                      | Policy `can:cancel`               | 403 Forbidden                      | "Unauthorized"                         |
| Soft-deleted booking                | Model binding fails               | 404 Not Found                      | "Booking not found"                    |
| Multi-tenant isolation              | Scoped queries                    | Query filters by tenant            | Only sees own bookings                 |
| Queue worker down                   | Job stays in queue                | Horizon/Supervisor auto-restart    | Delayed but eventually processed       |
| Refund succeeds, notification fails | Separate concerns                 | Notification retries independently | Refund works, email delayed            |

### When NOT to Rollback

| Situation                                 | Why No Rollback                                                                |
| ----------------------------------------- | ------------------------------------------------------------------------------ |
| Refund initiated but booking update fails | Refund is external — money already moved. Compensation: log, alert, manual fix |
| Notification fails                        | Non-critical; user still gets refund. Retry via queue.                         |
| Partial refund calculation error          | Log and process full refund; adjust manually if needed                         |

### Allowed Intermediate States

| State                  | Duration               | Resolution                                     |
| ---------------------- | ---------------------- | ---------------------------------------------- |
| `cancellation_pending` | < 30 minutes typically | Job completes → `cancelled` or `refund_failed` |
| `refund_failed`        | Until admin resolves   | Admin marks `cancelled` after manual refund    |

---

## 6. Testing Strategy

### Feature Tests (Fake Cashier)

**File:** `tests/Feature/BookingCancellationTest.php`

```php
public function test_cancellation_with_refund_sets_pending_status(): void
{
    $user = User::factory()->create();
    $booking = Booking::factory()
        ->for($user)
        ->create([
            'status' => 'confirmed',
            'payment_id' => 'pi_test_123',
            'amount' => 10000, // $100
            'check_in' => now()->addDays(7),
        ]);

    $this->actingAs($user)
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertStatus(202)
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'cancellation_pending',
        'refund_status' => 'pending',
    ]);

    Queue::assertPushed(ProcessRefundJob::class);
}

public function test_cancellation_idempotent_on_already_cancelled(): void
{
    $booking = Booking::factory()->create(['status' => 'cancelled']);

    $this->actingAs($booking->user)
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertStatus(200)
        ->assertJson(['message' => 'Booking is already cancelled']);
}

public function test_refund_job_updates_status_on_success(): void
{
    // Mock Stripe
    $this->mock(\Laravel\Cashier\Payment::class, function ($mock) {
        $mock->shouldReceive('refund')->andReturn(
            new \Stripe\Refund(['id' => 're_123', 'status' => 'succeeded'])
        );
    });

    $booking = Booking::factory()->create([
        'status' => 'cancellation_pending',
        'payment_id' => 'pi_test',
    ]);

    (new ProcessRefundJob($booking))->handle();

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'cancelled',
        'refund_id' => 're_123',
    ]);
}
```

### Manual Verification Checklist

- [ ] Cancel confirmed booking with payment → status becomes `cancellation_pending`
- [ ] Queue worker processes job → status becomes `cancelled`, refund_id populated
- [ ] Cancel pending booking (no payment) → status becomes `cancelled` immediately
- [ ] Cancel already-cancelled booking → returns 200, no state change
- [ ] Cancel as non-owner → returns 403
- [ ] Cancel within 24h of check-in → appropriate refund amount or denial
- [ ] Simulate Stripe failure → after 3 retries, status becomes `refund_failed`
- [ ] Check email received for both success and failure paths

### What Is NOT Unit Tested (and Why)

| Component             | Reason                                                       |
| --------------------- | ------------------------------------------------------------ |
| Stripe API calls      | Integration test with test keys, not unit mock               |
| DB transactions       | Feature tests cover; unit tests can't verify commit behavior |
| Queue dispatch timing | `afterCommit()` behavior requires integration test           |

---

## 7. Decision Log

### Dedicated Endpoint vs. Embedded Logic

| Option                                          | Verdict     | Reason                                                        |
| ----------------------------------------------- | ----------- | ------------------------------------------------------------- |
| `POST /bookings/{id}/cancel`                    | ✅ Chosen   | Clear semantics, auditable, RESTful                           |
| `PATCH /bookings/{id}` with `status: cancelled` | ❌ Rejected | Mixes update concerns; cancellation has side effects (refund) |

### Cashier vs. Custom Gateway Integration

| Option                      | Verdict     | Reason                                                   |
| --------------------------- | ----------- | -------------------------------------------------------- |
| Cashier `$charge->refund()` | ✅ Chosen   | Battle-tested, handles edge cases, maintained by Laravel |
| Direct Stripe SDK           | ❌ Rejected | Reinventing error handling, idempotency, logging         |
| Multi-gateway abstraction   | 🔄 Future   | Add when business requires; Cashier is Stripe-only       |

### Sync vs. Async Refund Processing

| Option             | Verdict     | Reason                                                     |
| ------------------ | ----------- | ---------------------------------------------------------- |
| Async (queued job) | ✅ Chosen   | Stripe can timeout; keeps request fast (<500ms); retryable |
| Sync (in request)  | ❌ Rejected | Request timeout risk; no automatic retry; blocks user      |

### Intermediate State `cancellation_pending`

| Option                                    | Verdict     | Reason                                                     |
| ----------------------------------------- | ----------- | ---------------------------------------------------------- |
| Explicit intermediate state               | ✅ Chosen   | User sees progress; job is idempotent; clear failure state |
| Direct `cancelled` with background refund | ❌ Rejected | Confusing if refund fails after showing "cancelled"        |

### Why This Survives High Load

- **Queue-based refunds**: Spikes handled by worker scaling (Horizon)
- **Idempotent endpoints**: Safe to retry from load balancer/client
- **No transaction spanning external calls**: DB connections released fast
- **Cache invalidation is async**: Doesn't block response

### Observability Requirements

| Type           | Implementation                                                                            |
| -------------- | ----------------------------------------------------------------------------------------- |
| **Logs**       | Structured JSON; levels: `info` (success), `warning` (retry), `error` (permanent failure) |
| **Metrics**    | `refunds.initiated`, `refunds.succeeded`, `refunds.failed`, `refunds.retry_count`         |
| **Alerts**     | Trigger on: `refunds.failed > 5/hour`, `cancellation_pending` stuck > 1 hour              |
| **Dashboards** | Refund success rate, average processing time, failure reasons                             |

### Multi-Tenant Extension

```php
// All queries already scoped
Booking::where('user_id', auth()->id())->...

// For multi-tenant (future):
Booking::where('tenant_id', auth()->user()->tenant_id)->...

// Refund scoped by payment ownership — Cashier handles via billable user
```

---

## 8. Production Recommendations

### Cashier Configuration

```php
// config/cashier.php
return [
    'currency' => 'usd',
    'currency_locale' => 'en',
    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300, // 5 min tolerance for webhook timestamp
    ],
];
```

### Webhook Handler for Async Refund Status

```php
// routes/api.php
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('stripe.webhook');

// Handle charge.refunded event to sync status
protected function handleChargeRefunded(array $payload): Response
{
    $refundId = $payload['data']['object']['id'];

    Booking::where('payment_id', $payload['data']['object']['payment_intent'])
        ->update(['refund_status' => 'succeeded']);

    return $this->successMethod();
}
```

### Queue Configuration

```php
// config/queue.php - separate queue for refunds
'connections' => [
    'redis' => [
        'queue' => 'default',
        // ...
    ],
],

// Horizon worker config
'environments' => [
    'production' => [
        'refunds-worker' => [
            'connection' => 'redis',
            'queue' => ['refunds'],
            'processes' => 2,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
],
```

### Monitoring Setup

```yaml
# Datadog/Prometheus alert rules
alerts:
  - name: RefundFailureRate
    condition: rate(refunds_failed[5m]) > 0.05
    severity: warning

  - name: StuckCancellations
    condition: count(bookings{status="cancellation_pending"} offset 1h) > 10
    severity: critical
```

---

## Summary

This architecture provides:

1. **Clear state machine** with explicit intermediate states
2. **Proper atomicity boundaries** — DB transactional, external calls async
3. **Idempotent operations** at every layer
4. **Graceful degradation** — failures don't leave system in bad state
5. **Observable behavior** — logs, metrics, alerts for production
6. **Extensible design** — cancellation fees, partial refunds additive

The key insight: **refunds are eventually consistent by nature**. Fighting this with "wrap everything in transaction" creates worse failure modes. Embrace the intermediate state and build compensation into the design.
