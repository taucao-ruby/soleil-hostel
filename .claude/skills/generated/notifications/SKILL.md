---
name: notifications
description: "Skill for the Notifications area of soleil-hostel. 30 symbols across 8 files."
---

# Notifications

30 symbols | 8 files | Cohesion: 68%

## When to Use

- Working with code in `backend/`
- Understanding how BookingUpdated, BookingConfirmed, BookingCancelled work
- Modifying notifications-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Notifications/BookingNotificationTest.php` | updated_notification_skipped_for_cancelled_booking, updated_notification_includes_changes, updated_notification_has_correct_properties, confirmation_notification_is_queued_on_correct_queue, confirmation_notification_skipped_when_booking_not_confirmed (+6) |
| `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | booking_updated_template_renders_with_changes, booking_updated_skips_when_booking_cancelled, booking_confirmed_skips_when_status_changed, booking_cancelled_skips_when_status_not_cancelled, booking_cancelled_template_renders_with_refund_info (+1) |
| `backend/tests/Unit/Notifications/BookingNotificationTest.php` | booking_updated_notification_includes_changes, booking_confirmed_notification_can_be_sent, notifications_are_queued, notifications_use_correct_queue, booking_cancelled_notification_can_be_sent |
| `backend/app/Notifications/BookingUpdated.php` | BookingUpdated, toMail, toArray |
| `backend/app/Notifications/BookingCancelled.php` | BookingCancelled, toMail |
| `backend/app/Notifications/BookingConfirmed.php` | BookingConfirmed |
| `backend/app/Services/BookingService.php` | confirmBooking |
| `backend/tests/Feature/Stays/StayBackfillTest.php` | test_confirming_booking_twice_does_not_create_duplicate_stay |

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
| `booking_confirmed_notification_can_be_sent` | Method | `backend/tests/Unit/Notifications/BookingNotificationTest.php` | 25 |
| `notifications_are_queued` | Method | `backend/tests/Unit/Notifications/BookingNotificationTest.php` | 134 |
| `notifications_use_correct_queue` | Method | `backend/tests/Unit/Notifications/BookingNotificationTest.php` | 142 |
| `booking_confirmed_skips_when_status_changed` | Method | `backend/tests/Unit/Mail/EmailTemplateRenderingTest.php` | 151 |
| `confirmation_notification_is_queued_on_correct_queue` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 78 |
| `confirmation_notification_skipped_when_booking_not_confirmed` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 94 |
| `confirmed_notification_has_correct_properties` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 341 |
| `confirmBooking` | Method | `backend/app/Services/BookingService.php` | 93 |
| `test_confirming_booking_twice_does_not_create_duplicate_stay` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 28 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Handle → SupportsTags` | cross_community | 6 |
| `Handle → Flush` | cross_community | 6 |
| `HandleForceDelete → SupportsTags` | cross_community | 6 |
| `HandleForceDelete → Flush` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → SupportsTags` | cross_community | 5 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |
| `Handle → BookingConfirmed` | cross_community | 4 |
| `HandleForceDelete → BookingConfirmed` | cross_community | 4 |
| `HandlePaymentIntentSucceeded → BookingConfirmed` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Mail | 5 calls |
| Room | 2 calls |
| Listeners | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "BookingUpdated"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "notifications"})` — find related execution flows
3. Read key files listed above for implementation details
