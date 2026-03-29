---
name: listeners
description: "Skill for the Listeners area of soleil-hostel. 26 symbols across 16 files."
---

# Listeners

26 symbols | 16 files | Cohesion: 73%

## When to Use

- Working with code in `backend/`
- Understanding how SendBookingUpdateNotification, BookingUpdated, SendBookingCancellation work
- Modifying listeners-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | send_booking_update_listener_sends_notification_with_changes, send_booking_update_listener_does_not_send_if_no_changes, send_booking_cancellation_listener_sends_notification, send_booking_confirmation_listener_sends_notification, booking_created_event_triggers_confirmation_email |
| `backend/app/Listeners/SendBookingUpdateNotification.php` | SendBookingUpdateNotification, handle, normalizeDate |
| `backend/app/Http/Controllers/BookingController.php` | update, destroy |
| `backend/app/Listeners/SendBookingCancellation.php` | SendBookingCancellation, handle |
| `backend/app/Listeners/SendBookingConfirmation.php` | SendBookingConfirmation, handle |
| `backend/app/Listeners/QueryDebuggerListener.php` | handle, formatSql |
| `backend/app/Events/BookingUpdated.php` | BookingUpdated |
| `backend/app/Services/BookingService.php` | softDelete |
| `backend/app/Events/BookingDeleted.php` | BookingDeleted |
| `backend/app/Events/BookingCreated.php` | BookingCreated |

## Entry Points

Start here when exploring this area:

- **`SendBookingUpdateNotification`** (Class) — `backend/app/Listeners/SendBookingUpdateNotification.php:16`
- **`BookingUpdated`** (Class) — `backend/app/Events/BookingUpdated.php:7`
- **`SendBookingCancellation`** (Class) — `backend/app/Listeners/SendBookingCancellation.php:20`
- **`BookingDeleted`** (Class) — `backend/app/Events/BookingDeleted.php:9`
- **`SendBookingConfirmation`** (Class) — `backend/app/Listeners/SendBookingConfirmation.php:15`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `SendBookingUpdateNotification` | Class | `backend/app/Listeners/SendBookingUpdateNotification.php` | 16 |
| `BookingUpdated` | Class | `backend/app/Events/BookingUpdated.php` | 7 |
| `SendBookingCancellation` | Class | `backend/app/Listeners/SendBookingCancellation.php` | 20 |
| `BookingDeleted` | Class | `backend/app/Events/BookingDeleted.php` | 9 |
| `SendBookingConfirmation` | Class | `backend/app/Listeners/SendBookingConfirmation.php` | 15 |
| `BookingCreated` | Class | `backend/app/Events/BookingCreated.php` | 8 |
| `handle` | Method | `backend/app/Listeners/SendBookingUpdateNotification.php` | 21 |
| `normalizeDate` | Method | `backend/app/Listeners/SendBookingUpdateNotification.php` | 57 |
| `send_booking_update_listener_sends_notification_with_changes` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 82 |
| `send_booking_update_listener_does_not_send_if_no_changes` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 116 |
| `update` | Method | `backend/app/Http/Controllers/BookingController.php` | 127 |
| `softDelete` | Method | `backend/app/Services/BookingService.php` | 285 |
| `handle` | Method | `backend/app/Listeners/SendBookingCancellation.php` | 25 |
| `send_booking_cancellation_listener_sends_notification` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 56 |
| `destroy` | Method | `backend/app/Http/Controllers/BookingController.php` | 185 |
| `handle` | Method | `backend/app/Listeners/SendBookingConfirmation.php` | 20 |
| `send_booking_confirmation_listener_sends_notification` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 30 |
| `booking_created_event_triggers_confirmation_email` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 142 |
| `test_booking_created_event_fires` | Method | `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | 32 |
| `invalidateRoom` | Method | `backend/app/Services/RoomService.php` | 55 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Destroy → SupportsTags` | cross_community | 6 |
| `Destroy → Flush` | cross_community | 6 |
| `Destroy → SupportsTags` | cross_community | 5 |
| `Destroy → Flush` | cross_community | 5 |
| `Handle → SupportsTags` | cross_community | 4 |
| `Handle → Flush` | cross_community | 4 |
| `Handle → SupportsTags` | cross_community | 4 |
| `Handle → Flush` | cross_community | 4 |
| `Handle → SupportsTags` | cross_community | 4 |
| `Handle → Flush` | cross_community | 4 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Services | 5 calls |
| Room | 5 calls |
| Notifications | 3 calls |
| Booking | 1 calls |
| Controllers | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "SendBookingUpdateNotification"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "listeners"})` — find related execution flows
3. Read key files listed above for implementation details
