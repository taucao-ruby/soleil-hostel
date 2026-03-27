---
name: listeners
description: "Skill for the Listeners area of soleil-hostel. 34 symbols across 17 files."
---

# Listeners

34 symbols | 17 files | Cohesion: 73%

## When to Use

- Working with code in `backend/`
- Understanding how SendBookingUpdateNotification, BookingUpdated, SendBookingCancellation work
- Modifying listeners-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | handle, handleCreated, handleUpdated, handleDeleted, handleCancelled |
| `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | send_booking_update_listener_sends_notification_with_changes, send_booking_update_listener_does_not_send_if_no_changes, send_booking_cancellation_listener_sends_notification, send_booking_confirmation_listener_sends_notification, booking_created_event_triggers_confirmation_email |
| `backend/app/Services/BookingService.php` | invalidateUserBookings, invalidateBooking, softDelete |
| `backend/app/Listeners/SendBookingUpdateNotification.php` | SendBookingUpdateNotification, handle, normalizeDate |
| `backend/app/Services/RoomAvailabilityService.php` | invalidateAvailability, invalidateRoomCache |
| `backend/app/Http/Controllers/BookingController.php` | update, destroy |
| `backend/app/Listeners/SendBookingCancellation.php` | SendBookingCancellation, handle |
| `backend/app/Listeners/SendBookingConfirmation.php` | SendBookingConfirmation, handle |
| `backend/app/Listeners/QueryDebuggerListener.php` | handle, formatSql |
| `backend/app/Events/BookingUpdated.php` | BookingUpdated |

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
| `invalidateAvailability` | Method | `backend/app/Services/RoomAvailabilityService.php` | 214 |
| `invalidateUserBookings` | Method | `backend/app/Services/BookingService.php` | 241 |
| `invalidateBooking` | Method | `backend/app/Services/BookingService.php` | 251 |
| `handle` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 20 |
| `handleCreated` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 37 |
| `handleUpdated` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 45 |
| `handleDeleted` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 59 |
| `handleCancelled` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 67 |
| `handle` | Method | `backend/app/Listeners/SendBookingUpdateNotification.php` | 21 |
| `normalizeDate` | Method | `backend/app/Listeners/SendBookingUpdateNotification.php` | 57 |
| `send_booking_update_listener_sends_notification_with_changes` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 82 |
| `send_booking_update_listener_does_not_send_if_no_changes` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 116 |
| `update` | Method | `backend/app/Http/Controllers/BookingController.php` | 127 |
| `softDelete` | Method | `backend/app/Services/BookingService.php` | 285 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Handle → SupportsTags` | cross_community | 6 |
| `Handle → Flush` | cross_community | 6 |
| `Destroy → SupportsTags` | cross_community | 6 |
| `Destroy → Flush` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → SupportsTags` | cross_community | 5 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |
| `Destroy → SupportsTags` | cross_community | 5 |
| `Destroy → Flush` | cross_community | 5 |
| `Handle → SupportsTags` | cross_community | 4 |
| `Handle → Flush` | cross_community | 4 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Services | 5 calls |
| Room | 5 calls |
| Cache | 4 calls |
| Notifications | 3 calls |
| Booking | 1 calls |
| Controllers | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "SendBookingUpdateNotification"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "listeners"})` — find related execution flows
3. Read key files listed above for implementation details
