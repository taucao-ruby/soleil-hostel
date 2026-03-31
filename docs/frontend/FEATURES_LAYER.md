# Features Layer (`src/features/`)

> Business features - auth, booking, bookings (guest dashboard), admin (admin dashboard), rooms, locations, home
>
> **UI DESIGN CONTEXT (Google Stitch):**
> This is the screen and component inventory. Use it as your screen list.
> Every component listed is implemented and live. Do NOT design screens not listed here.
> All user-facing copy is **Vietnamese**. Status badges follow the color guide in PERMISSION_MATRIX.md.
> Layout: `BottomNav` + `HeaderMobile` on `/` only. All other routes: `Header` + `Footer`.
> Admin routes (`/admin/*`): `AdminLayout` with `AdminSidebar` (left-rail on desktop, collapsed on mobile).

---

## Screen → Component Map (Quick Reference for Stitch)

| Route | Primary Component | Feature | Persona |
|---|---|---|---|
| `/` | `HomePage` (+ `SearchCard`, `Hero`, `FilterChips`, `BottomNav`, `HeaderMobile`) | `home/` | All |
| `/rooms` | `RoomList` | `rooms/` | All |
| `/locations` | `LocationList` | `locations/` | All |
| `/locations/:slug` | `LocationDetail` | `locations/` | All |a
| `/booking` | `BookingForm` | `booking/` | Auth |
| `/my-bookings` | `BookingList` | `booking/` | Auth |
| `/my-bookings/:id` | `BookingDetailPage` + `BookingDetailPanel` + `ReviewForm` | `bookings/` | Auth |
| `/dashboard` (user/moderator) | `GuestDashboard` | `bookings/` | Guest, Moderator |
| `/dashboard` (admin) | `AdminDashboard` | `admin/` | Admin |
| `/admin` | `AdminDashboard` in `AdminLayout` | `admin/` | Moderator, Admin |
| `/admin/bookings` | `AdminBookingDashboard` in `AdminLayout` | `admin/bookings/` | Moderator, Admin |
| `/admin/bookings/calendar` | `BookingCalendar` in `AdminLayout` | `admin/bookings/` | Moderator, Admin |
| `/admin/bookings/today` | `TodayOperations` in `AdminLayout` | `admin/bookings/` | Moderator, Admin |
| `/admin/bookings/:id` | `BookingDetailPage` in `AdminLayout` | `bookings/` | Moderator, Admin |
| `/admin/customers` | `CustomerList` in `AdminLayout` | `admin/customers/` | Moderator, Admin |
| `/admin/customers/:email` | `CustomerProfile` + `StayJournal` in `AdminLayout` | `admin/customers/` | Moderator, Admin |
| `/admin/rooms` | `AdminRoomDashboard` in `AdminLayout` | `admin/rooms/` | Moderator (view-only), Admin |
| `/admin/rooms/new` | `RoomForm` in `AdminLayout` | `admin/rooms/` | **Admin only** |
| `/admin/rooms/:id/edit` | `RoomForm` in `AdminLayout` | `admin/rooms/` | **Admin only** |

> **NOT implemented — do not design**: `/admin/reviews`, `/admin/messages`

---

## Overview

```text
src/features/
├── auth/
│   ├── AuthContext.tsx         # Auth provider + useAuth hook (+test)
│   ├── LoginPage.tsx           # Login form (+test)
│   ├── RegisterPage.tsx        # Registration form (+test)
│   ├── ProtectedRoute.tsx      # Auth guard component
│   └── AdminRoute.tsx          # Admin-only auth guard (+test)
├── booking/
│   ├── BookingForm.tsx         # Booking creation form (+test) — URL params pre-fill, Vietnamese UI
│   ├── BookingList.tsx         # Paginated booking list page (at /my-bookings)
│   ├── booking.api.ts          # createBooking, fetchMyBookings, cancelBooking, submitReview
│   ├── booking.types.ts        # BookingFormData, Booking, ..., ReviewSubmitData, ReviewSubmitResponse
│   └── booking.validation.ts   # Client-side validation (+test)
├── bookings/
│   ├── GuestDashboard.tsx
│   ├── useMyBookings.ts
│   ├── bookingViewModel.ts
│   ├── BookingDetailPanel.tsx  # Detail panel; shows ReviewForm for confirmed past bookings (+test)
│   ├── BookingDetailPage.tsx   # Booking detail page (also used at /admin/bookings/:id)
│   └── ReviewForm.tsx          # Star-rating review submission form (+test, 10 tests)
├── admin/
│   ├── AdminDashboard.tsx   # Legacy 3-tab view: Bookings / Trashed / Contacts (+test)
│   ├── AdminLayout.tsx      # Admin shell: AdminSidebar + content <Outlet />
│   ├── AdminSidebar.tsx     # Admin nav sidebar (+test)
│   ├── admin.api.ts         # fetchAdminBookings, fetchTrashedBookings, fetchContactMessages
│   ├── admin.types.ts       # AdminBookingRaw, ContactMessageRaw
│   ├── bookings/
│   │   ├── AdminBookingDashboard.tsx  # Booking list + 7 server-side filters (+test)
│   │   ├── AdminBookingTable.tsx      # Booking data table component
│   │   ├── BookingCalendar.tsx        # Calendar view of bookings
│   │   └── TodayOperations.tsx        # Today's arrivals/departures
│   ├── customers/
│   │   ├── CustomerList.tsx           # Guest list with search
│   │   ├── CustomerProfile.tsx        # Guest profile + booking history
│   │   └── StayJournal.tsx            # Stay history log per guest
│   └── rooms/
│       ├── AdminRoomDashboard.tsx     # Room list + location filter (+test)
│       ├── RoomForm.tsx               # Create/edit room form (admin-only write)
│       ├── RoomStatusBadge.tsx        # Room status indicator
│       ├── RoomStatusBoard.tsx        # Board view of all room statuses
│       └── RoomTable.tsx             # Room data table
├── locations/
│   ├── LocationList.tsx        # Locations grid page with city filter
│   ├── LocationDetail.tsx      # Single location + rooms page with availability search
│   ├── LocationCard.tsx        # Location card component
│   ├── location.api.ts         # getLocations, getLocationBySlug
│   ├── location.types.ts       # Location, LocationWithRooms, LocationRoom, response wrappers
│   ├── constants.ts            # Amenity icon mapping
│   └── index.ts                # Barrel exports
├── rooms/
│   ├── RoomList.tsx            # Rooms grid page (+test)
│   ├── room.api.ts             # getRooms
│   └── room.types.ts           # Room, RoomsResponse
└── home/
    └── components/
        ├── SearchCard.tsx      # Location search form — fetches locations, navigates to /locations/:slug (+test)
        ├── Hero.tsx            # Homepage hero section
        ├── FilterChips.tsx     # Filter chip row (+test)
        ├── RoomCard.tsx        # Room preview card
        ├── BottomNav.tsx       # Mobile bottom nav tabs (homepage only)
        ├── HeaderMobile.tsx    # Mobile sticky header (homepage only) (+test)
        ├── PromoBanner.tsx     # Promotional banner
        └── ReviewsCarousel.tsx # Reviews display
```

### Key Patterns

- **No Zod or runtime validation** - All validation is plain TypeScript functions
- **No react-hot-toast** - Auth pages use inline error/success states from `AuthContext`. Non-auth pages use `react-toastify` via `utils/toast.ts`
- **No DatePicker library** - Uses native `<input type="date">`
- **No shared UI components for forms** - Auth pages use native `<input>` with custom styling (not the shared `Input` component)
- **API imports** from `@/shared/lib/api` (not `@/services/api`)
- Each feature has its own types file (not importing from `@/types/api` for feature-specific types)

---

## Core Data Shapes (Stitch: use for realistic field labels and form layouts)

### User
```typescript
{ id, name, email, role: 'user'|'moderator'|'admin', email_verified_at: string|null }
```

### Booking
```typescript
{
  id, reference, status: 'pending'|'confirmed'|'cancelled'|'refund_pending'|'refund_failed',
  room_id, room: Room,
  guest_name, guest_email,
  check_in: 'YYYY-MM-DD', check_out: 'YYYY-MM-DD',
  amount: number,        // in VND cents
  location_id,
  created_at, updated_at, deleted_at: string|null,
  cancelled_at: string|null, cancellation_reason: string|null
}
```

### Room
```typescript
{
  id, name, slug, status: 'available'|'occupied'|'maintenance',
  price: number,          // per night, VND
  capacity: number,
  location_id,
  readiness_status: 'ready'|'occupied'|'dirty'|'cleaning'|'inspected'|'out_of_service'
}
```

### Location
```typescript
{ id, name, slug, city, address, amenities: string[], images: string[] }
```

### Review
```typescript
{ id, booking_id, rating: 1|2|3|4|5, comment: string, created_at }
```

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

// POST /v1/reviews — submits a star-rating review for a booking
export async function submitReview(data: ReviewSubmitData): Promise<ReviewSubmitResponse>
```

### BookingList.tsx

Standalone paginated booking list page rendered at `/my-bookings`. Displays the authenticated user's bookings with pagination. Separate from `GuestDashboard` (which is rendered inside `/dashboard`).

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

export interface ReviewSubmitData {
  // review submission payload
}

export interface ReviewSubmitResponse {
  // review submission response
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

**Imports:** `useMyBookings`, `bookingViewModel`, `shared/ui`, `utils/toast`

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

### BookingDetailPanel.tsx

Guest-facing booking detail panel. For confirmed bookings past checkout, renders `ReviewForm` inline.

### ReviewForm.tsx

Star-rating review submission form shown inside `BookingDetailPanel` for eligible bookings (confirmed + past checkout). Features:
- 1–5 star rating with Vietnamese labels
- Comment textarea
- 403 handling (already reviewed)
- 422 handling (validation errors)
- Submits to `POST /v1/reviews` via `booking.api.ts::submitReview()`
- 10 tests (`ReviewForm.test.tsx`)

---

## 6. Admin Feature (`features/admin/`)

Admin panel for managing bookings, rooms, contacts, and customers.
Accessible to both `admin` and `moderator` roles (via `AdminRoute` default `minRole='moderator'`).
Room CUD routes require `minRole="admin"`.

### AdminLayout.tsx

Shell component wrapping all `/admin/*` routes. Renders `AdminSidebar` on the left and a `<Outlet />` for page content on the right. Lazy-loaded.

### AdminSidebar.tsx

Admin navigation sidebar. Links to:
- `/admin` (dashboard overview)
- `/admin/bookings` (booking management)
- `/admin/rooms` (room management)
- `/admin/customers` (guest management)

### AdminDashboard.tsx (legacy 3-tab view)

Original 3-tab view. Still accessible at `/admin` index. Tabs: Đặt phòng / Đã xóa / Liên hệ.
See `bookings/AdminBookingDashboard.tsx` for the full-featured booking management screen.

### bookings/AdminBookingDashboard.tsx

Full booking management screen at `/admin/bookings`. Features:
- 7 server-side filter params: `check_in_start`, `check_in_end`, `check_out_start`, `check_out_end`, `status`, `location_id`, `search` (ILIKE)
- `AdminBookingTable` renders the filtered result
- Accessible to moderator and admin

### bookings/BookingCalendar.tsx

Calendar view of bookings at `/admin/bookings/calendar`. Accessible to moderator and admin.

### bookings/TodayOperations.tsx

Today's arrivals and departures operational view at `/admin/bookings/today`. Accessible to moderator and admin.

### customers/CustomerList.tsx

Guest list with search at `/admin/customers`. Accessible to moderator and admin.

### customers/CustomerProfile.tsx

Individual guest profile with full booking history at `/admin/customers/:email`. Accessible to moderator and admin.

### customers/StayJournal.tsx

Stay history log per guest. Rendered inside `CustomerProfile`.

### rooms/AdminRoomDashboard.tsx

Room list with location filter at `/admin/rooms`. Accessible to moderator and admin (read-only for moderator).

### rooms/RoomForm.tsx

Create/edit room form. Used at both `/admin/rooms/new` and `/admin/rooms/:id/edit`. **Admin-only** (`minRole="admin"`).

### rooms/RoomStatusBadge.tsx, RoomStatusBoard.tsx, RoomTable.tsx

Room status display components used by `AdminRoomDashboard`.

### admin.api.ts

```typescript
// GET /v1/admin/bookings?page=N — paginated; includes 7 filter params in full feature
export async function fetchAdminBookings(signal?: AbortSignal): Promise<AdminBookingRaw[]>

// GET /v1/admin/bookings/trashed
export async function fetchTrashedBookings(signal?: AbortSignal): Promise<AdminBookingRaw[]>

// GET /v1/admin/contact-messages
export async function fetchContactMessages(signal?: AbortSignal): Promise<ContactMessageRaw[]>
```

---

## 7. Cross-Feature Dependencies (formerly Section 5)

```text
booking/  → rooms/         (imports getRooms, Room type for room selection in BookingForm)
booking/  → shared/utils   (imports isValidEmail for validation)
bookings/ → booking/       (imports fetchMyBookings, cancelBooking, BookingApiRaw)
admin/    → booking/       (imports BookingApiRaw for AdminBookingRaw extension)
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

- **No `booking.constants.ts`** in `features/bookings/` — File is absent; status/date formatting logic (`getStatusConfig`, `formatDateVN`, `formatDateRangeVN`) lives in `shared/lib/booking.utils.ts`
- **No `RoomCard.tsx`** in `features/rooms/` — Room cards are rendered inline in `RoomList.tsx`
- **No Zod schemas** — All validation uses plain TypeScript functions
- **No `react-hot-toast`** — Auth pages use inline error/success states; dashboard uses `react-toastify`
- **No `react-datepicker`** — Uses native `<input type="date">`
- **No `@/services/api`** import path — All features use `@/shared/lib/api`
- **No `validateApiResponse()`** — No runtime response validation
- **No `bookingApi.getAll/getById/update`** — `createBooking`, `fetchMyBookings`, `cancelBooking` implemented only
- **No `roomsApi.getByStatus/search`** — Only `getRooms` exists
- **No `AvailabilityResponse` type** — Removed; `/v1/locations/:slug/availability` endpoint exists in backend but is not called by the frontend (frontend uses the show endpoint with params instead)
- **No `/admin/reviews` route** — AdminSidebar may link to it but no route or component is implemented
- **No `/admin/messages` route** — same as above; ContactMessages accessible only via legacy AdminDashboard tab
