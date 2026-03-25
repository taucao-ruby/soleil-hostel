---
name: notifications
description: "Skill for the Notifications area of soleil-hostel. 40 symbols across 14 files."
---

# Notifications

40 symbols | 14 files | Cohesion: 76%

## When to Use

- Working with code in `backend/`
- Understanding how BookingCancelled, SendBookingCancellation, BookingUpdated work
- Modifying notifications-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Notifications/BookingNotificationTest.php` | cancellation_notification_skipped_when_booking_not_cancelled, cancelled_notification_has_correct_properties, updated_notification_skipped_for_cancelled_booking, updated_notification_includes_changes, updated_notification_has_correct_properties (+8) |
| `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | booking_cancelled_template_renders_with_refund_info, booking_cancelled_template_renders_without_refund_when_none, booking_cancelled_skips_when_status_not_cancelled, booking_updated_template_renders_with_changes, booking_updated_skips_when_booking_cancelled (+1) |
| `backend/tests/Unit/Notifications/BookingNotificationTest.php` | booking_cancelled_notification_can_be_sent, booking_updated_notification_includes_changes, booking_confirmed_notification_can_be_sent, notifications_are_queued, notifications_use_correct_queue |
| `backend/app/Notifications/BookingUpdated.php` | BookingUpdated, toMail, toArray |
| `backend/app/Notifications/BookingCancelled.php` | BookingCancelled, toMail |
| `backend/app/Listeners/SendBookingCancellation.php` | SendBookingCancellation, handle |
| `backend/app/Services/BookingService.php` | confirmBooking, cancelBooking |
| `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | send_booking_cancellation_listener_sends_notification |
| `backend/app/Notifications/BookingConfirmed.php` | BookingConfirmed |
| `backend/tests/Feature/Stays/StayBackfillTest.php` | test_confirming_booking_twice_does_not_create_duplicate_stay |

## Entry Points

Start here when exploring this area:

- **`BookingCancelled`** (Class) — `backend/app/Notifications/BookingCancelled.php:26`
- **`SendBookingCancellation`** (Class) — `backend/app/Listeners/SendBookingCancellation.php:20`
- **`BookingUpdated`** (Class) — `backend/app/Notifications/BookingUpdated.php:25`
- **`BookingConfirmed`** (Class) — `backend/app/Notifications/BookingConfirmed.php:34`
- **`toMail`** (Method) — `backend/app/Notifications/BookingCancelled.php:72`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `BookingCancelled` | Class | `backend/app/Notifications/BookingCancelled.php` | 26 |
| `SendBookingCancellation` | Class | `backend/app/Listeners/SendBookingCancellation.php` | 20 |
| `BookingUpdated` | Class | `backend/app/Notifications/BookingUpdated.php` | 25 |
| `BookingConfirmed` | Class | `backend/app/Notifications/BookingConfirmed.php` | 34 |
| `toMail` | Method | `backend/app/Notifications/BookingCancelled.php` | 72 |
| `handle` | Method | `backend/app/Listeners/SendBookingCancellation.php` | 25 |
| `booking_cancelled_notification_can_be_sent` | Method | `backend/tests/Unit/Notifications/BookingNotificationTest.php` | 53 |
| `booking_cancelled_template_renders_with_refund_info` | Method | `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | 91 |
| `booking_cancelled_template_renders_without_refund_when_none` | Method | `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | 108 |
| `booking_cancelled_skips_when_status_not_cancelled` | Method | `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | 164 |
| `cancellation_notification_skipped_when_booking_not_cancelled` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 171 |
| `cancelled_notification_has_correct_properties` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 357 |
| `send_booking_cancellation_listener_sends_notification` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 56 |
| `toMail` | Method | `backend/app/Notifications/BookingUpdated.php` | 70 |
| `toArray` | Method | `backend/app/Notifications/BookingUpdated.php` | 100 |
| `booking_updated_notification_includes_changes` | Method | `backend/tests/Unit/Notifications/BookingNotificationTest.php` | 75 |
| `booking_updated_template_renders_with_changes` | Method | `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | 129 |
| `booking_updated_skips_when_booking_cancelled` | Method | `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | 178 |
| `updated_notification_skipped_for_cancelled_booking` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 203 |
| `updated_notification_includes_changes` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 218 |

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
| Room | 4 calls |
| Services | 2 calls |
| Feature | 2 calls |
| Booking | 1 calls |
| Controllers | 1 calls |

## How to Explore

1. `gitnexus_context({name: "BookingCancelled"})` — see callers and callees
2. `gitnexus_query({query: "notifications"})` — find related execution flows
3. Read key files listed above for implementation details
