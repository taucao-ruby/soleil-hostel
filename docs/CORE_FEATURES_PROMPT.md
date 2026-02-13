# Comprehensive Prompt: Soleil Hostel Core Features Implementation

> Use this prompt to instruct an AI coding agent to build the complete Core Features for Soleil Hostel.

---

## System Context

You are a senior full-stack engineer specializing in **Laravel 12 + React 19 TypeScript** applications. You are tasked with building the complete Core Features for **Soleil Hostel** — a multi-location hostel management system operating across 5 properties in Hue City, Vietnam.

---

## Project Overview

**Soleil Hostel** is a monorepo with:
- **Backend**: Laravel 12 (PHP 8.2+), PostgreSQL 16, Redis 7, Laravel Sanctum (custom token model)
- **Frontend**: React 19, TypeScript 5.7, Vite 6, Tailwind CSS 3.4, React Router v7, Axios, react-toastify
- **Architecture**: Feature-Sliced Design (frontend), Service-Repository pattern (backend)
- **Auth**: Dual-mode — HttpOnly Cookie (web) + Bearer Token (API/mobile), CSRF double-submit, RBAC with 3 roles (user/moderator/admin)

### The 5 Locations

| # | Name | Slug | Address | City | Rooms | Amenities |
|---|------|------|---------|------|-------|-----------|
| 1 | Soleil Hostel | `soleil-hostel` | Tháp B, 62 Tố Hữu | Huế | 9 | wifi, AC, hot water, breakfast, parking, laundry |
| 2 | Soleil House | `soleil-house` | 33 Lý Thường Kiệt | Huế (Phú Nhuận) | 10 | wifi, AC, hot water, breakfast, parking, garden |
| 3 | Soleil Urban Villa | `soleil-urban-villa` | KDT BGI Topaz Downtown | Quảng Điền | 7 | wifi, AC, hot water, pool, parking, gym, breakfast |
| 4 | Soleil Boutique Homestay | `soleil-boutique-homestay` | 46 Lê Duẩn | Huế (Phú Hoà) | 11 | wifi, AC, hot water, breakfast, parking, garden, bbq |
| 5 | Soleil Riverside Villa | `soleil-riverside-villa` | Quảng Phú | Quảng Điền | 8 | wifi, AC, hot water, pool, parking, garden, kayaking, fishing, breakfast |

**Total: 45 rooms across 5 properties.**

---

## Existing Backend Infrastructure (Already Built — DO NOT recreate)

### Database Schema (PostgreSQL)
- **locations**: id, name, slug, address, city, district, ward, postal_code, latitude, longitude, phone, email, description, amenities (JSONB), images (JSONB), is_active, total_rooms, lock_version
- **users**: id, name, email, password, role (ENUM: user/moderator/admin), email_verified_at, remember_token
- **rooms**: id, location_id (FK), name, room_number, description, price (DECIMAL 10,2), max_guests, status (ENUM: available/occupied/maintenance), lock_version
- **bookings**: id, user_id (FK), room_id (FK), location_id (FK, denormalized), guest_name, guest_email, check_in (DATE), check_out (DATE), status (pending/confirmed/refund_pending/cancelled/refund_failed), amount, payment_intent_id, refund_id, refund_status, refund_amount, refund_error, cancelled_at, cancelled_by, cancellation_reason, deleted_at, deleted_by
- **reviews**: id, booking_id (FK UNIQUE), room_id (FK), user_id (FK), title, content, guest_name, guest_email, rating (1-5), approved (BOOLEAN)
- **contact_messages**: id, name, email, subject, message, read_at
- **personal_access_tokens**: Custom Sanctum with token_identifier, token_hash, device_id, device_fingerprint, expires_at, revoked_at, type, refresh_count, last_rotated_at

### Booking Business Rules
- **Half-open intervals**: `[check_in, check_out)` — same-day checkout/checkin is allowed
- **Overlap detection**: `WHERE check_in < :new_check_out AND check_out > :new_check_in AND status IN ('pending', 'confirmed')`
- **PostgreSQL exclusion constraint**: `EXCLUDE USING gist (room_id WITH =, daterange(check_in, check_out, '[)') WITH &&) WHERE (status IN ('pending','confirmed') AND deleted_at IS NULL)`
- **Pessimistic locking**: `SELECT ... FOR UPDATE` inside transactions with deadlock retry (3 attempts, exponential backoff)
- **Optimistic locking**: Rooms use `lock_version` column for concurrent update detection
- **Soft deletes**: Bookings have `deleted_at` + `deleted_by` audit trail
- **Booking status state machine**: pending → confirmed → refund_pending → cancelled; pending → cancelled (fast path); refund_pending → refund_failed → cancelled

### Existing API Endpoints (v1)
```
# Auth (HttpOnly Cookie — used by frontend)
POST   /api/auth/login-httponly        # Login, sets HttpOnly cookie
POST   /api/auth/refresh-httponly      # Rotate cookie
POST   /api/auth/logout-httponly       # Clear cookie
GET    /api/auth/me-httponly           # Get current user
POST   /api/auth/register             # Register new user

# Locations (public)
GET    /api/v1/locations               # List all active locations
GET    /api/v1/locations/{slug}        # Location detail + rooms (supports ?check_in, ?check_out, ?guests)
GET    /api/v1/locations/{slug}/availability  # Availability query

# Rooms
GET    /api/v1/rooms                   # List all rooms
GET    /api/v1/rooms/{id}              # Room detail
POST   /api/v1/rooms                   # Create room (Admin)
PUT    /api/v1/rooms/{id}              # Update room with lock_version (Admin)
DELETE /api/v1/rooms/{id}              # Delete room (Admin)

# Bookings (authenticated)
GET    /api/v1/bookings                # List user's bookings
POST   /api/v1/bookings               # Create booking
GET    /api/v1/bookings/{id}           # View booking
PUT    /api/v1/bookings/{id}           # Update booking
DELETE /api/v1/bookings/{id}           # Cancel booking (soft delete)
POST   /api/v1/bookings/{id}/confirm   # Confirm booking (Admin)
POST   /api/v1/bookings/{id}/cancel    # Cancel with reason

# Admin Bookings
GET    /api/v1/admin/bookings              # All bookings (with trashed)
GET    /api/v1/admin/bookings/trashed      # Only trashed
GET    /api/v1/admin/bookings/trashed/{id} # View trashed
POST   /api/v1/admin/bookings/{id}/restore # Restore
POST   /api/v1/admin/bookings/restore-bulk # Bulk restore
DELETE /api/v1/admin/bookings/{id}/force   # Permanent delete

# Reviews
GET    /api/rooms/{room}/reviews       # List approved reviews (public)
POST   /api/reviews                    # Create review (auth)
GET    /api/reviews/{id}               # View review
PATCH  /api/reviews/{id}               # Update own review
DELETE /api/reviews/{id}               # Delete own review

# Contact
POST   /api/contact                    # Submit contact form (public, 3/min)
GET    /api/v1/admin/contact-messages          # List messages (Admin)
PATCH  /api/v1/admin/contact-messages/{id}/read # Mark read (Admin)

# Health
GET    /api/health                     # Basic health
GET    /api/health/detailed            # Full system health (Admin)
```

### Backend Services (Already Implemented)
- `CreateBookingService` — Pessimistic locking, deadlock retry, overlap detection
- `BookingService` — Confirmation, cancellation, cache, notifications
- `CancellationService` — Two-phase commit, Stripe refund integration
- `RoomService` — CRUD with optimistic locking, cache tags
- `RoomAvailabilityService` — Availability queries with caching
- `RoomAvailabilityCache` — Tag-based cache invalidation
- `RateLimitService` — Sliding window + token bucket, Redis-backed
- `HtmlPurifierService` — XSS sanitization

### Backend Models with Relationships
```
Location  → hasMany(Room), hasMany(Booking)
User      → hasMany(Booking), hasMany(Review)
Room      → belongsTo(Location), hasMany(Booking), hasMany(Review)
Booking   → belongsTo(User), belongsTo(Room), belongsTo(Location), hasOne(Review)
Review    → belongsTo(Room), belongsTo(User), belongsTo(Booking)
```

### RBAC
- **user**: Own bookings only
- **moderator**: + View all bookings, moderate content, approve reviews
- **admin**: + Manage users, rooms, restore bookings, system config

---

## Existing Frontend Code (Partially Built)

### Tech Stack
- React 19 + TypeScript 5.7 + Vite 6
- React Router v7 (`createBrowserRouter`)
- Axios (HTTP client with interceptors for CSRF, 401 auto-refresh)
- Tailwind CSS 3.4 (utility-first)
- react-toastify (toast notifications)
- Vitest + @testing-library/react (142 existing tests)
- **NOT installed**: Zod, React Hook Form, Framer Motion, DatePicker, date-fns, @tanstack/react-query, lucide-react

### Frontend Architecture (Feature-Sliced Design)
```
src/
├── app/              # App shell — App.tsx, router.tsx, Layout.tsx (Header + Outlet + Footer)
├── features/
│   ├── auth/         # AuthContext (HttpOnly cookie), LoginPage, RegisterPage, ProtectedRoute
│   ├── booking/      # BookingForm (create only), booking.api.ts (createBooking only), validation
│   ├── rooms/        # RoomList (grid), room.api.ts (getRooms only), room.types.ts
│   └── locations/    # LocationList, LocationDetail, LocationCard, location.api.ts, constants.ts
├── pages/            # HomePage, NotFoundPage
├── shared/
│   ├── ui/           # Button, Card, Input, Label, Skeleton, SkeletonCard
│   ├── layout/       # Header, Footer
│   ├── feedback/     # LoadingSpinner, ErrorBoundary
│   ├── lib/          # api.ts (Axios instance with CSRF + 401 interceptor), navigation.ts
│   └── utils/        # csrf.ts, security.ts (isValidEmail, sanitizeHtml)
├── types/            # api.ts (User, Room, Booking, Review interfaces)
└── utils/            # toast.ts (showToast wrapper), webVitals.ts
```

### Key Frontend Patterns (MUST follow)
- **No Zod** — All validation is plain TypeScript functions
- **No react-hot-toast** — Use react-toastify via `utils/toast.ts`
- **No DatePicker library** — Use native `<input type="date">`
- **API imports** from `@/shared/lib/api` (Axios instance)
- Each feature has its own `*.api.ts`, `*.types.ts`, components
- Auth uses `useAuth()` hook from `AuthContext`
- Path alias: `@` maps to `./src`
- Location API uses `/v1/` prefix; other features currently use unversioned paths (should migrate to `/v1/`)

### What EXISTS in the Frontend
- Login/Register pages with HttpOnly cookie auth
- Location list with city filter + Location detail with availability search
- Room list (grid display, no admin CRUD)
- Booking form (create only — no list, no edit, no cancel, no history)
- Basic shared UI components (Button, Card, Input, Skeleton)
- ErrorBoundary, LoadingSpinner, ProtectedRoute

### What DOES NOT EXIST in the Frontend (must be built)
- Admin dashboard / admin panel
- Room management CRUD (create/edit/delete rooms)
- Room status management UI (available/occupied/maintenance)
- Real-time room status dashboard per location
- Booking list (user's bookings, all bookings for admin)
- Booking detail view
- Booking edit / cancel UI
- Booking calendar view
- Check-in / check-out workflow
- Customer/guest management
- Stay history / booking history
- Review management (admin approval workflow)
- Contact messages admin view
- User management (admin)
- Any data visualization or analytics dashboard

---

## TASK: Build Complete Core Features

Implement the following 3 Core Feature areas with **frontend as primary focus** (backend APIs already exist). Where backend gaps exist, implement the necessary API endpoints following existing patterns.

---

### Feature 1: Room Management (across 5 locations)

#### 1.1 Admin Room Dashboard
- **Location selector**: Dropdown/tabs to switch between the 5 locations
- **Room grid/table view**: Show all rooms for selected location with status badges
- **Status indicators**: Color-coded — green (available), red (occupied), yellow (maintenance), blue (cleaning)
- **Quick stats per location**: Total rooms, available, occupied, maintenance count
- **Global overview**: Cross-location summary card showing all 45 rooms

#### 1.2 Room CRUD (Admin)
- **Create Room**: Form with fields — name, room_number, description, price, max_guests, status, location_id
- **Edit Room**: Pre-populated form, sends `lock_version` for optimistic locking, handles 409 Conflict gracefully (show "Room modified by another user, please refresh" dialog)
- **Delete Room**: Confirmation dialog, warns if room has active bookings
- **Validation**: Price > 0, max_guests > 0, name required, room_number unique per location

#### 1.3 Room Status Management
- **Status toggle buttons**: Quick-change between available/occupied/maintenance
- **Status change with reason**: Optional note when changing to maintenance
- **Batch status update**: Select multiple rooms → change status (e.g., mark all as "maintenance" during renovation)
- **Status history**: Show when status last changed (use updated_at)

#### 1.4 Room Filtering & Sorting
- **Filter by**: Status, price range, capacity (max_guests), location
- **Sort by**: Name, price (asc/desc), capacity, status
- **Search**: By room name or room number

#### 1.5 Frontend Implementation Details
```
src/features/admin/rooms/
├── AdminRoomDashboard.tsx     # Main dashboard with location selector
├── RoomTable.tsx              # Table view with sortable columns
├── RoomStatusBoard.tsx        # Visual board showing room status grid
├── RoomForm.tsx               # Create/Edit room form (handles lock_version)
├── RoomStatusBadge.tsx        # Color-coded status badge component
├── RoomFilters.tsx            # Filter sidebar/bar
├── adminRoom.api.ts           # API calls (CRUD, status update)
├── adminRoom.types.ts         # Admin-specific room types
└── adminRoom.validation.ts    # Form validation functions
```

#### 1.6 API Calls to Implement (Frontend)
```typescript
// adminRoom.api.ts
getRoomsByLocation(locationId: number): Promise<Room[]>
createRoom(data: CreateRoomData): Promise<Room>
updateRoom(id: number, data: UpdateRoomData & { lock_version: number }): Promise<Room>
deleteRoom(id: number): Promise<void>
updateRoomStatus(id: number, status: RoomStatus, lock_version: number): Promise<Room>
batchUpdateStatus(roomIds: number[], status: RoomStatus): Promise<Room[]>
```

---

### Feature 2: Booking System (multi-location)

#### 2.1 Enhanced Booking Flow
- **Step 1 — Select Location**: Card grid of 5 locations (reuse LocationCard)
- **Step 2 — Select Dates**: Check-in/check-out date pickers with min date = today, max stay = 30 days
- **Step 3 — Select Room**: Show available rooms for chosen location + dates (call availability API)
- **Step 4 — Guest Info**: Name, email, number of guests, special requests
- **Step 5 — Review & Confirm**: Summary with price calculation (room.price × nights), confirm button
- **Success**: Booking reference number, confirmation message, link to booking detail

#### 2.2 Booking Calendar View (Admin)
- **Monthly calendar grid**: Show bookings per room per day
- **Color-coded by status**: pending (yellow), confirmed (green), cancelled (gray)
- **Click day-cell**: Show bookings for that room on that date
- **Location filter**: Switch between locations
- **Date navigation**: Previous/next month, jump to date
- **Implementation**: Build with plain HTML/CSS grid (no external calendar library). Each row = one room, each column = one day of the month.

#### 2.3 Booking List
**For Users (My Bookings)**:
- List of user's bookings with status badges
- Filter: All / Upcoming / Past / Cancelled
- Each booking card shows: room name, location, dates, status, total price
- Actions: View details, Cancel (if pending/confirmed and not started)

**For Admin (All Bookings)**:
- Searchable/filterable table of all bookings across all locations
- Filters: Location, status, date range, guest name/email
- Sort: Check-in date, created date, status
- Actions: View, confirm, cancel, restore (trashed), force delete
- Tabs: Active / Trashed
- Bulk actions: Confirm selected, cancel selected

#### 2.4 Booking Detail View
- Full booking information: guest info, room info, location, dates, status, payment
- Status timeline: Show state transitions (created → confirmed → cancelled)
- Room card with link to room detail
- Location card with link to location
- Cancel button with reason field (if cancellable)
- For admin: Additional actions (confirm, restore, force delete)

#### 2.5 Check-in / Check-out Workflow (Admin)
- **Today's arrivals**: List of bookings with check_in = today
- **Today's departures**: List of bookings with check_out = today
- **Check-in action**: Confirm guest arrival, mark room as occupied
- **Check-out action**: Confirm guest departure, trigger room status change to cleaning/available
- **Quick view**: Compact cards with guest name, room number, location

#### 2.6 Availability Checker
- **Public page**: Select location → select dates → see available rooms with prices
- **Inline in booking flow**: Auto-checks as user selects dates
- **Admin view**: Grid showing room availability for date range

#### 2.7 Frontend Implementation Details
```
src/features/booking/
├── BookingForm.tsx              # EXISTING — enhance with multi-step flow
├── BookingList.tsx              # User's booking list with filters
├── BookingDetail.tsx            # Full booking detail view
├── BookingCalendar.tsx          # Monthly calendar grid (admin)
├── BookingStatusBadge.tsx       # Color-coded status badge
├── BookingCancelDialog.tsx      # Cancel confirmation with reason
├── BookingFilters.tsx           # Filter bar for booking list
├── booking.api.ts               # EXISTING — extend with getAll, getById, cancel, update
├── booking.types.ts             # EXISTING — extend with full types
└── booking.validation.ts        # EXISTING — extend

src/features/admin/bookings/
├── AdminBookingDashboard.tsx    # Admin booking overview
├── AdminBookingTable.tsx        # Searchable/filterable table
├── TodayOperations.tsx          # Check-in/check-out workflow
├── TrashedBookings.tsx          # Trashed bookings management
├── adminBooking.api.ts          # Admin-specific API calls
└── adminBooking.types.ts        # Admin-specific types
```

#### 2.8 API Calls to Implement (Frontend)
```typescript
// booking.api.ts (extend existing)
getMyBookings(filters?: BookingFilters): Promise<PaginatedResponse<Booking>>
getBookingById(id: number): Promise<BookingDetail>
cancelBooking(id: number, reason?: string): Promise<Booking>
updateBooking(id: number, data: UpdateBookingData): Promise<Booking>

// adminBooking.api.ts
getAllBookings(filters?: AdminBookingFilters): Promise<PaginatedResponse<Booking>>
confirmBooking(id: number): Promise<Booking>
adminCancelBooking(id: number, reason: string): Promise<Booking>
getTrashedBookings(): Promise<Booking[]>
restoreBooking(id: number): Promise<Booking>
bulkRestoreBookings(ids: number[]): Promise<void>
forceDeleteBooking(id: number): Promise<void>
getTodayArrivals(locationId?: number): Promise<Booking[]>
getTodayDepartures(locationId?: number): Promise<Booking[]>
```

---

### Feature 3: Customer Management

#### 3.1 Guest Profile View (Admin)
- **Guest list**: Searchable table of all guests (derived from bookings — guest_name, guest_email)
- **Guest profile**: Aggregate view per guest email showing:
  - Contact info (name, email)
  - Total stays count
  - Total nights stayed
  - Total amount spent
  - Preferred location (most bookings)
  - Average rating given (from reviews)
  - First visit / Last visit dates

#### 3.2 Stay Journal
- **Per guest**: Timeline of all stays (bookings) with:
  - Location name, room name
  - Check-in → check-out dates
  - Status (completed/cancelled)
  - Review if left
  - Amount paid
- **Sort**: Most recent first

#### 3.3 Booking History
- **Per guest**: Complete booking history including cancelled/trashed
- **Per room**: All bookings for a specific room
- **Per location**: All bookings for a specific location
- **Export capability**: (future — placeholder button)

#### 3.4 Loyalty Foundation (Data Layer)
- **Frequent guests**: Guests with 3+ bookings highlighted
- **VIP indicator**: Guests with 5+ bookings or $500+ total spend
- **Return rate**: Percentage of guests who book again
- NOTE: This is display-only analytics, no backend loyalty program yet

#### 3.5 Frontend Implementation Details
```
src/features/admin/customers/
├── CustomerList.tsx            # Searchable guest table
├── CustomerProfile.tsx         # Aggregate guest profile
├── StayJournal.tsx             # Timeline of stays
├── CustomerStats.tsx           # Stats cards (total stays, nights, spend)
├── customer.api.ts             # API calls (derived from booking data)
└── customer.types.ts           # Customer-specific types
```

#### 3.6 Backend API Needed (New Endpoints)
Since customer data is derived from bookings, create these backend endpoints:

```php
// routes/api.php — Admin only
Route::middleware(['check_token_valid', 'role:admin'])->prefix('v1/admin')->group(function () {
    Route::get('/customers', [CustomerController::class, 'index']);           // Paginated guest list
    Route::get('/customers/{email}', [CustomerController::class, 'show']);    // Guest profile by email
    Route::get('/customers/{email}/bookings', [CustomerController::class, 'bookings']); // Guest bookings
    Route::get('/customers/stats', [CustomerController::class, 'stats']);     // Aggregate stats
});
```

Backend implementation should:
- Aggregate from `bookings` table grouped by `guest_email`
- Join with `reviews` for rating data
- Join with `rooms` and `locations` for property info
- Use caching (5 min TTL) for aggregate queries
- Follow existing Service → Repository pattern

---

## Admin Dashboard (Container for Core Features)

### Layout
```
src/features/admin/
├── AdminLayout.tsx             # Sidebar + content area
├── AdminDashboard.tsx          # Main dashboard with overview cards
├── AdminSidebar.tsx            # Navigation sidebar
└── index.ts                    # Barrel exports
```

### Dashboard Overview Cards
- **Today**: Arrivals count, Departures count, Total occupied rooms
- **This Week**: New bookings, Revenue estimate
- **By Location**: 5 mini-cards showing occupancy rate per location
- **Quick Actions**: New booking, New room, View calendar

### Navigation Structure
```
Admin Panel
├── Dashboard (overview)
├── Rooms
│   ├── All Rooms (with location filter)
│   ├── Room Status Board
│   └── Add New Room
├── Bookings
│   ├── All Bookings
│   ├── Calendar View
│   ├── Today's Operations (check-in/out)
│   └── Trashed Bookings
├── Customers
│   ├── Guest List
│   └── Guest Profiles
├── Reviews
│   ├── Pending Approval
│   └── All Reviews
├── Messages
│   └── Contact Messages
└── Settings (placeholder)
```

### Route Configuration
```typescript
// Add to router.tsx
{
  path: '/admin',
  element: <AdminLayout />,
  children: [
    { index: true, element: <AdminDashboard /> },
    { path: 'rooms', element: <AdminRoomDashboard /> },
    { path: 'rooms/new', element: <RoomForm /> },
    { path: 'rooms/:id/edit', element: <RoomForm /> },
    { path: 'bookings', element: <AdminBookingDashboard /> },
    { path: 'bookings/calendar', element: <BookingCalendar /> },
    { path: 'bookings/today', element: <TodayOperations /> },
    { path: 'bookings/trashed', element: <TrashedBookings /> },
    { path: 'bookings/:id', element: <BookingDetail /> },
    { path: 'customers', element: <CustomerList /> },
    { path: 'customers/:email', element: <CustomerProfile /> },
    { path: 'reviews', element: <ReviewManagement /> },
    { path: 'messages', element: <ContactMessages /> },
  ]
}

// User routes
{ path: '/my-bookings', element: <ProtectedRoute><BookingList /></ProtectedRoute> },
{ path: '/my-bookings/:id', element: <ProtectedRoute><BookingDetail /></ProtectedRoute> },
```

---

## Implementation Guidelines

### Code Style & Patterns

**Frontend**:
1. Follow Feature-Sliced Design — each feature folder is self-contained
2. Use plain TypeScript validation (no Zod)
3. Use native `<input type="date">` for date fields
4. Use `@/shared/lib/api` Axios instance for all API calls
5. Use `showToast.success/error()` from `@/utils/toast` for notifications
6. Use `useAuth()` from `AuthContext` for auth state
7. Use Tailwind CSS for all styling — no CSS modules, no styled-components
8. Use React.lazy() for code splitting on admin routes
9. Handle loading states with existing `SkeletonCard` / `LoadingSpinner`
10. Handle errors with try/catch and toast notifications
11. All API response types follow `{ success: boolean, message: string, data: T }` wrapper

**Backend** (if new endpoints needed):
1. Thin controllers — delegate to services
2. Form Request classes for validation
3. API Resources for response transformation
4. Repository pattern for data access
5. Cache with tags for invalidation
6. Follow existing naming: `*Controller`, `*Service`, `*Request`, `*Resource`

### Type Safety
```typescript
// Always define request/response types
interface CreateRoomData {
  location_id: number
  name: string
  room_number?: string
  description?: string
  price: number
  max_guests: number
  status: 'available' | 'occupied' | 'maintenance'
}

// Always handle API error responses
interface ApiError {
  success: false
  message: string
  errors?: Record<string, string[]>
}
```

### Optimistic Locking Pattern (Frontend)
```typescript
// Always send lock_version with room updates
async function updateRoom(id: number, data: UpdateRoomData): Promise<Room> {
  try {
    const response = await api.put(`/v1/rooms/${id}`, {
      ...data,
      lock_version: data.lock_version,
    })
    return response.data.data
  } catch (error) {
    if (error.response?.status === 409) {
      showToast.error('Room was modified by another user. Please refresh and try again.')
      // Optionally refetch the room
      throw new OptimisticLockError('Version conflict')
    }
    throw error
  }
}
```

### Location-Aware Queries
All room and booking queries should be **location-aware**:
```typescript
// Always filter by location when showing rooms
const rooms = await api.get('/v1/rooms', { params: { location_id: selectedLocationId } })

// Bookings should show location context
const bookings = await api.get('/v1/admin/bookings', {
  params: { location_id: selectedLocationId, status: 'confirmed' }
})
```

---

## Testing Requirements

### Frontend Tests (Vitest + @testing-library/react)
For each new component, write at minimum:
1. **Render test**: Component renders without crashing
2. **Loading state**: Shows skeleton/spinner while loading
3. **Error state**: Shows error message on API failure
4. **User interaction**: Click handlers, form submission
5. **Data display**: Correct data rendered from mock API response

### Test Patterns (follow existing)
```typescript
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { vi, describe, it, expect } from 'vitest'

// Mock API
vi.mock('@/shared/lib/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  }
}))

// Wrap in MemoryRouter for components using React Router
import { MemoryRouter } from 'react-router-dom'
const renderWithRouter = (ui: React.ReactElement) =>
  render(<MemoryRouter>{ui}</MemoryRouter>)
```

---

## Deliverables Summary

### Priority Order
1. **Admin Layout + Dashboard** (container for everything)
2. **Room Management** (CRUD + status board per location)
3. **Booking List + Detail** (user-facing my-bookings)
4. **Admin Booking Management** (table + calendar + today ops)
5. **Customer Management** (guest list + profile)
6. **Backend: Customer endpoints** (new API for guest aggregation)
7. **Tests** for all new components

### File Count Estimate
- ~25-30 new frontend component files
- ~8-10 new API/types/validation files
- ~2-3 new backend controller/service files (customer feature)
- ~15-20 new test files

### Definition of Done
- [ ] All 5 locations render with correct room counts
- [ ] Admin can CRUD rooms per location with optimistic locking
- [ ] Users can view/cancel their bookings
- [ ] Admin can view/manage all bookings across locations
- [ ] Calendar view shows bookings per room per day
- [ ] Check-in/check-out workflow functions for today's operations
- [ ] Guest profiles aggregate correctly from booking data
- [ ] All new components have basic test coverage
- [ ] `npx tsc --noEmit` passes
- [ ] `npx vitest run` passes
- [ ] Existing 142 tests still pass
- [ ] No console errors in development
- [ ] Responsive design (mobile-friendly admin panel)
