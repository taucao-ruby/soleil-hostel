# Features Layer (`src/features/`)

> Business features - auth, booking, bookings (guest dashboard), admin (admin dashboard), rooms, locations, home

## Overview

```text
src/features/
├── auth/
│   ├── AuthContext.tsx         # Auth provider + useAuth hook (+test)
│   ├── LoginPage.tsx           # Login form (+test)
│   ├── RegisterPage.tsx        # Registration form (+test)
│   └── ProtectedRoute.tsx      # Auth guard component
├── booking/
│   ├── BookingForm.tsx         # Booking creation form (+test) — URL params pre-fill, Vietnamese UI
│   ├── booking.api.ts          # createBooking, fetchMyBookings, cancelBooking
│   ├── booking.types.ts        # BookingFormData, Booking, BookingApiRaw, BookingsListResponse, CancelBookingResponse
│   └── booking.validation.ts   # Client-side validation (+test)
├── bookings/
│   ├── GuestDashboard.tsx      # Guest "My Bookings" with filter tabs & cancel (+test)
│   ├── useMyBookings.ts        # useMyBookingsQuery + useCancelBookingMutation hooks
│   ├── bookingViewModel.ts     # toBookingViewModel, isUpcoming, isPast (+test)
│   └── booking.constants.ts    # getStatusConfig, formatDateVN, formatDateRangeVN (+test)
├── admin/
│   ├── AdminDashboard.tsx      # Admin 3-tab view: Bookings / Trashed / Contacts (+test)
│   ├── admin.api.ts            # fetchAdminBookings, fetchTrashedBookings, fetchContactMessages
│   └── admin.types.ts          # AdminBookingRaw, ContactMessageRaw, response wrappers
├── locations/
│   ├── LocationList.tsx        # Locations grid page with city filter
│   ├── LocationDetail.tsx      # Single location + rooms page with availability search
│   ├── LocationCard.tsx        # Location card component
│   ├── location.api.ts         # getLocations, getLocationBySlug
│   ├── location.types.ts       # Location, LocationWithRooms, LocationRoom, response wrappers
│   ├── constants.ts            # Amenity icon mapping
│   └── index.ts                # Barrel exports
├── rooms/
│   ├── RoomList.tsx            # Rooms grid page
│   ├── room.api.ts             # getRooms
│   └── room.types.ts           # Room, RoomsResponse
└── home/
    └── components/
        ├── SearchCard.tsx      # Location search form — fetches locations, navigates to /locations/:slug (+test)
        ├── Hero.tsx            # Homepage hero section
        ├── FilterChips.tsx     # Filter chip row (+test)
        ├── RoomCard.tsx        # Room preview card
        ├── BottomNav.tsx       # Mobile bottom nav tabs
        ├── HeaderMobile.tsx    # Mobile sticky header
        ├── PromoBanner.tsx     # Promotional banner
        └── ReviewsCarousel.tsx # Reviews display
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

// POST /v1/bookings — creates a new booking; returns the created Booking
export async function createBooking(data: BookingFormData): Promise<Booking>

// GET /v1/bookings — returns the authenticated user's bookings (BookingApiRaw[])
export async function fetchMyBookings(signal?: AbortSignal): Promise<BookingApiRaw[]>

// POST /v1/bookings/:id/cancel — cancels a booking; CSRF auto-attached by interceptor
export async function cancelBooking(id: number): Promise<CancelBookingResponse>
```

### booking.types.ts

```typescript
// Form submission payload
export interface BookingFormData {
  room_id: number
  guest_name: string
  guest_email: string
  check_in: string   // ISO date string
  check_out: string  // ISO date string
  number_of_guests: number
  special_requests?: string
}

// Created booking (POST /v1/bookings response)
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

// Raw shape from GET /v1/bookings (BookingResource)
export interface BookingApiRaw {
  id: number
  room_id: number
  user_id: number
  check_in: string
  check_out: string
  guest_name: string
  guest_email: string
  status: string
  status_label: string | null
  nights: number
  amount?: number
  amount_formatted?: string
  created_at: string
  updated_at: string
}

export interface BookingsListResponse {
  success: boolean
  data: BookingApiRaw[]
}

export interface CancelBookingResponse {
  success: boolean
  message: string
  data: BookingApiRaw
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
  const response = await api.get<RoomsResponse>('/v1/rooms')
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

---

## 5. Bookings Feature (`features/bookings/`)

Guest "My Bookings" dashboard — list, filter, cancel.

### GuestDashboard.tsx

Full booking management UI for authenticated guests.

**Features:**

- Filter tabs: Tất cả / Sắp tới / Đã qua (backed by `isUpcoming`/`isPast` predicates)
- `BookingCard` renders date range, night count, amount, status badge (Vietnamese labels)
- Cancel button gated by `booking.canCancel` (only `pending`/`confirmed` statuses)
- `ConfirmDialog` for cancel confirmation with pending state
- Loading: 3× `Skeleton` cards; Error: inline retry; Empty: "Đặt phòng ngay" CTA link

**Imports:** `useMyBookings`, `bookingViewModel`, `booking.constants`, `shared/ui`, `utils/toast`

### useMyBookings.ts

Two custom hooks following the `useState+useEffect+AbortController` pattern (no React Query):

```typescript
// Fetches user's bookings on mount; supports refetch()
export function useMyBookingsQuery(): {
  bookings: BookingViewModel[]
  isLoading: boolean
  isError: boolean
  refetch: () => void
}

// Returns cancel function + pending/error state
export function useCancelBookingMutation(): {
  cancel: (id: number) => Promise<boolean>
  isPending: boolean
  error: string | null
  clearError: () => void
}
```

### bookingViewModel.ts

Pure transformation layer — keeps components dumb:

```typescript
export interface BookingViewModel {
  id: number
  status: string
  statusLabel: string
  checkIn: Date
  checkOut: Date
  guestName: string
  nights: number
  amountFormatted: string | undefined
  canCancel: boolean   // true only for 'pending' | 'confirmed'
  createdAt: Date
}

export function toBookingViewModel(raw: BookingApiRaw): BookingViewModel
export function isUpcoming(booking: BookingViewModel): boolean  // checkIn >= today
export function isPast(booking: BookingViewModel): boolean      // checkOut < today
```

### booking.constants.ts

Single source of truth for status badge rendering and date formatting:

```typescript
// Returns { label: string, colorClass: string } for a booking status string
export function getStatusConfig(status: string): StatusConfig

// Format Date → Vietnamese "dd/MM/yyyy"
export function formatDateVN(date: Date): string

// Format check-in — check-out as Vietnamese range
export function formatDateRangeVN(checkIn: Date, checkOut: Date): string
```

**Status coverage:** `pending`, `confirmed`, `cancelled`, `completed`, `refund_pending`, `refund_failed`

---

## 6. Admin Feature (`features/admin/`)

Admin dashboard for managing bookings and contact messages.

### AdminDashboard.tsx

3-tab view with lazy-per-tab data fetching.

**Tabs:**

| Tab        | Label     | API                               | Data type          |
| ---------- | --------- | --------------------------------- | ------------------ |
| `bookings` | Đặt phòng | `GET /v1/admin/bookings`          | `AdminBookingRaw`  |
| `trashed`  | Đã xóa    | `GET /v1/admin/bookings/trashed`  | `AdminBookingRaw`  |
| `contacts` | Liên hệ   | `GET /v1/admin/contact-messages`  | `ContactMessageRaw`|

**Data fetching:** `useAdminFetch<T>` — generic hook that fetches on first tab activation (`hasFetched` flag prevents re-fetch on tab switch).

**Card components:**

- `AdminBookingCard` — shows guest name, date range, nights, amount, status badge; shows deleted-at + deleted-by metadata for trashed items
- `ContactCard` — shows name, email, subject, message snippet; highlights unread (blue border + "Mới" badge)

### admin.api.ts

```typescript
// GET /v1/admin/bookings (paginated 50/page; V1 returns page 1 only)
export async function fetchAdminBookings(signal?: AbortSignal): Promise<AdminBookingRaw[]>

// GET /v1/admin/bookings/trashed
export async function fetchTrashedBookings(signal?: AbortSignal): Promise<AdminBookingRaw[]>

// GET /v1/admin/contact-messages (paginated 15/page; V1 returns page 1 only)
export async function fetchContactMessages(signal?: AbortSignal): Promise<ContactMessageRaw[]>
```

### admin.types.ts

```typescript
// Extends BookingApiRaw with optional trashed/refund fields
export interface AdminBookingRaw extends BookingApiRaw {
  is_trashed?: boolean
  deleted_at?: string | null
  deleted_by?: { id: number; name: string; email: string } | null
  cancelled_at?: string | null
  cancelled_by?: { id: number; name: string } | null
  refund_amount?: number
  refund_amount_formatted?: string
  refund_status?: string
  refund_percentage?: number
}

// Raw model — no ContactResource in backend; direct paginator response
export interface ContactMessageRaw {
  id: number
  name: string
  email: string
  subject: string | null
  message: string
  read_at: string | null
  created_at: string
  updated_at: string
}
```

---

## 7. Cross-Feature Dependencies (formerly Section 5)

```text
booking/  → rooms/         (imports getRooms, Room type for room selection in BookingForm)
booking/  → shared/utils   (imports isValidEmail for validation)
bookings/ → booking/       (imports fetchMyBookings, cancelBooking, BookingApiRaw)
admin/    → booking/       (imports BookingApiRaw for AdminBookingRaw extension)
admin/    → bookings/      (imports getStatusConfig, formatDateRangeVN, formatDateVN)
auth/     → shared/lib     (imports api client)
auth/     → shared/utils   (imports setCsrfToken, clearCsrfToken)
rooms/    → shared/lib     (imports api client)
locations/ → shared/lib    (imports api client)
```

**Allowed cross-feature import:** `bookings/` and `admin/` may import from `booking/` (same domain, booking data types/API).
**No other feature imports from another feature.**

---

## 8. What Does NOT Exist

Previously documented but not present in the actual codebase:

- **No `RoomCard.tsx`** in `features/rooms/` — Room cards are rendered inline in `RoomList.tsx`
- **No Zod schemas** — All validation uses plain TypeScript functions
- **No `react-hot-toast`** — Auth pages use inline error/success states; dashboard uses `react-toastify`
- **No `react-datepicker`** — Uses native `<input type="date">`
- **No `@/services/api`** import path — All features use `@/shared/lib/api`
- **No `validateApiResponse()`** — No runtime response validation
- **No `bookingApi.getAll/getById/update`** — `createBooking`, `fetchMyBookings`, `cancelBooking` implemented only
- **No `roomsApi.getByStatus/search`** — Only `getRooms` exists
- **No `AvailabilityResponse` type** — Removed; `/v1/locations/:slug/availability` endpoint exists in backend but is not called by the frontend (frontend uses the show endpoint with params instead)
