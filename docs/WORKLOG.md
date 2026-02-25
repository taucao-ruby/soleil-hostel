# WORKLOG — Soleil Hostel (Append-only)

## 2026-02-25

- Change: Frontend Phases 0-4 complete + full docs sync.
- Why: Implement guest/admin dashboard, wire SearchCard to locations API, polish BookingForm, fix deprecated endpoints, sync all docs.
- Phase 0: Lazy-loaded `DashboardPage` with role-based routing (admin → AdminDashboard, user → GuestDashboard).
- Phase 1: `GuestDashboard` — booking list with filter tabs (All/Upcoming/Past), cancel with `ConfirmDialog`, skeleton/empty/error states, toast on success/error. New files: `bookings/GuestDashboard.tsx`, `useMyBookings.ts`, `bookingViewModel.ts`, `booking.constants.ts`, `ConfirmDialog.tsx`.
- Phase 2: `SearchCard` wired to `GET /v1/locations`; navigates to `/locations/:slug?check_in=&check_out=&guests=`.
- Phase 3: `AdminDashboard` — 3 tabs (Đặt phòng/Đã xóa/Liên hệ), `useAdminFetch<T>` hook, `AdminBookingCard`, `ContactCard`. New files: `admin/AdminDashboard.tsx`, `admin.api.ts`, `admin.types.ts`.
- Phase 4: `BookingForm` — URL params pre-fill, Vietnamese UI; `booking.api.ts` `/v1/bookings`; `room.api.ts` `/v1/rooms`; removed `AvailabilityResponse` dead type; `vi.hoisted` fix in `BookingForm.test.tsx`.
- Verification: `npx vitest run` → 194 tests, 19 suites, 0 failures. `tsc --noEmit` → 0 errors.
- Docs updated: `docs/README.md`, `docs/COMPACT.md`, `docs/WORKLOG.md`, `docs/DEVELOPMENT_HOOKS.md`, `docs/frontend/README.md`, `docs/frontend/ARCHITECTURE.md`, `docs/frontend/APP_LAYER.md`, `docs/frontend/FEATURES_LAYER.md`, `docs/frontend/SERVICES_LAYER.md`, `docs/frontend/TESTING.md`.
- Git: committed on dev → pushed → merged --no-ff to main → pushed. All pre-push hooks passed.

## 2026-02-12

- Change: Added COMPACT memory snapshot and append-only WORKLOG; linked memory docs from docs index.
- Why: Preserve high-signal context across long AI sessions with low maintenance cost.
- Files: `docs/COMPACT.md`, `docs/WORKLOG.md`, `docs/README.md`.
- Verification: Confirmed target paths/invariants from repository docs and code references.
- Notes/Risks: Snapshot uses latest verified command results recorded on 2026-02-11; refresh after next verification run.
