# Types Layer (`src/types/`)

> TypeScript interface definitions for API responses and domain entities

## Overview

Types Layer contains all shared TypeScript type definitions:

```text
src/types/
└── api.ts           # API response/request types and domain entities
```

Types are defined using **plain TypeScript interfaces** (no runtime validation library). Each feature may also define feature-specific types in its own directory (e.g., `booking.types.ts`, `room.types.ts`, `location.types.ts`).

---

## 1. API Types (`types/api.ts`)

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
  role: 'guest' | 'staff' | 'admin'
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

### Room Type

```typescript
export interface Room {
  id: number
  name: string
  price: number
  max_guests: number
  status: 'available' | 'booked' | 'maintenance'
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

```typescript
export interface Booking {
  id: number
  room_id: number
  user_id?: number
  guest_name: string
  guest_email: string
  check_in: string
  check_out: string
  number_of_guests?: number
  status?: 'pending' | 'confirmed' | 'cancelled' | 'completed'
  total_price?: number
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

| File                                   | Contents                        |
| -------------------------------------- | ------------------------------- |
| `features/booking/booking.types.ts`    | Booking form data, validation   |
| `features/rooms/room.types.ts`         | Room filters, display types     |
| `features/locations/location.types.ts` | Location entity, response types |

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
// Status types use string literal unions
status: 'available' | 'booked' | 'maintenance'
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
