# Booking Cancellation Flow with Refund Logic

> **Design Review Document** — Laravel 12.46+ / Cashier (Stripe)  
> Target audience: Principal/Staff backend engineers  
> Last updated: January 2026

---

## Executive Summary

| Aspect                   | Decision                                                      |
| ------------------------ | ------------------------------------------------------------- |
| **Endpoint**             | `POST /api/bookings/{booking}/cancel` — dedicated, idempotent |
| **Refund Processing**    | **Synchronous** — user waits for Stripe response              |
| **Transaction Boundary** | DB update + refund are **NOT** in same transaction            |
| **Fallback**             | `REFUND_PENDING` intermediate state; async reconciliation job |
| **Notification**         | Queued via `afterCommit()`, retry with exponential backoff    |
| **Idempotency**          | Status check before processing; repeat calls return 200       |

---

## High-Level Summary (10 bullets)

1. **Dedicated endpoint** — `POST /bookings/{booking}/cancel` isolated from CRUD; never embed in `PATCH /bookings/{id}`
2. **Synchronous refund** — user receives immediate confirmation; avoids UX ambiguity ("is my refund processing?")
3. **Cashier-native refunds** — `$user->refund($paymentIntentId, $options)` only; no raw Stripe SDK calls
4. **Intermediate state** — booking transitions to `REFUND_PENDING` before Stripe call; reverts to `CANCELLED`/`REFUND_FAILED` after
5. **Idempotent by design** — re-cancelling an already-cancelled booking returns 200 with existing state
6. **DB transaction scope** — status updates are transactional; refund call is intentionally **outside** to avoid long-held locks
7. **Reconciliation job** — scheduled every 5 min to fix orphaned `REFUND_PENDING` bookings (Stripe webhook or API check)
8. **Notifications use Mailable internally** — `toMail()` returns a Mailable; never `Mail::send()` directly
9. **Policy enforcement** — `BookingPolicy@cancel` checks ownership, status, and refund window
10. **Observability** — structured logs (JSON), Prometheus metrics via `laravel-prometheus`, Sentry for exceptions

---

## 1. Laravel Provides vs. You Configure vs. Do Not Touch

### Out-of-the-Box (Use As-Is)

| Component                              | What Laravel/Cashier Provides                                |
| -------------------------------------- | ------------------------------------------------------------ |
| `Cashier::stripe()->refunds->create()` | Handles idempotency keys, retries, webhook verification      |
| `DB::transaction()`                    | ACID guarantees, automatic rollback on exception             |
| Route model binding                    | `{booking}` resolves via `Booking::findOrFail()`             |
| Policy authorization                   | `$this->authorize('cancel', $booking)` integrates with gates |
| `ShouldQueue` + `afterCommit`          | Notification dispatched only after DB commit                 |
| `RateLimiter`                          | Throttle cancellation attempts per user                      |

### You Must Configure

```env
# .env (production)
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en
```

```php
// config/cashier.php — verify these defaults
'currency' => env('CASHIER_CURRENCY', 'usd'),
'logger' => env('CASHIER_LOGGER', 'stack'), // structured logging
```

### Do NOT Customize

| Component                              | Why                                                                                             |
| -------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Cashier webhook signature verification | Reimplementing = security hole; Stripe SDK handles timing-safe comparison                       |
| Route middleware stack order           | `auth:sanctum` → `verified` → `throttle` is battle-tested                                       |
| `Refund::create()` parameters          | Cashier validates and maps correctly; manual field injection breaks idempotency                 |
| Exception hierarchy                    | `IncompletePayment`, `InvalidRequest` are Stripe-native; catching generically loses diagnostics |

---

## 2. Business Policy Defaults (Mandatory)

> **Rationale**: Hostel bookings are typically prepaid; guests expect clarity on cancellation terms.

### Concrete Defaults

| Policy                 | Value                         | Enforcement           |
| ---------------------- | ----------------------------- | --------------------- |
| **Refund window**      | ≥ 48 hours before `check_in`  | Full refund           |
| **Late cancellation**  | 24–48 hours before `check_in` | 50% refund            |
| **No-refund zone**     | < 24 hours before `check_in`  | 0% refund             |
| **Already checked-in** | After `check_in` date         | Cancellation denied   |
| **Cancellation fee**   | 0% (absorbed by business)     | Simplifies accounting |

### Configuration (runtime-adjustable)

```php
// config/booking.php
return [
    'cancellation' => [
        'full_refund_hours'    => 48,
        'partial_refund_hours' => 24,
        'partial_refund_pct'   => 50,
        'allow_fee'            => false,
        'fee_pct'              => 0,
    ],
];
```

### Enforcement in Policy

```php
// app/Policies/BookingPolicy.php
public function cancel(User $user, Booking $booking): bool
{
    $isOwner = $user->id === $booking->user_id;
    $isAdmin = $user->isAdmin();

    if (!$isOwner && !$isAdmin) {
        return false;
    }

    // Idempotent — allow re-request on already cancelled
    if ($booking->status === BookingStatus::CANCELLED) {
        return true;
    }

    // Must be in cancellable state (pending, confirmed, or refund_failed)
    if (!$booking->status->isCancellable()) {
        return false;
    }

    // Regular users cannot cancel after check-in started (unless config allows)
    if (!$isAdmin && $booking->isStarted()) {
        return config('booking.cancellation.allow_after_checkin', false);
    }

    return true;
}
```

> **Note:** Admins can always cancel bookings even after check-in has started.
> The `CancellationService::validateCancellation()` method also respects admin bypass.

---

## 3. State Model & Invariants

### Booking Status Enum

```php
// app/Enums/BookingStatus.php
enum BookingStatus: string
{
    case PENDING         = 'pending';
    case CONFIRMED       = 'confirmed';
    case REFUND_PENDING  = 'refund_pending';
    case CANCELLED       = 'cancelled';
    case REFUND_FAILED   = 'refund_failed';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PENDING         => in_array($target, [self::CONFIRMED, self::REFUND_PENDING, self::CANCELLED]),
            self::CONFIRMED       => in_array($target, [self::REFUND_PENDING, self::CANCELLED]),
            self::REFUND_PENDING  => in_array($target, [self::CANCELLED, self::REFUND_FAILED]),
            self::CANCELLED       => false, // Terminal
            self::REFUND_FAILED   => in_array($target, [self::REFUND_PENDING, self::CANCELLED]), // Retry allowed
        };
    }
}
```

### State Machine Diagram

```
                    ┌─────────────────────────────────────┐
                    │                                     │
                    ▼                                     │
┌─────────┐    ┌───────────┐    ┌────────────────┐    ┌───────────┐
│ PENDING │───▶│ CONFIRMED │───▶│ REFUND_PENDING │───▶│ CANCELLED │
└─────────┘    └───────────┘    └────────────────┘    └───────────┘
     │              │                   │                    ▲
     │              │                   │                    │
     └──────────────┴───────────────────┘                    │
     (no-payment fast path: skip refund)                     │
                                        │                    │
                                        ▼                    │
                                ┌───────────────┐            │
                                │ REFUND_FAILED │────────────┘
                                └───────────────┘
                                   (retry or manual)
```

### Invariants (MUST NEVER BE VIOLATED)

1. **Refunded → Cancelled**: If `refund_id IS NOT NULL`, status MUST be `cancelled`
2. **No double refund**: Once `refund_status = succeeded`, no further refund attempts allowed
3. **Audit trail**: `cancelled_at` and `cancelled_by` MUST be set on any cancelled booking
4. **Soft delete ≠ cancellation**: Soft-deleted bookings retain original status; cancellation is business logic
5. **Concurrent mutation**: Only one status transition per booking in flight (pessimistic lock)

### Transactional vs. Eventually Consistent

| Operation                 | Consistency Model         | Reason                                                          |
| ------------------------- | ------------------------- | --------------------------------------------------------------- |
| Status → `refund_pending` | **Transactional**         | Must be atomic with lock acquisition                            |
| Stripe refund call        | **Outside TX**            | Network I/O; holding DB lock during Stripe call = deadlock risk |
| Status → `cancelled`      | **Transactional**         | Commit only after Stripe confirms                               |
| Notification dispatch     | **Eventually consistent** | `afterCommit()` ensures delivery after success                  |
| Cache invalidation        | **Eventually consistent** | Acceptable 1-2s staleness                                       |

---

## 4. Full Lifecycle Flow

### Sequence Diagram — Success Path

```
User                Controller           Service              Stripe              DB
 │                      │                   │                   │                 │
 │  POST /cancel        │                   │                   │                 │
 │─────────────────────▶│                   │                   │                 │
 │                      │  authorize()      │                   │                 │
 │                      │──────────────────▶│                   │                 │
 │                      │                   │                   │                 │
 │                      │  cancelBooking()  │                   │                 │
 │                      │──────────────────▶│                   │                 │
 │                      │                   │  BEGIN TX         │                 │
 │                      │                   │─────────────────────────────────────▶│
 │                      │                   │  SELECT FOR UPDATE                  │
 │                      │                   │─────────────────────────────────────▶│
 │                      │                   │  status = refund_pending            │
 │                      │                   │─────────────────────────────────────▶│
 │                      │                   │  COMMIT                             │
 │                      │                   │─────────────────────────────────────▶│
 │                      │                   │                   │                 │
 │                      │                   │  refund()         │                 │
 │                      │                   │──────────────────▶│                 │
 │                      │                   │                   │  (Stripe API)   │
 │                      │                   │◀──────────────────│                 │
 │                      │                   │                   │                 │
 │                      │                   │  BEGIN TX         │                 │
 │                      │                   │─────────────────────────────────────▶│
 │                      │                   │  status = cancelled                 │
 │                      │                   │  refund_id = rf_xxx                 │
 │                      │                   │─────────────────────────────────────▶│
 │                      │                   │  COMMIT           │                 │
 │                      │                   │─────────────────────────────────────▶│
 │                      │                   │                   │                 │
 │                      │                   │  dispatch notification              │
 │                      │                   │  (afterCommit)    │                 │
 │                      │◀──────────────────│                   │                 │
 │  200 OK              │                   │                   │                 │
 │◀─────────────────────│                   │                   │                 │
```

### Sequence Diagram — Refund Failure Path

```
User                Controller           Service              Stripe              DB
 │                      │                   │                   │                 │
 │  POST /cancel        │                   │                   │                 │
 │─────────────────────▶│                   │                   │                 │
 │                      │                   │  status = refund_pending            │
 │                      │                   │─────────────────────────────────────▶│
 │                      │                   │                   │                 │
 │                      │                   │  refund()         │                 │
 │                      │                   │──────────────────▶│                 │
 │                      │                   │        EXCEPTION  │                 │
 │                      │                   │◀──────────────────│                 │
 │                      │                   │                   │                 │
 │                      │                   │  status = refund_failed             │
 │                      │                   │  refund_error = "card_declined"     │
 │                      │                   │─────────────────────────────────────▶│
 │                      │                   │                   │                 │
 │                      │                   │  log error, alert │                 │
 │                      │◀──────────────────│                   │                 │
 │  422 Refund Failed   │                   │                   │                 │
 │◀─────────────────────│                   │                   │                 │
 │                      │                   │                   │                 │
 │                      │     ┌─────────────────────────────────┐                 │
 │                      │     │ ReconcileRefundsJob (5 min)     │                 │
 │                      │     │ retries failed refunds          │                 │
 │                      │     └─────────────────────────────────┘                 │
```

---

## 5. Implementation (Step-by-Step)

### Step 1: Add Payment Fields to Bookings Migration

**File**: `database/migrations/2026_01_11_000001_add_payment_fields_to_bookings.php`

```php
public function up(): void
{
    Schema::table('bookings', function (Blueprint $table) {
        $table->string('payment_intent_id')->nullable()->after('status');
        $table->string('refund_id')->nullable()->after('payment_intent_id');
        $table->string('refund_status')->nullable()->after('refund_id'); // pending|succeeded|failed
        $table->unsignedBigInteger('refund_amount')->nullable()->after('refund_status'); // cents
        $table->timestamp('cancelled_at')->nullable()->after('refund_amount');
        $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

        $table->index('refund_status'); // For reconciliation queries
    });
}
```

**Rationale**: Adds audit trail + refund tracking without touching existing columns.

---

### Step 2: Create BookingStatus Enum

**File**: `app/Enums/BookingStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING        = 'pending';
    case CONFIRMED      = 'confirmed';
    case REFUND_PENDING = 'refund_pending';
    case CANCELLED      = 'cancelled';
    case REFUND_FAILED  = 'refund_failed';

    public function isCancellable(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED, self::REFUND_FAILED]);
    }

    public function isTerminal(): bool
    {
        return $this === self::CANCELLED;
    }
}
```

**Rationale**: Type-safe status; replaces string constants. `isCancellable()` centralizes business rule.

---

### Step 3: Update Booking Model

**File**: `app/Models/Booking.php` — add cast and helper

```php
use App\Enums\BookingStatus;

protected function casts(): array
{
    return [
        'check_in'     => 'date',
        'check_out'    => 'date',
        'cancelled_at' => 'datetime',
        'status'       => BookingStatus::class,
    ];
}

public function isRefundable(): bool
{
    return $this->payment_intent_id !== null
        && $this->refund_id === null
        && $this->status->isCancellable();
}

public function calculateRefundAmount(): int
{
    $hoursUntilCheckIn = now()->diffInHours($this->check_in, false);
    $config = config('booking.cancellation');

    if ($hoursUntilCheckIn >= $config['full_refund_hours']) {
        return $this->amount; // Full refund
    }

    if ($hoursUntilCheckIn >= $config['partial_refund_hours']) {
        return (int) ($this->amount * $config['partial_refund_pct'] / 100);
    }

    return 0; // No refund
}
```

**Rationale**: Encapsulates refund calculation in model; testable in isolation.

---

### Step 4: Create CancellationService

**File**: `app/Services/CancellationService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;

final class CancellationService
{
    public function cancel(Booking $booking, User $actor): Booking
    {
        // Idempotency: already cancelled
        if ($booking->status === BookingStatus::CANCELLED) {
            Log::info('Cancellation skipped: already cancelled', ['booking_id' => $booking->id]);
            return $booking->fresh();
        }

        // Phase 1: Lock and mark as refund_pending
        $booking = $this->transitionToRefundPending($booking, $actor);

        // Phase 2: Process refund (outside transaction)
        if ($booking->isRefundable()) {
            $booking = $this->processRefund($booking);
        } else {
            // No payment = direct cancellation
            $booking = $this->finalizeCancellation($booking);
        }

        return $booking;
    }

    private function transitionToRefundPending(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $locked = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->first();

            if (!$locked->status->isCancellable()) {
                throw new \DomainException("Booking {$booking->id} is not cancellable.");
            }

            $locked->update([
                'status'       => $locked->isRefundable()
                    ? BookingStatus::REFUND_PENDING
                    : BookingStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ]);

            return $locked->fresh();
        });
    }

    private function processRefund(Booking $booking): Booking
    {
        $refundAmount = $booking->calculateRefundAmount();

        if ($refundAmount === 0) {
            return $this->finalizeCancellation($booking);
        }

        try {
            $refund = $booking->user->refund(
                $booking->payment_intent_id,
                ['amount' => $refundAmount]
            );

            return DB::transaction(function () use ($booking, $refund, $refundAmount) {
                $booking->update([
                    'status'        => BookingStatus::CANCELLED,
                    'refund_id'     => $refund->id,
                    'refund_status' => 'succeeded',
                    'refund_amount' => $refundAmount,
                ]);

                event(new BookingCancelled($booking));

                return $booking->fresh();
            });
        } catch (IncompletePayment|\Stripe\Exception\ApiErrorException $e) {
            Log::error('Refund failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);

            $booking->update([
                'status'        => BookingStatus::REFUND_FAILED,
                'refund_status' => 'failed',
            ]);

            throw $e;
        }
    }

    private function finalizeCancellation(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking->update(['status' => BookingStatus::CANCELLED]);
            event(new BookingCancelled($booking));
            return $booking->fresh();
        });
    }
}
```

**Rationale**:

- **Two-phase commit pattern**: DB lock → Stripe call → DB update
- Stripe call is **outside** transaction to avoid holding lock during network I/O
- `lockForUpdate()` prevents concurrent cancellation race
- Explicit error handling with `REFUND_FAILED` state for retry

---

### Step 5: Create Controller Action

**File**: `app/Http/Controllers/Api/BookingCancellationController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\CancellationService;
use Illuminate\Http\JsonResponse;
use Laravel\Cashier\Exceptions\IncompletePayment;

final class BookingCancellationController extends Controller
{
    public function __construct(
        private readonly CancellationService $cancellationService
    ) {}

    public function __invoke(Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);

        try {
            $booking = $this->cancellationService->cancel(
                $booking,
                auth()->user()
            );

            return response()->json([
                'message' => 'Booking cancelled successfully.',
                'data'    => new BookingResource($booking),
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (IncompletePayment|\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'message' => 'Refund processing failed. Our team has been notified.',
                'error'   => 'refund_failed',
            ], 422);
        }
    }
}
```

**Rationale**: Single-action controller for clarity. Returns 422 on business rule violation (not 400 — the request is well-formed but unprocessable).

---

### Step 6: Register Route

**File**: `routes/api.php` — add within authenticated group

```php
Route::post('bookings/{booking}/cancel', BookingCancellationController::class)
    ->middleware(['auth:sanctum', 'verified', 'throttle:cancellations'])
    ->name('bookings.cancel');
```

**Rationale**: Dedicated route, custom throttle group to prevent abuse.

---

### Step 7: Create Reconciliation Job

**File**: `app/Jobs/ReconcileRefundsJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ReconcileRefundsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function handle(): void
    {
        Booking::query()
            ->whereIn('status', [BookingStatus::REFUND_PENDING, BookingStatus::REFUND_FAILED])
            ->where('updated_at', '<', now()->subMinutes(5))
            ->chunk(50, function ($bookings) {
                foreach ($bookings as $booking) {
                    $this->reconcile($booking);
                }
            });
    }

    private function reconcile(Booking $booking): void
    {
        try {
            $stripeRefund = $booking->user
                ->stripe()
                ->refunds
                ->retrieve($booking->refund_id);

            if ($stripeRefund->status === 'succeeded') {
                $booking->update([
                    'status'        => BookingStatus::CANCELLED,
                    'refund_status' => 'succeeded',
                ]);
                Log::info('Reconciled refund', ['booking_id' => $booking->id]);
            }
        } catch (\Throwable $e) {
            Log::warning('Reconciliation failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
```

**Rationale**: Catches orphaned states (e.g., process died after Stripe success but before DB commit). Scheduled via `Kernel.php`.

---

### Step 8: Update BookingCancelled Notification

**File**: `app/Notifications/BookingCancelled.php` — ensure Mailable in `toMail()`

```php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Booking Cancelled — Confirmation')
        ->greeting("Hello {$notifiable->name},")
        ->line("Your booking #{$this->booking->id} has been cancelled.")
        ->lineIf(
            $this->booking->refund_amount > 0,
            "A refund of $" . number_format($this->booking->refund_amount / 100, 2) . " has been processed."
        )
        ->line('Thank you for staying with us.');
}
```

**Rationale**: Mailable via `MailMessage` inside `toMail()` only. Never `Mail::to()->send()` outside notifications.

---

## 6. Failure Modes & Edge Cases

### Comprehensive Failure Matrix

| Scenario                     | Detection                 | Response                                    | Recovery                         |
| ---------------------------- | ------------------------- | ------------------------------------------- | -------------------------------- |
| **Already cancelled**        | Status check in service   | Return 200 with existing state              | None needed (idempotent)         |
| **Policy violation** (< 24h) | `BookingPolicy@cancel`    | 403 Forbidden                               | None — business rule             |
| **Refund gateway timeout**   | `ApiErrorException`       | Set `refund_failed`, return 422             | `ReconcileRefundsJob` retries    |
| **Partial refund fails**     | Stripe exception          | Full failure (no partial commit)            | Retry or manual intervention     |
| **Race condition**           | `lockForUpdate()` blocks  | Second request waits, then sees `cancelled` | Idempotent return                |
| **Unauthorized user**        | Policy denies             | 403 Forbidden                               | None                             |
| **Soft-deleted booking**     | Route model binding fails | 404 Not Found                               | None (expected)                  |
| **Soft-deleted user**        | `$booking->user` is null  | Check before refund                         | Log + alert, set `refund_failed` |
| **Multi-tenant isolation**   | Policy checks `user_id`   | 403 if mismatch                             | None                             |
| **High-volume spikes**       | Throttle middleware       | 429 Too Many Requests                       | Retry-After header               |
| **Stripe downtime**          | Connection exception      | Set `refund_failed`                         | Reconciliation job               |

### When NOT to Rollback

1. **After Stripe refund succeeds** — never rollback the DB update; if it fails, the reconciliation job will fix it
2. **Notification dispatch failure** — booking state is correct; retry notification separately
3. **Cache invalidation failure** — data is correct; cache will expire naturally

### Allowed Intermediate States

- `refund_pending` — Stripe call in flight
- `refund_failed` — Stripe rejected; awaiting retry or manual fix

### Refund Succeeds But Status Unchanged

**Cause**: Process dies between Stripe success and DB commit.

**Recovery**: `ReconcileRefundsJob` queries Stripe for `refund_id`, confirms status, updates DB.

---

## 7. Testing Strategy

### Manual Verification Checklist

- [ ] Cancel booking with payment → refund issued, status = `cancelled`
- [ ] Cancel booking without payment → status = `cancelled`, no Stripe call
- [ ] Cancel already-cancelled booking → 200 OK, no side effects
- [ ] Cancel booking < 24h before check-in → 0% refund, status = `cancelled`
- [ ] Cancel booking 24-48h before check-in → 50% refund
- [ ] Cancel booking > 48h before check-in → 100% refund
- [ ] Cancel as non-owner → 403 Forbidden
- [ ] Cancel after check-in → 403 Forbidden
- [ ] Stripe timeout → 422, status = `refund_failed`
- [ ] Run `ReconcileRefundsJob` on `refund_pending` booking → status corrected

### Feature Tests

**File**: `tests/Feature/BookingCancellationTest.php`

```php
use Laravel\Cashier\Cashier;

public function test_cancel_booking_with_full_refund(): void
{
    Cashier::fake();

    $booking = Booking::factory()
        ->confirmed()
        ->withPayment()
        ->checkInFuture(hours: 72)
        ->create();

    $this->actingAs($booking->user)
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('bookings', [
        'id'            => $booking->id,
        'status'        => 'cancelled',
        'refund_status' => 'succeeded',
    ]);
}

public function test_cancel_is_idempotent(): void
{
    $booking = Booking::factory()->cancelled()->create();

    $this->actingAs($booking->user)
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertOk();

    // No state change
    $this->assertDatabaseHas('bookings', [
        'id'     => $booking->id,
        'status' => 'cancelled',
    ]);
}

public function test_unauthorized_user_cannot_cancel(): void
{
    $booking = Booking::factory()->confirmed()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertForbidden();
}
```

### What Is NOT Unit-Tested (And Why)

| Component              | Reason                                                                    |
| ---------------------- | ------------------------------------------------------------------------- |
| Stripe refund logic    | Covered by Cashier's own tests; we test integration via `Cashier::fake()` |
| Policy authorization   | Feature tests cover; unit testing policies adds little value              |
| Notification rendering | Visual; use Mailtrap/Mailhog for manual verification                      |
| Queue job dispatch     | `afterCommit()` is framework behavior; trust Laravel                      |

---

## 8. Decision Log

### Dedicated Endpoint vs. Embedded in PATCH

**Choice**: Dedicated `POST /bookings/{id}/cancel`  
**Rationale**: Cancellation triggers side effects (refund, notification); mixing with generic updates creates ambiguous semantics. RESTful purity (PATCH for state change) is less important than operational clarity.  
**Rejected**: `PATCH /bookings/{id}` with `{status: 'cancelled'}` — hides complexity, complicates authorization.

### Cashier vs. Custom Gateway Integration

**Choice**: Cashier exclusively  
**Rationale**: Handles idempotency keys, webhook verification, error mapping. No business case for manual Stripe SDK calls.  
**Rejected**: Direct Stripe SDK — duplicates Cashier, loses built-in protections.

### Synchronous vs. Asynchronous Refund Processing

**Choice**: **Synchronous**  
**Defense**:

- User expects immediate confirmation ("Is my money coming back?")
- Async introduces UX complexity (polling, push notifications, email-only confirmation)
- Stripe refunds are fast (~200-500ms P95); acceptable latency for user-facing action
- Failure path is clear: 422 response, `refund_failed` state, reconciliation job retries

**Rejected**: Async via job queue

- **Fatal flaw**: User sees "cancellation processing" but no confirmation for 5+ minutes
- Requires additional UI for status polling
- Adds debugging complexity (job failures, dead-letter queues)
- Stripe's synchronous API is reliable enough for primary path

### Default Business Policies

**Choice**: 48h full / 24h partial / 0% fee  
**Rationale**: Industry standard for hostels; simple for guests to understand; configurable for business changes.

### High Load & Future Extensions

**Survives high load**:

- `lockForUpdate()` serializes concurrent cancellations per booking (not global lock)
- Throttle middleware limits per-user abuse
- Reconciliation job processes in chunks, not bulk

**Extends to**:

- **Cancellation fees**: Add `fee_amount` column, subtract in `calculateRefundAmount()`
- **Multi-gateway**: Add `payment_provider` column, strategy pattern in `CancellationService`
- **Multi-tenant**: Add `tenant_id` to Booking, include in policy checks

### Multi-Tenant Isolation

**Approach**: Policy-based (`$booking->user_id === $user->id`).  
**Extension**: Add `tenant_id` to Booking, filter in repository/query scope.

### Observability

| Type        | Implementation                                                                                       |
| ----------- | ---------------------------------------------------------------------------------------------------- |
| **Logs**    | Structured JSON via Monolog; levels: INFO (success), WARNING (retry), ERROR (failure)                |
| **Metrics** | `cancellation_total` (counter), `refund_duration_seconds` (histogram), `refund_failure_rate` (gauge) |
| **Alerts**  | Slack/PagerDuty on `refund_failure_rate > 5%` sustained 5m                                           |
| **Tracing** | Sentry transaction spans for Stripe calls                                                            |

---

## 9. Production Recommendations

- [ ] **Stripe webhook**: Configure `refund.updated` webhook to update `refund_status` proactively
- [ ] **Rate limiting**: Separate `cancellations` throttle (e.g., 5/min per user)
- [ ] **Monitoring**: Dashboard for `refund_pending` bookings > 10 min old
- [ ] **Dead-letter queue**: Route failed `ReconcileRefundsJob` to separate queue for manual review
- [ ] **Audit log**: Persist cancellation requests (who, when, IP) for compliance
- [ ] **Feature flag**: Gate refund functionality for phased rollout (`config('features.refunds_enabled')`)
- [ ] **Stripe test mode**: Use `STRIPE_KEY=pk_test_*` in staging; never test against live

---

## 10. SPA / API Delta

| Aspect              | Livewire                         | React/Vue SPA         | API-only             |
| ------------------- | -------------------------------- | --------------------- | -------------------- |
| **Request format**  | Form POST                        | JSON POST             | JSON POST            |
| **Response format** | Redirect or JSON                 | JSON                  | JSON                 |
| **Error display**   | Blade flash / Livewire error bag | Toast / modal         | HTTP status + body   |
| **CSRF**            | Automatic                        | `X-XSRF-TOKEN` header | None (Sanctum token) |

**No architectural change** — controller logic identical. Response format handled by Laravel's content negotiation.

---

## Appendix: File Manifest

| File                                                                  | Purpose                       |
| --------------------------------------------------------------------- | ----------------------------- |
| `database/migrations/2026_01_11_*_add_payment_fields_to_bookings.php` | Schema extension              |
| `app/Enums/BookingStatus.php`                                         | Type-safe status enum         |
| `app/Models/Booking.php`                                              | Model updates (cast, helpers) |
| `app/Services/CancellationService.php`                                | Core cancellation logic       |
| `app/Http/Controllers/Api/BookingCancellationController.php`          | HTTP layer                    |
| `app/Jobs/ReconcileRefundsJob.php`                                    | Orphaned state recovery       |
| `app/Policies/BookingPolicy.php`                                      | Authorization (cancel method) |
| `app/Notifications/BookingCancelled.php`                              | Email notification            |
| `config/booking.php`                                                  | Business policy configuration |
| `routes/api.php`                                                      | Route registration            |
| `tests/Feature/BookingCancellationTest.php`                           | Integration tests             |
