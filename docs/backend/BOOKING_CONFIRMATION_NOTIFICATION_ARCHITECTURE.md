# Booking Confirmation Notification Architecture

> **Target:** Laravel 12.46+ · Redis queues · Production-grade async email delivery  
> **Audience:** Principal/Staff backend engineers  
> **Last Updated:** January 2026

---

## High-Level Summary

- **Single entry point:** `BookingConfirmedNotification` class—all confirmation emails flow through Laravel's Notification system, never raw Mailables or custom Jobs
- **Async-only:** Notification implements `ShouldQueue`; email rendering and SMTP delivery happen entirely in queue workers
- **Existing infrastructure leveraged:** Your codebase already has `BookingConfirmedNotification`, `BookingCancelledNotification`, `BookingModifiedNotification` on the `notifications` queue—this document formalizes the architecture
- **Redis queue driver:** Already configured (`QUEUE_CONNECTION=redis`), `retry_after: 90s`, failed jobs use `database-uuids` driver
- **No Horizon yet:** Strongly recommended for production—covered in Production Recommendations
- **Idempotency via database state:** Booking status transitions guard against duplicate sends; no external deduplication service needed
- **Soft-delete aware:** Existing `SoftDeletes` trait on Booking model handled via job serialization rules
- **Multi-channel ready:** `toMail()` + `toArray()` already present—adding SMS/push requires only new `toVonage()`/`toFcm()` methods
- **Rate limiting:** Laravel's native `RateLimiter` at dispatch site + queue middleware for per-user throttling

---

## 1. Laravel Provides vs. You Configure vs. Do Not Touch

### Out-of-the-Box (Laravel provides)

| Mechanism                                          | What It Does                                                        | Your Role                                      |
| -------------------------------------------------- | ------------------------------------------------------------------- | ---------------------------------------------- |
| `ShouldQueue` interface                            | Tells Laravel to serialize notification to a queued job             | Implement on Notification class                |
| `Illuminate\Notifications\SendQueuedNotifications` | Internal job that wraps your notification, handles channel dispatch | **DO NOT EXTEND**                              |
| `failed_jobs` table                                | Stores permanently failed jobs with payload + exception             | Run `php artisan queue:failed-table` migration |
| `retry_after` (config)                             | Prevents zombie job re-pickup; default 90s in your config           | Tune only if SMTP is slow                      |
| `tries` / `backoff` properties                     | Per-notification retry control                                      | Set on Notification class                      |
| `SerializesModels` trait                           | Stores model IDs, re-fetches from DB when job runs                  | Automatic on Notifications                     |
| Mail transport retry                               | Symfony Mailer retries transient SMTP failures internally           | **DO NOT WRAP**                                |

### You Must Configure

| Item                     | File                 | Reason                                                                           |
| ------------------------ | -------------------- | -------------------------------------------------------------------------------- |
| `QUEUE_CONNECTION=redis` | `.env`               | Already set; ensures async by default                                            |
| `REDIS_QUEUE=default`    | `.env`               | Default queue name; your notifications use `notifications` queue via `onQueue()` |
| `retry_after`            | `config/queue.php`   | 90s is sane; increase only if mail provider has >60s latency                     |
| Horizon (recommended)    | `config/horizon.php` | Supervisor config, queue priorities, metrics dashboard                           |
| Failed job pruning       | Scheduler command    | `$schedule->command('queue:prune-failed --hours=168')->daily()`                  |

### Do Not Touch (Non-Negotiable)

| Component                                    | Why                                                                                                                               |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `SendQueuedNotifications` job internals      | Laravel handles channel routing, exception capture, retry. Custom wrappers break Notification::fake() and add untested edge cases |
| `Illuminate\Mail\SendQueuedMailable`         | Only used if you queue Mailables directly—which you must not do                                                                   |
| Queue worker signal handling                 | SIGTERM/SIGKILL handling is battle-tested; custom signal handlers cause zombie workers                                            |
| `failed()` method signature on notifications | Must match Laravel's contract for failed job recording                                                                            |

---

## 2. Full Lifecycle Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 1. TRIGGER: Booking Created/Updated                                        │
│    └─ BookingController@store OR Livewire component calls BookingService   │
│       └─ Service persists Booking with status = 'confirmed'                │
│          └─ Model event (or explicit dispatch) triggers notification       │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 2. DISPATCH: $user->notify(new BookingConfirmedNotification($booking))     │
│    └─ Notification implements ShouldQueue                                  │
│       └─ Laravel wraps in SendQueuedNotifications job                      │
│          └─ Job serialized: notification class + constructor args          │
│             └─ Booking model → stored as ['id' => 123, 'class' => ...]     │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 3. QUEUE: Job pushed to Redis (queue: 'notifications')                     │
│    └─ Redis LPUSH to queue:notifications                                   │
│       └─ Job sits until worker picks up                                    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 4. WORKER EXECUTION: php artisan queue:work --queue=notifications          │
│    └─ Worker BRPOP from Redis                                              │
│       └─ Deserialize SendQueuedNotifications job                           │
│          └─ Re-hydrate Booking model from DB (fresh data!)                 │
│             └─ Call $notification->toMail($notifiable)                     │
│                └─ MailMessage built → handed to mail channel               │
│                   └─ Symfony Mailer sends via configured transport         │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                          ┌───────────┴───────────┐
                          ▼                       ▼
┌──────────────────────────────┐  ┌──────────────────────────────────────────┐
│ 5a. SUCCESS                  │  │ 5b. FAILURE                              │
│  └─ Job deleted from queue   │  │  └─ Exception thrown                     │
│     └─ NotificationSent      │  │     └─ Job released for retry (backoff)  │
│        event fired           │  │        └─ After max tries → failed_jobs  │
│        (optional listener)   │  │           └─ NotificationFailed event    │
└──────────────────────────────┘  └──────────────────────────────────────────┘
```

### Key Mechanics Deep-Dive

**Serialization:** When `$user->notify()` is called, Laravel does NOT serialize the Booking Eloquent instance. It extracts `Booking::class` + primary key. On worker execution, it runs `Booking::find($id)`. This means:

- Worker always gets **current** database state (email changes, soft-deletes visible)
- If model is deleted, `ModelNotFoundException` → job fails (handled below)

**Channel Resolution:** `via()` method returns `['mail']`. Laravel's `ChannelManager` resolves `mail` to `Illuminate\Notifications\Channels\MailChannel`, which calls `toMail()`.

**MailMessage vs Mailable:** Your existing notifications use `MailMessage` (fluent builder). This is correct—Mailables are only needed for complex layouts. If you need a Mailable, return it from `toMail()`:

```php
public function toMail(object $notifiable): Mailable
{
    return (new BookingConfirmationMailable($this->booking))
        ->to($notifiable->email);
}
```

The Mailable is **not queued separately**—it's rendered inline during the already-queued notification job.

---

## 3. Implementation (Step-by-Step)

Your codebase already has `BookingConfirmedNotification`. Below are the **required properties and rationale** for production-grade operation.

### Step 1: Notification Class Structure

**File:** `app/Notifications/BookingConfirmedNotification.php`

```php
<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public readonly Booking $booking
    ) {
        $this->onQueue('notifications');
        $this->afterCommit(); // Critical: only dispatch after DB transaction commits
    }
```

**Rationale:**

- `$tries = 3`: Matches your existing `CreateBookingJob` pattern; prevents infinite retry loops
- `$backoff` array: Exponential backoff for transient SMTP failures; gives mail provider recovery time
- `afterCommit()`: **Critical**—if notification dispatches mid-transaction that rolls back, job references non-existent booking

### Step 2: Idempotency Guard in toMail()

```php
    public function toMail(object $notifiable): MailMessage|null
    {
        // Guard: booking may have been cancelled between queue and execution
        if ($this->booking->status !== BookingStatus::CONFIRMED) {
            return null; // Returning null skips the mail channel silently
        }

        return (new MailMessage)
            ->subject('Booking Confirmed: ' . $this->booking->room->name)
            ->greeting('Hello ' . $this->booking->guest_name . ',')
            ->line('Your booking has been confirmed.')
            ->line('Check-in: ' . $this->booking->check_in->format('M j, Y'))
            ->line('Check-out: ' . $this->booking->check_out->format('M j, Y'))
            ->action('View Booking', url('/bookings/' . $this->booking->id));
    }
```

> **Note:** Use `BookingStatus::CONFIRMED` enum instead of `Booking::STATUS_CONFIRMED` string constant.

**Rationale:**

- Status check at **execution time** (not dispatch time) catches race conditions
- Returning `null` from `toMail()` is Laravel's official skip mechanism—no exception, no retry, no failed job

### Step 3: Handle Missing Models

```php
    public bool $deleteWhenMissingModels = true;
```

**Rationale:**

- If booking is hard-deleted (edge case) or soft-deleted and not in scope, Laravel throws `ModelNotFoundException`
- `$deleteWhenMissingModels = true` silently discards the job instead of sending to `failed_jobs`
- Alternative: Set to `false` and implement `failed()` for logging

> ⚠️ **Trade-off:** You lose observability for missing-model cases unless you log explicitly. In production, silent drops can mask data corruption or unexpected deletions. Consider adding explicit logging in a custom `restoreModel()` override or monitoring for unusually high job completion rates without corresponding sent emails.

### Step 4: Explicit Failure Handling

```php
    public function failed(\Throwable $exception): void
    {
        // Log for alerting; do NOT re-queue manually
        Log::error('BookingConfirmedNotification failed permanently', [
            'booking_id' => $this->booking->id ?? 'unknown',
            'exception' => $exception->getMessage(),
        ]);

        // Optional: Notify ops team via Slack/PagerDuty
        // Notification::route('slack', config('services.slack.ops_webhook'))
        //     ->notify(new OpsAlertNotification('Booking email failed', $exception));
    }
```

**Rationale:**

- `failed()` is called **after** all retries exhausted
- Do NOT dispatch another notification here—that creates infinite loops
- Ops alerting is appropriate; customer retry should be manual

### Step 5: Dispatch Site (Service Layer)

**File:** `app/Services/BookingService.php`

```php
public function confirmBooking(Booking $booking): Booking
{
    if ($booking->status !== BookingStatus::PENDING) {
        throw new \RuntimeException(
            "Cannot confirm booking: current status is '{$booking->status->value}', expected 'pending'"
        );
    }

    return DB::transaction(function () use ($booking) {
        $booking->update(['status' => BookingStatus::CONFIRMED]);

        // Dispatch notification—afterCommit() ensures it waits for transaction
        $booking->user->notify(new BookingConfirmed($booking));

        return $booking->fresh();
    });
}
```

> **Note:** Use `BookingStatus` enum for all status comparisons and assignments.

**Rationale:**

- Notification inside transaction + `afterCommit()` = guaranteed consistency
- Never dispatch in Model events for notifications—too implicit, hard to test, triggers on seeding

### Step 6: Rate Limiting (Dispatch-Site)

**File:** `app/Services/BookingService.php`

```php
use Illuminate\Support\Facades\RateLimiter;

public function confirmBooking(Booking $booking): Booking
{
    $rateLimitKey = 'booking-confirm:' . $booking->user_id;

    if (RateLimiter::tooManyAttempts($rateLimitKey, perMinute: 5)) {
        Log::warning('Rate limit hit for booking confirmation', [
            'user_id' => $booking->user_id,
        ]);
        // Still confirm booking, just skip notification
    } else {
        RateLimiter::hit($rateLimitKey, decaySeconds: 60);
        $booking->user->notify(new BookingConfirmedNotification($booking));
    }

    // ... rest of confirmation logic
}
```

**Rationale:**

- Prevents abuse (scripted booking spam)
- Rate limit at dispatch, not in queue worker—preserves queue throughput
- Business decision: confirmation email is nice-to-have, not critical-path

> ⚠️ **Business Trade-off:** This pattern treats booking confirmation email as a **non-critical side-effect**. The booking itself is persisted regardless of notification success. Do NOT copy this pattern for critical transactional emails (password reset, 2FA codes) where delivery failure should block or alert the user.

---

## 4. Failure Modes & Edge Cases

### Queue Worker Down / Connection Lost

| Scenario                            | Laravel Behavior                                               | Your Action                                     |
| ----------------------------------- | -------------------------------------------------------------- | ----------------------------------------------- |
| Worker process dies mid-job         | Job stays reserved until `retry_after` (90s), then re-queued   | Monitor worker uptime; use Supervisor/systemd   |
| Redis connection lost               | `RedisException` thrown; job not ACKed; re-queued on reconnect | Redis Sentinel/Cluster for HA                   |
| Worker can't reach Redis on startup | Worker exits with error                                        | Supervisor restarts; alert on repeated failures |

### Failed Delivery (SMTP Errors)

| Error Type                        | Retry Behavior            | Final State                            |
| --------------------------------- | ------------------------- | -------------------------------------- |
| 4xx (rate limit, greylisting)     | Retries per `$backoff`    | Success after backoff OR `failed_jobs` |
| 5xx (invalid recipient, rejected) | Retries exhausted quickly | `failed_jobs` + `failed()` called      |
| Connection timeout                | Retries with backoff      | Usually succeeds on retry 2-3          |
| TLS handshake failure             | Fails immediately         | `failed_jobs`; check mail config       |

### Duplicate Emails (Idempotency)

**Root Causes:**

1. Job timeout during SMTP send → re-queued → sent twice
2. Transaction rollback after notification queued (solved by `afterCommit()`)
3. User clicks "resend confirmation" rapidly

**Mitigations:**

1. Increase `retry_after` to exceed SMTP timeout (already 90s > typical 30s timeout)
2. `afterCommit()` on notification constructor
3. Rate limiting at dispatch site (Step 6)

**NOT recommended:** Database "sent_at" flag checked in `toMail()`. Adds query overhead and doesn't prevent race between check and send.

### User Email Changed After Booking

**Behavior:** Notification uses `$notifiable->email` at **execution time**, not dispatch time. Model re-hydrated from DB means user gets email at their **current** address.

**If this is wrong for your business:** Store email in Booking model (`guest_email` already exists) and use custom routing:

```php
public function routeNotificationForMail(): string
{
    return $this->booking->guest_email; // Captured at booking time
}
```

### Soft-Deleted Bookings/Users

| Entity Deleted       | Default Behavior                                 | Recommendation                                                       |
| -------------------- | ------------------------------------------------ | -------------------------------------------------------------------- |
| Booking soft-deleted | `ModelNotFoundException` (not in default scope)  | Use `$deleteWhenMissingModels = true`                                |
| User soft-deleted    | Same exception                                   | Same solution                                                        |
| Need to send anyway  | Add `->withTrashed()` in custom `restoreModel()` | Only if business requires "cancellation" emails for deleted bookings |

### High-Volume Booking Spikes

**Symptoms:** Queue depth grows; emails delayed minutes/hours.

**Solutions (in order of preference):**

1. **Horizontal scaling:** More queue workers (`--queue=notifications` on multiple processes/servers)
2. **Queue prioritization:** Critical notifications on `high` queue; marketing on `low`
3. **Batching:** Not applicable for transactional emails—each must be unique
4. **Throttle at mail provider:** Use `ShouldBeUnique` + `uniqueFor()` to dedupe within window

### Multi-Tenant Isolation

**If you add tenants later:**

```php
// In Notification constructor
$this->onQueue("tenant-{$booking->tenant_id}-notifications");
```

Or use queue connections per tenant in `config/queue.php`.

**Horizon Integration (Required for Dynamic Queues):**

Horizon must be configured to discover tenant-specific queues. Two approaches:

1. **Wildcard queue config** (Horizon 5.x+):

   ```php
   // config/horizon.php
   'environments' => [
       'production' => [
           'supervisor-tenant-notifications' => [
               'connection' => 'redis',
               'queue' => ['tenant-*-notifications'], // Wildcard pattern
               'balance' => 'auto',
               'minProcesses' => 1,
               'maxProcesses' => 10,
           ],
       ],
   ],
   ```

2. **Dynamic queue registration** via `Horizon::queues()` in a service provider if you need runtime tenant discovery.

Without this configuration, Horizon workers will not process tenant-specific queues.

### Queue Backend Trade-offs

| Driver                   | Pros                                   | Cons                                       | When to Use                         |
| ------------------------ | -------------------------------------- | ------------------------------------------ | ----------------------------------- |
| **Redis** (your current) | Fast, reliable, supports priorities    | Single point of failure without Sentinel   | Default choice; add Sentinel for HA |
| **Database**             | No extra infra; transactional with app | Slower; polling; table bloat               | Dev/staging only                    |
| **SQS**                  | Managed, auto-scaling, no ops          | Higher latency; no priorities; AWS lock-in | Large scale + AWS-native            |
| **Beanstalkd**           | Simple, fast, priorities               | Less ecosystem support                     | Legacy migrations                   |

---

## 5. Testing Strategy

### Feature Tests (Mandatory)

**File:** `tests/Feature/Notifications/BookingConfirmedNotificationTest.php`

```php
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use App\Notifications\BookingConfirmedNotification;

public function test_notification_dispatched_on_booking_confirmation(): void
{
    Notification::fake();

    $user = User::factory()->create();
    $booking = Booking::factory()->for($user)->create(['status' => Booking::STATUS_PENDING]);

    app(BookingService::class)->confirmBooking($booking);

    Notification::assertSentTo(
        $user,
        BookingConfirmedNotification::class,
        fn ($notification) => $notification->booking->id === $booking->id
    );
}

public function test_notification_queued_on_correct_queue(): void
{
    Queue::fake();

    $user = User::factory()->create();
    $booking = Booking::factory()->for($user)->create(['status' => Booking::STATUS_PENDING]);

    $user->notify(new BookingConfirmedNotification($booking));

    Queue::assertPushedOn('notifications', \Illuminate\Notifications\SendQueuedNotifications::class);
}

public function test_notification_skipped_when_booking_cancelled(): void
{
    Notification::fake();

    $user = User::factory()->create();
    $booking = Booking::factory()->for($user)->create(['status' => Booking::STATUS_CANCELLED]);

    $notification = new BookingConfirmedNotification($booking);
    $result = $notification->toMail($user);

    $this->assertNull($result);
}

public function test_notification_not_sent_when_rate_limited(): void
{
    Notification::fake();
    RateLimiter::shouldReceive('tooManyAttempts')->andReturn(true);

    $user = User::factory()->create();
    $booking = Booking::factory()->for($user)->create();

    app(BookingService::class)->confirmBooking($booking);

    Notification::assertNothingSentTo($user);
}
```

### Manual Verification Checklist

- [ ] Queue worker running: `php artisan queue:work --queue=notifications`
- [ ] Create booking via UI/API → email received within 60s
- [ ] Check `jobs` table empty after processing (database driver) or Redis queue length = 0
- [ ] Kill worker mid-job → job reappears after 90s, processes successfully
- [ ] Force SMTP failure (invalid credentials) → job in `failed_jobs` after 3 attempts
- [ ] Cancel booking immediately after creation → no confirmation email sent
- [ ] Create 10 bookings rapidly → only 5 emails sent (rate limit), no errors

### What Is NOT Unit Tested (Intentionally)

| Component                           | Why Not Tested                                    |
| ----------------------------------- | ------------------------------------------------- |
| `SendQueuedNotifications` internals | Laravel's responsibility; tested in framework     |
| Redis queue push/pop                | Infrastructure; covered by integration tests      |
| Actual SMTP delivery                | External service; use mailhog/mailtrap in staging |
| Retry/backoff timing                | Queue worker internals; verify via manual testing |
| `failed_jobs` table writes          | Framework behavior; trust Laravel                 |

---

## 6. Production Recommendations

### Install Horizon (Strongly Recommended)

```bash
composer require laravel/horizon
php artisan horizon:install
```

**Configure:** `config/horizon.php`

```php
'environments' => [
    'production' => [
        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue' => ['notifications'],
            'balance' => 'auto',
            'minProcesses' => 2,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
        ],
    ],
],
```

**Rationale:** Auto-scaling workers, queue metrics, job/failure visibility, graceful restarts.

### Monitoring & Alerting

- **Queue depth:** Alert if `notifications` queue exceeds 1000 jobs for >5 minutes
- **Failed jobs count:** Alert on any new entry in `failed_jobs`
- **Worker uptime:** Supervisor/systemd + external monitoring (UptimeRobot, Datadog)
- **Email delivery:** Track `NotificationSent` event → log delivery confirmation

### Scheduler Commands

```php
// app/Console/Kernel.php (or routes/console.php in Laravel 11+)
$schedule->command('queue:prune-failed --hours=168')->daily();
$schedule->command('queue:prune-batches --hours=48')->daily();
$schedule->command('horizon:snapshot')->everyFiveMinutes(); // If using Horizon
```

### Scaling Strategies

| Load Level           | Strategy                                                                            |
| -------------------- | ----------------------------------------------------------------------------------- |
| <1000 emails/hour    | Single worker, `minProcesses: 1`                                                    |
| 1k-10k emails/hour   | Horizon auto-balance, `maxProcesses: 10`                                            |
| 10k-100k emails/hour | Dedicated notification workers on separate servers; consider SES/Mailgun batch APIs |
| >100k emails/hour    | Move to dedicated email service (Customer.io, Sendgrid) with webhook callbacks      |

---

## 7. Decision Log

### Notification Class vs Raw Mailable vs Custom Job

| Approach                        | Verdict | Rationale                                                                                              |
| ------------------------------- | ------- | ------------------------------------------------------------------------------------------------------ |
| **Notification (chosen)**       | ✅      | Multi-channel ready, `Notification::fake()` works, Laravel-idiomatic, database logging via `toArray()` |
| Raw Mailable queued             | ❌      | Single-channel, no `Notification::fake()`, harder to add SMS/push later                                |
| Custom Job dispatching Mailable | ❌      | Bypasses notification system, loses channel abstraction, double-serialization overhead                 |

### Async-Only vs Sync Fallback

| Approach                       | Verdict | Rationale                                                             |
| ------------------------------ | ------- | --------------------------------------------------------------------- |
| **Async only (chosen)**        | ✅      | Predictable latency, no request blocking, queue backpressure visible  |
| Sync fallback on queue failure | ❌      | User request blocks on SMTP; 30s timeout kills UX; masks queue issues |
| Sync in dev, async in prod     | ⚠️      | Acceptable only with `QUEUE_CONNECTION=sync` in `.env.testing`        |

### Why This Survives High Load

1. **Decoupled from HTTP:** Booking creation returns immediately; email is fire-and-forget
2. **Horizontal scale:** Add workers without code changes
3. **Backpressure visible:** Queue depth metrics tell you when to scale
4. **Retry logic is framework-native:** No custom retry bugs

### Multi-Channel Extension Path

Adding SMS (Vonage/Twilio):

```php
public function via(object $notifiable): array
{
    $channels = ['mail'];

    if ($notifiable->phone_verified_at) {
        $channels[] = 'vonage';
    }

    return $channels;
}

public function toVonage(object $notifiable): VonageMessage
{
    return (new VonageMessage)
        ->content('Your booking #' . $this->booking->id . ' is confirmed.');
}
```

Zero changes to dispatch site, queue config, or existing tests.

### Rejected Alternatives

| Alternative                               | Fatal Flaw                                                        |
| ----------------------------------------- | ----------------------------------------------------------------- |
| Model observer triggering notification    | Fires on seeders, factory creates, raw DB imports; no control     |
| Event listener with queued job            | Extra indirection; harder to trace; no benefit over direct notify |
| Database queue driver in production       | Polling overhead; table locking under load; no priorities         |
| Custom retry logic in `failed()`          | Creates infinite loops; breaks `failed_jobs` contract             |
| Storing "notification_sent_at" on booking | Race condition between check and send; doesn't prevent duplicates |
| Unique job IDs in Redis                   | Over-engineering; status check in `toMail()` is simpler           |

---

## SPA/API Flow Deltas

If triggering from React/Vue/Inertia or pure API instead of Livewire:

| Aspect              | Livewire                     | SPA/API                                                    |
| ------------------- | ---------------------------- | ---------------------------------------------------------- |
| Trigger point       | Same (Service layer)         | Same                                                       |
| Response format     | Livewire component re-render | JSON `{ "status": "confirmed", "notification": "queued" }` |
| Error handling      | Livewire exception handler   | API exception → JSON error response                        |
| Rate limit response | Flash message                | 429 JSON response                                          |

**The notification architecture is identical.** Only the HTTP response format differs.

---

## Appendix: Complete Notification Class Reference

For copy-paste into new projects (your existing class may already have most of this):

```php
<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly Booking $booking
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        if ($this->booking->status !== Booking::STATUS_CONFIRMED) {
            return null;
        }

        return (new MailMessage)
            ->subject('Booking Confirmed: ' . $this->booking->room->name)
            ->greeting('Hello ' . $this->booking->guest_name . ',')
            ->line('Your booking has been confirmed.')
            ->line('Check-in: ' . $this->booking->check_in->format('M j, Y'))
            ->line('Check-out: ' . $this->booking->check_out->format('M j, Y'))
            ->action('View Booking', url('/bookings/' . $this->booking->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'status' => $this->booking->status,
            'room' => $this->booking->room->name,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BookingConfirmedNotification failed', [
            'booking_id' => $this->booking->id ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

**Document version:** 1.0  
**Maintainer:** Backend Platform Team
