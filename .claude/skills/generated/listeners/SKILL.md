---
name: listeners
description: "Skill for the Listeners area of soleil-hostel. 19 symbols across 12 files."
---

# Listeners

19 symbols | 12 files | Cohesion: 74%

## When to Use

- Working with code in `backend/`
- Understanding how SendBookingUpdateNotification, BookingUpdated, SendBookingConfirmation work
- Modifying listeners-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | send_booking_update_listener_sends_notification_with_changes, send_booking_update_listener_does_not_send_if_no_changes, send_booking_confirmation_listener_sends_notification, booking_created_event_triggers_confirmation_email |
| `backend/app/Listeners/SendBookingUpdateNotification.php` | SendBookingUpdateNotification, handle, normalizeDate |
| `backend/app/Listeners/SendBookingConfirmation.php` | SendBookingConfirmation, handle |
| `backend/app/Listeners/QueryDebuggerListener.php` | handle, formatSql |
| `backend/app/Events/BookingUpdated.php` | BookingUpdated |
| `backend/app/Events/BookingCreated.php` | BookingCreated |
| `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | test_booking_created_event_fires |
| `backend/app/Services/RoomService.php` | invalidateRoom |
| `backend/app/Services/RoomAvailabilityService.php` | invalidateRoomCache |
| `backend/app/Listeners/InvalidateRoomAvailabilityCache.php` | handle |

## Entry Points

Start here when exploring this area:

- **`SendBookingUpdateNotification`** (Class) — `backend/app/Listeners/SendBookingUpdateNotification.php:16`
- **`BookingUpdated`** (Class) — `backend/app/Events/BookingUpdated.php:7`
- **`SendBookingConfirmation`** (Class) — `backend/app/Listeners/SendBookingConfirmation.php:15`
- **`BookingCreated`** (Class) — `backend/app/Events/BookingCreated.php:8`
- **`handle`** (Method) — `backend/app/Listeners/SendBookingUpdateNotification.php:21`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `SendBookingUpdateNotification` | Class | `backend/app/Listeners/SendBookingUpdateNotification.php` | 16 |
| `BookingUpdated` | Class | `backend/app/Events/BookingUpdated.php` | 7 |
| `SendBookingConfirmation` | Class | `backend/app/Listeners/SendBookingConfirmation.php` | 15 |
| `BookingCreated` | Class | `backend/app/Events/BookingCreated.php` | 8 |
| `handle` | Method | `backend/app/Listeners/SendBookingUpdateNotification.php` | 21 |
| `normalizeDate` | Method | `backend/app/Listeners/SendBookingUpdateNotification.php` | 57 |
| `send_booking_update_listener_sends_notification_with_changes` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 82 |
| `send_booking_update_listener_does_not_send_if_no_changes` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 116 |
| `handle` | Method | `backend/app/Listeners/SendBookingConfirmation.php` | 20 |
| `send_booking_confirmation_listener_sends_notification` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 30 |
| `booking_created_event_triggers_confirmation_email` | Method | `backend/tests/Feature/Listeners/BookingNotificationListenerTest.php` | 142 |
| `test_booking_created_event_fires` | Method | `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | 32 |
| `invalidateRoom` | Method | `backend/app/Services/RoomService.php` | 55 |
| `invalidateRoomCache` | Method | `backend/app/Services/RoomAvailabilityService.php` | 248 |
| `handle` | Method | `backend/app/Listeners/InvalidateRoomAvailabilityCache.php` | 37 |
| `handle` | Method | `backend/app/Listeners/InvalidateCacheOnBookingUpdated.php` | 26 |
| `handle` | Method | `backend/app/Listeners/InvalidateCacheOnBookingDeleted.php` | 25 |
| `handle` | Method | `backend/app/Listeners/QueryDebuggerListener.php` | 23 |
| `formatSql` | Method | `backend/app/Listeners/QueryDebuggerListener.php` | 59 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Destroy → SupportsTags` | cross_community | 6 |
| `Destroy → Flush` | cross_community | 6 |
| `Handle → SupportsTags` | cross_community | 4 |
| `Handle → Flush` | cross_community | 4 |
| `Handle → SupportsTags` | cross_community | 4 |
| `Handle → Flush` | cross_community | 4 |
| `Handle → SupportsTags` | cross_community | 4 |
| `Handle → Flush` | cross_community | 4 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 4 calls |
| Services | 3 calls |
| Notifications | 2 calls |

## How to Explore

1. `gitnexus_context({name: "SendBookingUpdateNotification"})` — see callers and callees
2. `gitnexus_query({query: "listeners"})` — find related execution flows
3. Read key files listed above for implementation details
