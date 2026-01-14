# Email Templates

> Branded Markdown email templates for booking notifications

## Overview

Soleil Hostel uses **branded Markdown email templates** for all booking notifications, providing a professional, consistent appearance across all guest communications.

**Status**: ✅ Complete | **Tests**: 13 | **Last Updated**: January 12, 2026

---

## Architecture

### Why Markdown Templates?

| Approach                  | Pros                                | Cons                   |
| ------------------------- | ----------------------------------- | ---------------------- |
| **Markdown Templates** ✅ | Professional, branded, customizable | More files to maintain |
| Fluent MailMessage        | Simple, quick                       | Generic appearance     |
| Custom Mailables          | Full control                        | More code, complex     |

We chose Markdown templates because they provide:

- ✅ **Brand consistency** via `config/email-branding.php`
- ✅ **Custom theme** with brand colors
- ✅ **Responsive design** for mobile devices
- ✅ **Laravel components** for buttons, panels, tables
- ✅ **Easy customization** without code changes

---

## Templates

### Location

```
resources/views/mail/bookings/
├── confirmed.blade.php    # Booking confirmation email
├── cancelled.blade.php    # Booking cancellation email
└── updated.blade.php      # Booking update notification
```

### BookingConfirmed

**Trigger**: `BookingCreated` event  
**Subject**: `🎉 Booking Confirmed - Soleil Hostel`

**Content**:

- Brand header with logo
- Personalized greeting
- Booking details table (confirmation #, room, dates, total)
- "View Your Booking" button
- Contact information
- Footer with tagline

### BookingCancelled

**Trigger**: `BookingDeleted` event  
**Subject**: `Booking Cancelled - Soleil Hostel`

**Content**:

- Brand header with logo
- Cancellation confirmation
- Cancelled booking details (ID, room, dates)
- "Book Again" button
- Encouraging message for future visits
- Contact information

### BookingUpdated

**Trigger**: `BookingUpdated` event  
**Subject**: `Booking Updated - Soleil Hostel`

**Content**:

- Brand header with logo
- Update notification
- List of changes made
- Current booking details
- "View Your Booking" button
- Contact information

---

## Configuration

### Brand Settings

**File**: `config/email-branding.php`

```php
return [
    // Brand identity
    'name' => env('MAIL_BRAND_NAME', 'Soleil Hostel'),
    'tagline' => env('MAIL_BRAND_TAGLINE', 'Your Home Away From Home'),

    // Logo
    'logo' => [
        'url' => env('MAIL_LOGO_URL', null), // Falls back to APP_URL/logo.png
        'alt' => 'Soleil Hostel',
        'width' => '150',
    ],

    // Colors
    'colors' => [
        'primary' => env('MAIL_COLOR_PRIMARY', '#007BFF'),    // Buttons, links
        'secondary' => env('MAIL_COLOR_SECONDARY', '#6C757D'), // Muted text
        'success' => env('MAIL_COLOR_SUCCESS', '#28A745'),     // Success messages
        'danger' => env('MAIL_COLOR_DANGER', '#DC3545'),       // Error messages
        'background' => env('MAIL_COLOR_BACKGROUND', '#F8F9FA'), // Email background
    ],

    // Contact info
    'contact' => [
        'email' => env('MAIL_CONTACT_EMAIL', 'support@soleilhostel.com'),
        'phone' => env('MAIL_CONTACT_PHONE', '+1 (555) 123-4567'),
        'address' => 'Paradise City',
    ],

    // Footer
    'footer' => [
        'copyright' => '© ' . date('Y') . ' Soleil Hostel. All rights reserved.',
    ],
];
```

### Environment Variables

```bash
# .env
MAIL_BRAND_NAME="Soleil Hostel"
MAIL_BRAND_TAGLINE="Your Home Away From Home"
MAIL_LOGO_URL=https://yourcdn.com/logo.png
MAIL_COLOR_PRIMARY=#007BFF
MAIL_CONTACT_EMAIL=support@soleilhostel.com
MAIL_CONTACT_PHONE="+1 (555) 123-4567"
```

---

## Theme Customization

### Custom Theme

**File**: `resources/views/vendor/mail/html/themes/soleil.css`

The custom theme defines:

- **Buttons**: Brand primary color (#007BFF)
- **Typography**: Clean, readable fonts
- **Panels**: Styled for booking details
- **Tables**: Formatted for data presentation
- **Mobile**: Responsive design

### Enabling Theme

**File**: `config/mail.php`

```php
'markdown' => [
    'theme' => env('MAIL_MARKDOWN_THEME', 'soleil'),
    'paths' => [
        resource_path('views/vendor/mail'),
    ],
],
```

---

## Template Structure

### Example: confirmed.blade.php

```blade
@component('mail::message')

{{-- Brand Header --}}
<div style="text-align: center; margin-bottom: 24px;">
    @if(config('email-branding.logo.url'))
        <img src="{{ config('email-branding.logo.url') }}"
             alt="{{ config('email-branding.logo.alt') }}"
             width="{{ config('email-branding.logo.width') }}">
    @endif
    <p style="color: {{ config('email-branding.colors.secondary') }};">
        {{ config('email-branding.tagline') }}
    </p>
</div>

{{-- Content --}}
# 🎉 Booking Confirmed!

Hello **{{ $guestName }}**,

Thank you for choosing {{ config('email-branding.name') }}!

{{-- Booking Details Panel --}}
@component('mail::panel')
| Detail | Information |
|:-------|:------------|
| **Confirmation Number** | #{{ $bookingId }} |
| **Room** | {{ $roomName }} |
| **Check-in** | {{ $checkIn }} |
| **Check-out** | {{ $checkOut }} |
| **Total** | ${{ number_format($totalPrice, 2) }} |
@endcomponent

{{-- CTA Button --}}
@component('mail::button', ['url' => $viewBookingUrl, 'color' => 'primary'])
View Your Booking
@endcomponent

{{-- Contact Info --}}
Questions? Contact us:
- 📧 {{ config('email-branding.contact.email') }}
- 📞 {{ config('email-branding.contact.phone') }}

{{ config('email-branding.footer.copyright') }}

@endcomponent
```

---

## Security

### XSS Protection

All user-supplied data is escaped using Laravel's `e()` helper:

```php
// In Notification class
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->markdown('mail.bookings.confirmed', [
            'guestName' => e($this->booking->guest_name), // XSS-safe
            'roomName' => e($this->booking->room->name),  // XSS-safe
            // ...
        ]);
}
```

### Verified Fields

| Field         | Source                   | Escaped  |
| ------------- | ------------------------ | -------- |
| `guestName`   | User input               | ✅ `e()` |
| `roomName`    | Database (admin-created) | ✅ `e()` |
| `bookingId`   | Auto-generated integer   | Safe     |
| `totalPrice`  | Calculated decimal       | Safe     |
| `checkIn/Out` | Carbon formatted         | Safe     |

---

## Testing

### Test File

**Location**: `tests/Unit/Mail/EmailTemplateRenderingTest.php`

### Test Coverage (13 tests)

```php
// View existence
✓ booking confirmed template exists
✓ booking cancelled template exists
✓ booking updated template exists

// Rendering
✓ booking confirmed template renders with data
✓ booking cancelled template renders with data
✓ booking updated template renders with data

// Brand elements
✓ templates include brand name
✓ templates include contact email
✓ templates include footer copyright

// XSS protection
✓ templates escape guest name for xss protection
✓ templates escape room name for xss protection

// Config
✓ email branding config exists
✓ mail theme is set to soleil
```

### Running Tests

```bash
# Run email template tests only
php artisan test tests/Unit/Mail/EmailTemplateRenderingTest.php

# Run all notification tests
php artisan test --filter=Notification
```

---

## Integration with Notifications

### How Templates Connect to Notifications

```php
// BookingConfirmed notification
class BookingConfirmed extends Notification implements ShouldQueue
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🎉 Booking Confirmed - ' . config('email-branding.name'))
            ->markdown('mail.bookings.confirmed', [
                'guestName' => e($this->booking->guest_name),
                'bookingId' => $this->booking->id,
                'roomName' => e($this->booking->room->name),
                'checkIn' => $this->booking->check_in->format('l, F j, Y'),
                'checkOut' => $this->booking->check_out->format('l, F j, Y'),
                'totalPrice' => $this->booking->amount ?? 0,
                'viewBookingUrl' => url('/bookings/' . $this->booking->id),
            ]);
    }
}
```

### Event Flow

```
User creates booking
       ↓
BookingCreated event dispatched
       ↓
SendBookingConfirmation listener triggered
       ↓
BookingConfirmed notification queued
       ↓
Queue worker processes notification
       ↓
→ mail.bookings.confirmed template rendered
       ↓
Email sent to guest
```

---

## Customization Guide

### Adding a New Template

1. **Create template file**:

   ```bash
   touch resources/views/mail/bookings/reminder.blade.php
   ```

2. **Add template content**:

   ```blade
   @component('mail::message')

   # Booking Reminder

   Hello **{{ $guestName }}**,

   Your stay begins tomorrow!

   @component('mail::button', ['url' => $viewBookingUrl])
   View Booking
   @endcomponent

   @endcomponent
   ```

3. **Create notification class**:

   ```php
   php artisan make:notification BookingReminder
   ```

4. **Use template in notification**:
   ```php
   public function toMail($notifiable): MailMessage
   {
       return (new MailMessage)
           ->markdown('mail.bookings.reminder', [...]);
   }
   ```

### Modifying Brand Colors

Update `config/email-branding.php` or use environment variables:

```bash
MAIL_COLOR_PRIMARY=#FF5722  # Orange buttons
MAIL_COLOR_SUCCESS=#4CAF50  # Green accents
```

---

## Related Documentation

- [EMAIL_NOTIFICATIONS.md](../guides/EMAIL_NOTIFICATIONS.md) - Full notification guide
- [EVENTS.md](../architecture/EVENTS.md) - Event-driven architecture
- [BOOKING.md](./BOOKING.md) - Booking system documentation
