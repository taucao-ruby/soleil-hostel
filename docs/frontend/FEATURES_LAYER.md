# Features Layer (`src/features/`)

> Business features - auth, booking, rooms, locations

## Overview

```text
src/features/
├── auth/
│   ├── AuthContext.tsx       # Auth provider + useAuth hook (+test)
│   ├── LoginPage.tsx         # Login form (+test)
│   ├── RegisterPage.tsx      # Registration form (+test)
│   └── ProtectedRoute.tsx    # Auth guard component
├── booking/
│   ├── BookingForm.tsx       # Booking creation form (+test)
│   ├── booking.api.ts        # Booking API calls
│   ├── booking.types.ts      # Booking TypeScript interfaces
│   └── booking.validation.ts # Client-side validation (+test)
├── locations/
│   ├── LocationList.tsx      # Locations grid page
│   ├── LocationDetail.tsx    # Single location + rooms page
│   ├── LocationCard.tsx      # Location card component
│   ├── location.api.ts       # Location API calls
│   ├── location.types.ts     # Location TypeScript interfaces
│   ├── constants.ts          # Amenity icon mapping
│   └── index.ts              # Barrel exports
└── rooms/
    ├── RoomList.tsx           # Rooms grid page
    ├── room.api.ts            # Room API calls
    └── room.types.ts          # Room TypeScript interfaces
```

### Key Patterns

- **No Zod or runtime validation** - All validation is plain TypeScript functions
- **No react-hot-toast** - Auth pages use inline error/success states from AuthContext
- **No DatePicker library** - Uses native `<input type="date">`
- **No shared UI components for forms** - Auth pages use native `<input>` with custom styling (not the shared `Input` component)
- **API imports** from `@/shared/lib/api` (not `@/services/api`)
- Each feature has its own types file (not importing from `@/types/api` for feature-specific types)

---

## 1. Authentication Feature (`features/auth/`)

### AuthContext.tsx

Manages authentication state via React Context with httpOnly cookie authentication.

```typescript
interface AuthContextType {
  user: User | null
  isAuthenticated: boolean
  loading: boolean
  error: string | null
  loginHttpOnly: (email: string, password: string, rememberMe?: boolean) => Promise<void>
  registerHttpOnly: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>
  logoutHttpOnly: () => Promise<void>
  me: () => Promise<User | null>
  clearError: () => void
}
```

**On mount:** Checks `sessionStorage` for `csrf_token`. If present, calls `GET /auth/me-httponly` to validate. If absent or 401, user stays `null`.

**Login flow:**

1. `POST /auth/login-httponly` with `{ email, password, remember_me }`
2. Backend sets httpOnly cookie + returns `{ user, csrf_token }`
3. `setCsrfToken()` saves CSRF token to sessionStorage
4. `setUser()` updates React state

**Logout flow:**

1. `POST /auth/logout-httponly` (cookie sent automatically)
2. `clearCsrfToken()` + `setUser(null)`
3. Even on API error, local state is cleared

**Exports:** `AuthProvider` (component), `useAuth` (hook)

### LoginPage.tsx

Self-contained login form with:

- Email + password fields with inline validation (regex for email, min 8 chars for password)
- "Remember me" checkbox (sends `remember_me` to backend)
- Loading/success/error states managed locally + `authError` from `useAuth()`
- Redirects to `/dashboard` after 500ms delay on success
- Link to register page, back to home
- Uses native `<input>` elements with custom Tailwind styling (not shared `Input` component)
- Uses `loginHttpOnly()` from `useAuth()` (no toast notifications)

### RegisterPage.tsx

Self-contained registration form with:

- Name, email, password, password confirmation fields
- Client-side validation: name min 2 chars, email regex, password min 8 chars + uppercase/lowercase/number, password match
- Uses `registerHttpOnly()` from `useAuth()` (no toast, no Zod)
- Redirects to `/dashboard` after 1000ms delay on success
- Error display via `authError` from `useAuth()`

### ProtectedRoute.tsx

```typescript
interface ProtectedRouteProps {
  children: React.ReactNode
}
```

Simple auth guard:

- `loading` → Shows inline spinner (not `LoadingSpinner` component)
- `!isAuthenticated` → `<Navigate to="/login" state={{ from: location.pathname }} />`
- Authenticated → Renders children

**Note:** No `redirectTo` or `requireAuth` props. Simpler than documented in earlier versions.

---

## 2. Booking Feature (`features/booking/`)

### BookingForm.tsx

Full booking form with room selection, guest info, dates, and price calculation.

**Imports:**

```typescript
import { createBooking } from './booking.api'
import { getRooms } from '../rooms/room.api'
import { Room } from '../rooms/room.types'
import { BookingFormData } from './booking.types'
import { validateBookingForm, getMinCheckInDate, getMinCheckOutDate, calculateNights } from './booking.validation'
```

**Features:**

- Fetches available rooms on mount via `getRooms()`, filters by `status === 'available'`
- Pre-selects room from URL param `?room_id=`
- Native `<input type="date">` for check-in/check-out (not DatePicker)
- Live price calculation: `selectedRoom.price * nights`
- Validation via `validateBookingForm()` (plain function, not Zod)
- On success: shows booking reference, redirects to `/dashboard` after 2s
- Error handling from API response `message` field

**Form fields:** room_id (select), guest_name, guest_email, check_in (date), check_out (date), number_of_guests (number), special_requests (textarea, optional)

### booking.api.ts

```typescript
import api from '@/shared/lib/api'
import { Booking, BookingFormData, BookingResponse } from './booking.types'

export async function createBooking(data: BookingFormData): Promise<Booking> {
  const response = await api.post<BookingResponse>('/bookings', data)
  return response.data.data
}
```

**Note:** Only `createBooking` is implemented. No `getAll`, `getById`, `update`, or `cancel` yet.

### booking.types.ts

```typescript
export interface BookingFormData {
  room_id: number
  guest_name: string
  guest_email: string
  check_in: string   // ISO date string
  check_out: string  // ISO date string
  number_of_guests: number
  special_requests?: string
}

export interface Booking {
  id: number
  room_id: number
  guest_name: string
  guest_email: string
  check_in: string
  check_out: string
  number_of_guests: number
  special_requests: string | null
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed'
  total_price: number
  created_at: string
  updated_at: string
}

export interface BookingResponse {
  data: Booking
  message?: string
}
```

### booking.validation.ts

Plain TypeScript validation functions (no Zod):

```typescript
export function validateBookingForm(data: {...}): ValidationErrors
export function calculateNights(checkIn: string, checkOut: string): number
export function formatDateForInput(date: Date): string
export function getMinCheckInDate(): string
export function getMinCheckOutDate(checkInDate?: string): string
```

**Validation rules:**

- `room_id`: Required, must be > 0
- `guest_name`: Required, min 2 characters
- `guest_email`: Required, validated via `isValidEmail()` from `@/shared/utils/security`
- `check_in`: Required, cannot be in the past
- `check_out`: Required, must be after check-in, max 30 days stay
- `number_of_guests`: Required, 1-10

---

## 3. Rooms Feature (`features/rooms/`)

### RoomList.tsx

Displays all rooms in a responsive grid (1/2/3 columns).

**Features:**

- Fetches rooms via `getRooms()` on mount
- Skeleton loading state using `SkeletonCard` component
- Room cards rendered inline (no separate `RoomCard` component)
- Each card shows: image (or gradient placeholder), name, description, price, status badge
- Status badges: green (available), red (booked), yellow (maintenance)
- "Book Now" button navigates to `/booking?room_id={id}` (only for available rooms)
- Empty state with icon and message

### room.api.ts

```typescript
import api from '@/shared/lib/api'
import { Room, RoomsResponse } from './room.types'

export async function getRooms(): Promise<Room[]> {
  const response = await api.get<RoomsResponse>('/rooms')
  return response.data.data
}
```

**Note:** Only `getRooms` is implemented. No `getById`, `getByStatus`, or `search`.

### room.types.ts

```typescript
export interface Room {
  id: number
  name: string
  description: string
  price: number
  status: 'available' | 'booked' | 'maintenance'
  image_url: string | null
  created_at: string
  updated_at: string
}

export interface RoomsResponse {
  data: Room[]
}
```

---

## 4. Locations Feature (`features/locations/`)

### LocationList.tsx

Displays all active locations in a responsive grid with city filtering.

**Features:**

- Fetches locations via `getLocations()` (endpoint: `GET /v1/locations`)
- City filter dropdown (derived from `location.address.city` with `useMemo`)
- Skeleton loading state using `SkeletonCard`
- Renders `LocationCard` for each location
- Click navigates to `/locations/{slug}`
- Empty state with conditional message based on filter

### LocationCard.tsx

```typescript
interface LocationCardProps {
  location: Location
  onClick: () => void
}
```

Card displays:

- Cover image (first from `location.images[]`) or amber gradient placeholder
- Location name, full address
- Amenity emoji icons (first 6, with "+N more" overflow)
- Room stats: total rooms, available rooms count
- Accessible: keyboard navigation with `role="button"`, `tabIndex={0}`, Enter/Space handler

### LocationDetail.tsx

Single location page with availability search.

**Features:**

- Fetches location by slug via `getLocationBySlug(slug, params)`
- URL search params for `check_in`, `check_out`, `guests`
- Hero section: name, address, phone/email contact links, description
- Amenities grid with emoji icons from `constants.ts`
- Availability search form: check-in, check-out, guests → updates URL params
- Available rooms grid (2 columns): each room shows display_name, description, price, max guests, "Book Now" button
- "Book Now" navigates to `/booking?room_id=&check_in=&check_out=&guests=`

### location.api.ts

```typescript
import api from '@/shared/lib/api'

export async function getLocations(): Promise<Location[]> {
  const response = await api.get<LocationsResponse>('/v1/locations')
  return response.data.data
}

export async function getLocationBySlug(
  slug: string,
  params?: { check_in?: string; check_out?: string; guests?: number }
): Promise<LocationWithRooms> {
  const response = await api.get<LocationResponse>(`/v1/locations/${slug}`, { params })
  return response.data.data
}
```

**Note:** Location endpoints use `/v1/` prefix (versioned API), unlike other features which use unversioned paths.

### location.types.ts

```typescript
export interface Location {
  id: number
  name: string
  slug: string
  address: LocationAddress        // { full, street, ward, district, city, postal_code }
  coordinates: LocationCoordinates | null  // { lat, lng }
  contact: LocationContact        // { phone, email }
  description: string | null
  amenities: string[]
  images: LocationImage[]         // { url, alt, order }
  stats: LocationStats            // { total_rooms, available_rooms?, rooms_count? }
  is_active: boolean
  created_at: string
}

export interface LocationWithRooms extends Location {
  rooms: LocationRoom[]           // Extended room type with display_name, max_guests, lock_version
}

// API response wrappers
export interface LocationsResponse { success: boolean; message: string; data: Location[] }
export interface LocationResponse { success: boolean; message: string; data: LocationWithRooms }
export interface AvailabilityResponse {
  success: boolean; message: string
  data: { location: Location; available_rooms: LocationRoom[]; total_available: number }
}
```

### constants.ts

Amenity-to-emoji mapping used by `LocationCard` and `LocationDetail`:

```typescript
export const amenityIcons: Record<string, string> = {
  wifi: '📶', air_conditioning: '❄️', hot_water: '🚿', breakfast: '🍳',
  parking: '🅿️', pool: '🏊', gym: '🏋️', laundry: '🧺',
  garden: '🌿', bbq: '🍖', kayaking: '🛶', fishing: '🎣',
}
```

### index.ts

Barrel exports for the locations feature:

```typescript
export { default as LocationList } from './LocationList'
export { default as LocationDetail } from './LocationDetail'
export { default as LocationCard } from './LocationCard'
export * from './location.api'
export * from './location.types'
```

---

## 5. Cross-Feature Dependencies

```text
booking/ → rooms/       (imports getRooms, Room type for room selection)
booking/ → shared/utils  (imports isValidEmail for validation)
auth/    → shared/lib    (imports api client)
auth/    → shared/utils  (imports setCsrfToken, clearCsrfToken)
rooms/   → shared/lib    (imports api client)
locations/ → shared/lib  (imports api client)
```

**No feature imports from another feature** except `booking/` → `rooms/` (for room data in the booking form).

---

## 6. What Does NOT Exist

Previously documented but not present in the actual codebase:

- **No `RoomCard.tsx`** - Room cards are rendered inline in `RoomList.tsx`
- **No Zod schemas** - All validation uses plain TypeScript functions
- **No `react-hot-toast`** - Auth pages use inline error/success states, no toast notifications
- **No `react-datepicker`** - Uses native `<input type="date">`
- **No `@/services/api`** import path - All features use `@/shared/lib/api`
- **No `validateApiResponse()`** - No runtime response validation
- **No `bookingApi.getAll/getById/update/cancel`** - Only `createBooking` exists
- **No `roomsApi.getByStatus/search`** - Only `getRooms` exists
