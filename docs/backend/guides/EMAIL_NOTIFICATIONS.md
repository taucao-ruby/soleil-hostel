# Email Notifications Guide

## Overview

This project uses **Laravel's default Notification system** instead of custom Mailables for sending emails. This approach provides:

- ✅ **Automatic queueing** for async delivery
- ✅ **Multiple channels** (mail, database, SMS, etc.)
- ✅ **Built-in retry logic** with queue workers
- ✅ **Consistent API** across notification types
- ✅ **Less code** to maintain

## Architecture

### Why Notifications over Mailables?

| Aspect                | Notifications                      | Custom Mailables       |
| --------------------- | ---------------------------------- | ---------------------- |
| **Queueing**          | Built-in with `ShouldQueue`        | Must manually queue    |
| **Multiple Channels** | Easy (mail, database, SMS)         | Email only             |
| **Extensibility**     | Add channels without changing code | Requires refactoring   |
| **Code Volume**       | Less boilerplate                   | More verbose           |
| **Use Case**          | User-facing notifications          | Complex branded emails |

### When to Use Custom Mailables

Only use custom Mailables when you need:

- Complex HTML layouts with extensive branding
- Multiple email templates for the same event
- Direct mail sending without notifications
- Advanced Markdown customization

For most use cases, **Laravel Notifications are sufficient and preferred**.

---

## Available Notifications

### 1. BookingConfirmed

**Trigger:** When a new booking is created  
**Event:** `BookingCreated`  
**Listener:** `SendBookingConfirmation`

```php
// Automatically sent via event listener
event(new BookingCreated($booking));

// Manual sending (if needed)
Notification::route('mail', $booking->guest_email)
    ->notify(new BookingConfirmed($booking));
```

**Email Content:**

- Greeting with guest name
- Booking details (room, dates, price)
- "View Booking" action button
- Professional signature

---

### 2. BookingCancelled

**Trigger:** When a booking is deleted  
**Event:** `BookingDeleted`  
**Listener:** `SendBookingCancellation`

```php
// Automatically sent via event listener
event(new BookingDeleted($booking));

// Manual sending (if needed)
Notification::route('mail', $booking->guest_email)
    ->notify(new BookingCancelled($booking));
```

**Email Content:**

- Cancellation confirmation
- Cancelled booking details
- "Contact Support" action button
- Encouraging message for future bookings

---

### 3. BookingUpdated

**Trigger:** When booking details are modified  
**Event:** `BookingUpdated`  
**Listener:** `SendBookingUpdateNotification`

```php
// Automatically sent via event listener
event(new BookingUpdated($newBooking, $oldBooking));

// Manual sending with changes
$changes = ['check_in' => '2025-12-05', 'check_out' => '2025-12-10'];
Notification::route('mail', $booking->guest_email)
    ->notify(new BookingUpdated($booking, $changes));
```

**Email Content:**

- Update notification
- List of changes made
- Current booking details
- "View Booking" action button

---

## Implementation Details

### Event-Driven Architecture

```php
// Controller (BookingController.php)
public function store(StoreBookingRequest $request): JsonResponse
{
    $booking = $this->createBookingService->create(...);

    // Dispatch event - listeners handle everything else
    event(new BookingCreated($booking));

    return response()->json([...]);
}
```

```php
// EventServiceProvider.php
protected $listen = [
    BookingCreated::class => [
        InvalidateCacheOnBookingChange::class,  // Cache invalidation
        SendBookingConfirmation::class,         // Email notification
    ],
];
```

```php
// Listener (SendBookingConfirmation.php)
class SendBookingConfirmation implements ShouldQueue
{
    public function handle(BookingCreated $event): void
    {
        Notification::route('mail', $event->booking->guest_email)
            ->notify(new BookingConfirmed($event->booking));
    }
}
```

### Notification Structure

```php
// Notification (BookingConfirmed.php)
class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        $this->onQueue('notifications');  // Dedicated queue
    }

    public function via(object $notifiable): array
    {
        return ['mail'];  // Can add 'database', 'slack', etc.
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking Confirmation - Soleil Hostel')
            ->greeting('Hello ' . $this->booking->guest_name . '!')
            ->line('Your booking has been confirmed.')
            // ... more content
            ->action('View Booking', url('/bookings/' . $this->booking->id));
    }
}
```

---

## Configuration

### Mail Configuration

File: `config/mail.php`

```php
'default' => env('MAIL_MAILER', 'log'),  // Use 'smtp', 'ses', 'postmark', etc.

'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'name' => env('MAIL_FROM_NAME', 'Soleil Hostel'),
],
```

### Environment Variables

```bash
# .env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@soleilhostel.com
MAIL_FROM_NAME="Soleil Hostel"
```

### Queue Configuration

Notifications use the `notifications` queue for better organization:

```bash
# Start queue worker
php artisan queue:work --queue=notifications,default

# Or use supervisor in production
[program:soleil-queue-worker]
command=php /path/to/artisan queue:work --queue=notifications,default
```

---

## Testing

### Unit Testing Notifications

```php
use Illuminate\Support\Facades\Notification;
use App\Notifications\BookingConfirmed;

public function test_booking_confirmation_sent()
{
    Notification::fake();

    $booking = Booking::factory()->create();
    event(new BookingCreated($booking));

    Notification::assertSentTo(
        new AnonymousNotifiable(),
        BookingConfirmed::class,
        function ($notification, $channels, $notifiable) use ($booking) {
            return $notifiable->routes['mail'] === $booking->guest_email;
        }
    );
}
```

### Manual Testing

```bash
# Test email sending (uses log mailer by default)
php artisan tinker

>>> $booking = App\Models\Booking::first();
>>> Notification::route('mail', 'test@example.com')
      ->notify(new App\Notifications\BookingConfirmed($booking));

# Check logs
tail -f storage/logs/laravel.log
```

---

## Extending to Multiple Channels

### Adding Database Notifications

1. Run migration:

```bash
php artisan notifications:table
php artisan migrate
```

2. Update notification:

```php
public function via(object $notifiable): array
{
    return ['mail', 'database'];  // ← Add 'database'
}

public function toArray(object $notifiable): array
{
    return [
        'booking_id' => $this->booking->id,
        'message' => 'Your booking has been confirmed',
    ];
}
```

1. Retrieve in-app:

```php
// Get user notifications
$notifications = auth()->user()->notifications;

// Mark as read
auth()->user()->unreadNotifications->markAsRead();
```

### Adding Slack Notifications

```php
public function via(object $notifiable): array
{
    return ['mail', 'slack'];
}

public function toSlack(object $notifiable): SlackMessage
{
    return (new SlackMessage)
        ->content('New booking created!')
        ->attachment(function ($attachment) {
            $attachment->title('Booking #' . $this->booking->id)
                ->fields([
                    'Guest' => $this->booking->guest_name,
                    'Room' => $this->booking->room->name,
                ]);
        });
}
```

---

## Best Practices

### ✅ DO

- Use Notifications for user-facing emails
- Queue notifications with `ShouldQueue`
- Use dedicated queue (`notifications`)
- Handle failed notifications in listeners
- Test notifications with `Notification::fake()`

### ❌ DON'T

- Create custom Mailables for simple notifications
- Send emails synchronously in HTTP requests
- Use `Mail::send()` directly in controllers
- Forget to configure queue workers
- Skip error handling in listeners

---

## Troubleshooting

### Emails Not Sending

1. **Check queue workers:**

   ```bash
   php artisan queue:work --queue=notifications
   ```

2. **Check logs:**

   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Verify mail config:**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

### Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all
```

---

## Future Enhancements

### Potential Additions

1. **SMS Notifications** (via Twilio/Vonage)
2. **Push Notifications** (via Firebase)
3. **Admin Notifications** (for critical booking issues)
4. **Reminder Notifications** (24h before check-in)
5. **Review Request** (after check-out)

### Example: Review Request Notification

```php
// app/Notifications/ReviewRequest.php
class ReviewRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        $this->delay(now()->addDay());  // Send 1 day after checkout
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('How was your stay? - Soleil Hostel')
            ->greeting('Hello ' . $this->booking->guest_name . '!')
            ->line('We hope you enjoyed your stay at Soleil Hostel.')
            ->line('We would love to hear about your experience.')
            ->action('Write a Review', url('/reviews/create?booking=' . $this->booking->id))
            ->line('Thank you for choosing Soleil Hostel!');
    }
}
```

---

## Email Verification

### Overview

Email verification is **required** for users to access protected routes (bookings, etc.).

This uses Laravel's default verification system:

- `MustVerifyEmail` interface on User model
- `SendEmailVerificationNotification` listener
- `verified` middleware for protected routes
- Signed URLs for secure verification links

### Implementation Details

#### User Model

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    // ...
}
```

#### Protected Routes

```php
// Requires verified email
Route::middleware(['check_token_valid', 'verified'])->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    // ...
});
```

#### Verification Routes

| Method | Endpoint                               | Description                   |
| ------ | -------------------------------------- | ----------------------------- |
| GET    | `/api/email/verify`                    | Verification notice (403/200) |
| GET    | `/api/email/verify/{id}/{hash}`        | Verify email (signed URL)     |
| POST   | `/api/email/verification-notification` | Resend verification email     |
| GET    | `/api/email/verification-status`       | Check verification status     |

### Flow

```
1. User registers
   └─→ Registered event fires
       └─→ SendEmailVerificationNotification listener
           └─→ VerifyEmail notification sent

2. User clicks verification link
   └─→ GET /api/email/verify/{id}/{hash}
       └─→ EmailVerificationController::verify()
           └─→ Mark email as verified
               └─→ Verified event fires

3. User accesses protected route
   └─→ 'verified' middleware checks email_verified_at
       ├─→ Verified: Continue to route
       └─→ Unverified: 403 Forbidden

4. Unverified user logs in
   └─→ Auto-resend verification email
       └─→ User receives fresh verification link
```

### Auto-Resend on Login

**Feature:** Unverified users automatically receive a fresh verification email on login.

```php
// In all login controllers (AuthController, Auth\AuthController, HttpOnlyTokenController)
if (!$user->hasVerifiedEmail()) {
    $user->sendEmailVerificationNotification();
}
```

**Benefits:**

- No need to manually request resend
- Fresh verification link after password reset
- Better UX for users who lost the original email

### Centralized Email Change

**Method:** `User::changeEmail(string $newEmail)`

```php
// User model
public function changeEmail(string $newEmail): bool
{
    if ($this->email !== $newEmail) {
        $this->email = $newEmail;
        $this->email_verified_at = null;  // Force re-verification
        return true;
    }
    return false;
}

// Usage in controllers/services
if ($user->changeEmail($request->input('email'))) {
    $user->save();
    $user->sendEmailVerificationNotification();
    return response()->json(['message' => 'Email changed. Please verify your new email.']);
}
```

**Why centralize?**

- ✅ Prevents forgetting to clear `email_verified_at`
- ✅ Single source of truth for email change logic
- ✅ Easier to add logging/auditing later
- ✅ Returns boolean indicating if change occurred

### Frontend Integration

```typescript
// Check verification status after login
const checkVerification = async () => {
  const response = await api.get("/email/verification-status");
  if (!response.data.verified) {
    // Redirect to verification notice page
    router.push("/verify-email");
  }
};

// Request resend (now less needed due to auto-resend on login)
const resendVerification = async () => {
  await api.post("/email/verification-notification");
  showNotification("Verification email sent!");
};
```

### Testing

```php
// Unverified user blocked
$user = User::factory()->unverified()->create();
$this->actingAs($user)->get('/api/bookings')->assertStatus(403);

// Verified user allowed
$user = User::factory()->create(['email_verified_at' => now()]);
$this->actingAs($user)->get('/api/bookings')->assertStatus(200);

// Expired link rejected
$this->travel(2)->days();
$response = $this->get($expiredVerificationUrl);
$response->assertStatus(403);

// Auto-resend on login
$user = User::factory()->unverified()->create();
$response = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password']);
Notification::assertSentTo($user, VerifyEmail::class);

// changeEmail method
$changed = $user->changeEmail('new@example.com');
$this->assertTrue($changed);
$this->assertNull($user->email_verified_at);
```

---

## Related Documentation

- [Laravel Notifications](https://laravel.com/docs/notifications)
- [Laravel Mail](https://laravel.com/docs/mail)
- [Laravel Queues](https://laravel.com/docs/queues)
- [Laravel Email Verification](https://laravel.com/docs/verification)
- [Event System](./MONITORING_LOGGING.md#event-driven-architecture)

---

## Summary

✅ **Use Laravel Notifications** for booking emails  
✅ **Avoid custom Mailables** unless required for complex layouts  
✅ **Queue all notifications** for async delivery  
✅ **Handle failures** in listener `failed()` methods  
✅ **Extend to multiple channels** as needed  
✅ **Email verification required** for protected routes  
✅ **Signed URLs** for secure verification links

This architecture keeps the codebase maintainable, testable, and extensible.
