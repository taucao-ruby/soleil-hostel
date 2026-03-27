---
name: repositories
description: "Skill for the Repositories area of soleil-hostel. 90 symbols across 10 files."
---

# Repositories

90 symbols | 10 files | Cohesion: 97%

## When to Use

- Working with code in `backend/`
- Understanding how EloquentBookingRepository, EloquentRoomRepository, EloquentContactMessageRepository work
- Modifying repositories-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/Repositories/EloquentBookingRepositoryTest.php` | update_returns_true_on_success, update_returns_false_on_failure, delete_returns_true_on_success, delete_returns_false_on_failure, restore_returns_true_on_success (+32) |
| `backend/app/Repositories/EloquentBookingRepository.php` | EloquentBookingRepository, findById, findByIdOrFail, findByIdWithRelations, create (+14) |
| `backend/tests/Unit/Repositories/EloquentRoomRepositoryTest.php` | refresh_calls_refresh_on_model_and_returns_it, refresh_preserves_model_identity, find_by_id_with_bookings_returns_room_with_eager_loaded_bookings, find_by_id_with_bookings_returns_null_when_room_not_found, find_by_id_with_confirmed_bookings_returns_room_with_filtered_bookings (+13) |
| `backend/app/Repositories/EloquentRoomRepository.php` | EloquentRoomRepository, findByIdWithBookings, findByIdWithConfirmedBookings, getAllOrderedByName, hasOverlappingConfirmedBookings (+4) |
| `backend/app/Repositories/EloquentContactMessageRepository.php` | EloquentContactMessageRepository, markAsRead |
| `backend/app/Repositories/Contracts/BookingRepositoryInterface.php` | BookingRepositoryInterface |
| `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | RoomRepositoryInterface |
| `backend/tests/TestCase.php` | tearDown |
| `backend/app/Repositories/Contracts/ContactMessageRepositoryInterface.php` | ContactMessageRepositoryInterface |
| `backend/app/Models/ContactMessage.php` | markAsRead |

## Entry Points

Start here when exploring this area:

- **`EloquentBookingRepository`** (Class) — `backend/app/Repositories/EloquentBookingRepository.php:30`
- **`EloquentRoomRepository`** (Class) — `backend/app/Repositories/EloquentRoomRepository.php:22`
- **`EloquentContactMessageRepository`** (Class) — `backend/app/Repositories/EloquentContactMessageRepository.php:13`
- **`findById`** (Method) — `backend/app/Repositories/EloquentBookingRepository.php:37`
- **`findByIdOrFail`** (Method) — `backend/app/Repositories/EloquentBookingRepository.php:45`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `EloquentBookingRepository` | Class | `backend/app/Repositories/EloquentBookingRepository.php` | 30 |
| `EloquentRoomRepository` | Class | `backend/app/Repositories/EloquentRoomRepository.php` | 22 |
| `EloquentContactMessageRepository` | Class | `backend/app/Repositories/EloquentContactMessageRepository.php` | 13 |
| `BookingRepositoryInterface` | Interface | `backend/app/Repositories/Contracts/BookingRepositoryInterface.php` | 31 |
| `RoomRepositoryInterface` | Interface | `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | 19 |
| `ContactMessageRepositoryInterface` | Interface | `backend/app/Repositories/Contracts/ContactMessageRepositoryInterface.php` | 15 |
| `findById` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 37 |
| `findByIdOrFail` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 45 |
| `findByIdWithRelations` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 53 |
| `create` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 61 |
| `delete` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 77 |
| `getByUserId` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 87 |
| `getByUserIdOrderedByCheckIn` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 108 |
| `findOverlappingBookings` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 131 |
| `getTrashed` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 187 |
| `findTrashedById` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 201 |
| `restore` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 215 |
| `forceDelete` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 223 |
| `getTrashedOlderThan` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 231 |
| `getAllWithTrashed` | Method | `backend/app/Repositories/EloquentBookingRepository.php` | 243 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Models | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "EloquentBookingRepository"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "repositories"})` — find related execution flows
3. Read key files listed above for implementation details
