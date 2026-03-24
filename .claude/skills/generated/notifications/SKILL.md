---
name: notifications
description: "Skill for the Notifications area of soleil-hostel. 38 symbols across 13 files."
---

# Notifications

38 symbols | 13 files | Cohesion: 69%

## When to Use

- Working with code in `backend/`
- Understanding how BookingUpdated, BookingConfirmed, BookingCancelled work
- Modifying notifications-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Notifications/BookingNotificationTest.php` | updated_notification_skipped_for_cancelled_booking, updated_notification_includes_changes, updated_notification_has_correct_properties, confirmation_notification_is_dispatched_when_booking_confirmed, confirmation_notification_rate_limited (+8) |
| `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | booking_updated_template_renders_with_changes, booking_updated_skips_when_booking_cancelled, booking_confirmed_skips_when_status_changed, booking_cancelled_skips_when_status_not_cancelled, booking_cancelled_template_renders_with_refund_info (+1) |
| `backend/tests/Unit/Notifications/BookingNotificationTest.php` | booking_updated_notification_includes_changes, booking_confirmed_notification_can_be_sent, notifications_are_queued, notifications_use_correct_queue, booking_cancelled_notification_can_be_sent |
| `backend/app/Notifications/BookingUpdated.php` | BookingUpdated, toMail, toArray |
| `backend/app/Services/BookingService.php` | confirmBooking, cancelBooking |
| `backend/app/Notifications/BookingCancelled.php` | BookingCancelled, toMail |
| `backend/tests/Feature/Stays/StayBackfillTest.php` | test_confirming_booking_twice_does_not_create_duplicate_stay |
| `backend/app/Http/Controllers/BookingController.php` | confirm |
| `backend/app/Console/Commands/PruneOldSoftDeletedBookings.php` | handle |
| `backend/app/Http/Controllers/Payment/StripeWebhookController.php` | handlePaymentIntentSucceeded |

## Entry Points

Start here when exploring this area:

- **`BookingUpdated`** (Class) — `backend/app/Notifications/BookingUpdated.php:25`
- **`BookingConfirmed`** (Class) — `backend/app/Notifications/BookingConfirmed.php:34`
- **`BookingCancelled`** (Class) — `backend/app/Notifications/BookingCancelled.php:26`
- **`toMail`** (Method) — `backend/app/Notifications/BookingUpdated.php:70`
- **`toArray`** (Method) — `backend/app/Notifications/BookingUpdated.php:100`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `BookingUpdated` | Class | `backend/app/Notifications/BookingUpdated.php` | 25 |
| `BookingConfirmed` | Class | `backend/app/Notifications/BookingConfirmed.php` | 34 |
| `BookingCancelled` | Class | `backend/app/Notifications/BookingCancelled.php` | 26 |
| `toMail` | Method | `backend/app/Notifications/BookingUpdated.php` | 70 |
| `toArray` | Method | `backend/app/Notifications/BookingUpdated.php` | 100 |
| `booking_updated_notification_includes_changes` | Method | `backend/tests/Unit/Notifications/BookingNotificationTest.php` | 75 |
| `booking_updated_template_renders_with_changes` | Method | `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | 129 |
| `booking_updated_skips_when_booking_cancelled` | Method | `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | 178 |
| `updated_notification_skipped_for_cancelled_booking` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 203 |
| `updated_notification_includes_changes` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 218 |
| `updated_notification_has_correct_properties` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 373 |
| `confirmBooking` | Method | `backend/app/Services/BookingService.php` | 85 |
| `test_confirming_booking_twice_does_not_create_duplicate_stay` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 28 |
| `confirmation_notification_is_dispatched_when_booking_confirmed` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 58 |
| `confirmation_notification_rate_limited` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 109 |
| `cannot_confirm_already_confirmed_booking` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 132 |
| `confirm` | Method | `backend/app/Http/Controllers/BookingController.php` | 212 |
| `handle` | Method | `backend/app/Console/Commands/PruneOldSoftDeletedBookings.php` | 51 |
| `handlePaymentIntentSucceeded` | Method | `backend/app/Http/Controllers/Payment/StripeWebhookController.php` | 31 |
| `booking_confirmed_notification_can_be_sent` | Method | `backend/tests/Unit/Notifications/BookingNotificationTest.php` | 25 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Handle → SupportsTags` | cross_community | 6 |
| `Handle → Flush` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → SupportsTags` | cross_community | 5 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |
| `Handle → BookingConfirmed` | cross_community | 4 |
| `HandlePaymentIntentSucceeded → BookingConfirmed` | cross_community | 3 |
| `Handle → BookingResource` | cross_community | 3 |
| `HandleCancelConfirm → User` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Mail | 5 calls |
| Room | 3 calls |
| Services | 2 calls |
| Feature | 2 calls |
| Booking | 1 calls |
| Controllers | 1 calls |

## How to Explore

1. `gitnexus_context({name: "BookingUpdated"})` — see callers and callees
2. `gitnexus_query({query: "notifications"})` — find related execution flows
3. Read key files listed above for implementation details
