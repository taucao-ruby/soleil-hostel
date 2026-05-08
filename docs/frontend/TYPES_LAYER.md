# Types Layer (`src/shared/types/`)

> TypeScript interface definitions for API responses and domain entities
>
> **Last Updated:** May 8, 2026

## Overview

Shared types live under `src/shared/types/`. **There is no top-level `src/types/` directory** — earlier docs that placed types at `src/types/api.ts` are stale.

```text
src/shared/types/
├── api.ts                  # Base ApiResponse, ApiError, AuthResponse, User
├── booking.types.ts        # Shared booking DTOs (re-exported by features as needed)
└── location.types.ts       # Shared Location DTOs
```

Plus **feature-local types** that are not shared across features (canonical for that feature):

```text
src/features/booking/booking.types.ts        # BookingFormData, ReviewSubmitData, BookingApiRaw
src/features/rooms/room.types.ts             # Room, RoomsResponse
src/features/locations/location.types.ts     # Location, LocationWithRooms (extends shared)
src/features/admin/admin.types.ts            # AdminBookingRaw, ContactMessageRaw, PaginationMeta
src/features/assistant/assistant.types.ts    # AI proposal-confirmation DTOs
```

Types are defined using **plain TypeScript interfaces** (no runtime validation library). No Zod, no io-ts, no class-transformer.

---

## 1. Shared API Types (`src/shared/types/api.ts`)

### Base API Response

```typescript
export interface ApiResponse {
  message?: string
  success?: boolean
}
```

### User Type

```typescript
export interface User {
  id: number
  name: string
  email: string
  role: 'user' | 'moderator' | 'admin'  // matches backend App\Enums\UserRole
  email_verified_at: string | null
  created_at: string
  updated_at: string
}
```

### Auth Response

```typescript
export interface AuthResponse {
  user: User
  csrf_token: string
}
```

### Room Type (shared)

> The **bookable status** field on rooms was narrowed to two values on 2026-04-29 via the `rooms_status_deprecated` CHECK constraint (`'available' | 'unavailable'`). The historical legacy values (`'booked'`, `'maintenance'`) no longer pass the DB constraint. Physical readiness is a separate field (`readiness_status`).

```typescript
export interface Room {
  id: number
  name: string
  price: number
  max_guests: number
  status: 'available' | 'unavailable'        // DB CHECK rooms_status_deprecated
  readiness_status: 'ready' | 'occupied' | 'dirty' | 'cleaning' | 'inspected' | 'out_of_service'
  description?: string
  image_url?: string
  created_at?: string
  updated_at?: string
}

export interface RoomsResponse extends ApiResponse {
  data: Room[]
}
```

### Booking Type

> Status enum mirrors the backend `App\Enums\BookingStatus` and the DB `chk_bookings_status` CHECK constraint. `'completed'` is **not** a valid backend state — `cancelled` is the terminal state. Fields for cancellation actor snapshot (`cancelled_by_email`, `cancelled_by_role`, `cancelled_by_display`) are populated on cancelled rows since 2026-05-01.

```typescript
export interface Booking {
  id: number
  room_id: number
  user_id?: number
  guest_name: string
  guest_email: string
  check_in: string                                                // YYYY-MM-DD
  check_out: string
  number_of_guests?: number
  status: 'pending' | 'confirmed' | 'refund_pending' | 'cancelled' | 'refund_failed'
  amount?: number                                                 // cents
  payment_intent_id?: string | null                               // Stripe PaymentIntent id (payment-hold, since Apr 22)
  deposit_status?: 'none' | 'collected' | 'applied' | 'refunded' | 'partial_refund' | 'forfeited'
  cancelled_at?: string | null
  cancelled_by_email?: string | null                              // immutable actor snapshot
  cancelled_by_role?: string | null
  cancelled_by_display?: string | null
  cancellation_reason?: string | null
  created_at?: string
  updated_at?: string
}

export interface BookingResponse extends ApiResponse {
  data: Booking
}

export interface BookingsResponse extends ApiResponse {
  data: Booking[]
}
```

### Review Type

```typescript
export interface Review {
  id: number
  user_id: number
  room_id?: number
  rating: number
  content: string
  created_at?: string
  updated_at?: string
}

export interface ReviewsResponse extends ApiResponse {
  data: Review[]
}
```

### API Error

```typescript
export interface ApiError {
  message: string
  errors?: Record<string, string[]>  // Laravel validation errors
  exception?: string
  file?: string
  line?: number
  trace?: unknown[]
}
```

---

## 2. Feature-Specific Types

Each feature defines its own types alongside its components:

| File                                       | Contents                                                              |
| ------------------------------------------ | --------------------------------------------------------------------- |
| `features/booking/booking.types.ts`        | `BookingFormData`, `BookingApiRaw`, `ReviewSubmitData`, response wrappers |
| `features/rooms/room.types.ts`             | Feature-local `Room`, `RoomsResponse` (overlaps with shared — kept for compat) |
| `features/locations/location.types.ts`     | `Location`, `LocationWithRooms`, `LocationRoom`, response wrappers     |
| `features/admin/admin.types.ts`            | `AdminBookingRaw`, `ContactMessageRaw`, `PaginationMeta`               |
| `features/assistant/assistant.types.ts`    | AI proposal/decision DTOs (`Proposal`, `DecideRequest`, `DecideResponse`) |

---

## 3. Type Patterns

### Interface Extension

```typescript
// Extend base ApiResponse for specific responses
export interface BookingResponse extends ApiResponse {
  data: Booking
}
```

### String Literal Unions

```typescript
// Status types use string literal unions — values mirror backend DB CHECK constraints
type RoomBookableStatus = 'available' | 'unavailable'           // chk constraint rooms_status_deprecated
type RoomReadiness = 'ready' | 'occupied' | 'dirty' | 'cleaning' | 'inspected' | 'out_of_service'
type BookingStatus = 'pending' | 'confirmed' | 'refund_pending' | 'cancelled' | 'refund_failed'
```

### Optional Fields

```typescript
// Optional fields for partial responses
description?: string
created_at?: string
```

---

## 4. Key Decisions

- **No Zod or runtime validation**: Types are compile-time only via TypeScript interfaces
- **No barrel exports**: `types/api.ts` is imported directly (`import { User } from '@/types/api'`)
- **Feature-local types**: Feature-specific types live alongside their feature code, not in the shared types directory
- **Laravel-aligned**: Error types match Laravel's validation error format (`Record<string, string[]>`)
