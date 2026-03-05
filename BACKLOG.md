# BACKLOG.md — Soleil Hostel

> **Product backlog — prioritized by implementation order**
> Last updated: 2026-03-05 | Source: COMPACT.md + KNOWN_LIMITATIONS.md + FINDINGS_BACKLOG.md

---

## Status Legend

| Symbol      | Meaning                            |
| ----------- | ---------------------------------- |
| 🔴 Blocker  | Blocks release or other features   |
| 🟠 High     | Must be done in the current sprint |
| 🟡 Medium   | Next sprint                        |
| 🟢 Low      | When time permits                  |
| ✅ Done     | Completed                          |
| ❌ Won't Do | Out of scope                       |

---

## EPIC 1 — Frontend Phase 5+

> Complete the dashboard and booking management experience

### FE-001 ✅ Booking Detail Panel (Guest) — Done 2026-02-27

**Description:** Guest clicks a booking → sees full details (room, location, dates, price, status, cancellation reason if applicable).

**Files created/modified:**

- `src/features/bookings/BookingDetailPanel.tsx` (new)
- `src/features/bookings/BookingDetailPanel.test.tsx` (new — 14 tests)
- `src/features/bookings/GuestDashboard.tsx` (add click handler)
- `src/features/booking/booking.api.ts` (add `getBookingById`)
- `src/features/booking/booking.types.ts` (add detail types)

**Acceptance Criteria:**

- [x] Click on a booking card → modal panel opens
- [x] Shows: room name + number, check-in/out dates, status badge, guest name/email, amount
- [x] If cancelled: shows `cancelled_at`
- [x] Has a "Close" button and can be dismissed with Escape or backdrop click

---

### FE-002 ✅ Admin Actions: Restore & Force-Delete Trashed Bookings — Done 2026-02-27

**Description:** The "Trashed" tab in AdminDashboard needs two action buttons: restore and permanently delete.

**Files modified:**

- `src/features/admin/AdminDashboard.tsx` (action buttons + ConfirmDialog)
- `src/features/admin/admin.api.ts` (add `restoreBooking`, `forceDeleteBooking`)
- `src/features/admin/AdminDashboard.test.tsx` (10 new tests)

**Acceptance Criteria:**

- [x] "Khôi phục" button → calls restore endpoint → booking restored
- [x] "Xóa vĩnh viễn" button → confirm dialog → calls force-delete → booking permanently removed
- [x] Shows success / error toast notification

---

### FE-003 ✅ Pagination for Admin Tabs — Done 2026-02-27

**Description:** Admin currently sees page 1 only. Add pagination to all 3 tabs.

**Files modified:**

- `src/features/admin/AdminDashboard.tsx` (PaginationControls, `useAdminPaginatedFetch`)
- `src/features/admin/admin.types.ts` (add `PaginationMeta`, `AdminBookingsPaginatedResult`)

**Acceptance Criteria:**

- [x] Each tab has page navigation (Trước / Sau)
- [x] Boundary buttons disabled at first/last page
- [x] Hidden when `last_page <= 1`

---

### FE-004 🟡 Booking Modification History (Guest)

**Source:** FG-001 from KNOWN_LIMITATIONS.md

**Description:** Guests can view the status change history of their booking.

**Acceptance Criteria:**

- [ ] Timeline shows: Created → Confirmed → Cancelled (with timestamps)
- [ ] Shows `cancelled_by` if booking was cancelled by an admin

---

### FE-005 🟢 PWA / Offline Support

**Description:** Add a service worker so the app works offline (view cached booking list).

**Acceptance Criteria:**

- [ ] Complete `manifest.json` (icon, theme-color, start_url)
- [ ] Service worker caches static assets + `GET /v1/bookings` API responses
- [ ] "You are offline" banner shown when network is unavailable

---

## EPIC 2 — Payment Integration

> Integrate real payment processing (currently mocked)
> **Source:** LIM-002 from KNOWN_LIMITATIONS.md

### PAY-001 🟡 Stripe / VNPay Integration (Phase 1) — Bootstrap Done

**Description:** Replace the mock `processRefund()` with the real Stripe SDK.

**Completed (March 1, 2026):**

- [x] Laravel Cashier `^16.3` installed, `Billable` trait on User model
- [x] Cashier migration (stripe_id, pm_type, pm_last_four, trial_ends_at)
- [x] `POST /api/webhooks/stripe` endpoint with signature verification
- [x] Webhook handlers: `payment_intent.succeeded`, `charge.refunded`, `payment_intent.payment_failed`
- [x] 14 tests (4 webhook signature + 10 handler tests)

**Remaining:**

- [ ] `POST /v1/bookings` → creates a Payment Intent before confirming
- [ ] Payment confirmed → booking status = `confirmed`
- [ ] Cancellation → automatic refund via Stripe
- [ ] Checkout session frontend UI
- [ ] No API keys committed (use `config('payment.stripe_secret')`)

---

### PAY-002 🟡 Webhook Handling for Payment Events — Partially Done

**Source:** LIM-006

**Description:** Receive webhooks from Stripe (payment.succeeded, refund.created, charge.failed).

**Completed (March 1, 2026):**

- [x] Endpoint `POST /api/webhooks/stripe` with signature verification
- [x] 3 event handlers implemented with idempotency checks

**Remaining:**

- [ ] Exponential backoff retry on webhook failure
- [ ] Dead letter queue for failed webhooks

---

### PAY-003 🟡 Booking Reminder Emails

**Source:** FG-003 from KNOWN_LIMITATIONS.md

**Description:** Automatically send reminder emails 1 day and 3 days before check-in.

**Acceptance Criteria:**

- [ ] Scheduled job runs daily (`php artisan schedule:run`)
- [ ] "Check-in Reminder" email template (Vietnamese)
- [ ] No email sent if booking is already cancelled

---

## EPIC 3 — Internationalization (i18n)

> **Source:** LIM-008 from KNOWN_LIMITATIONS.md

### I18N-001 ✅ Backend i18n (Laravel) — Done 2026-03-01

**Description:** Replace all hardcoded strings with the `__()` helper.

**Files created/modified:**

- `backend/lang/en/booking.php` (30 keys), `backend/lang/vi/booking.php`
- `backend/lang/en/messages.php` (17 keys), `backend/lang/vi/messages.php`
- 5 controllers updated: BookingController, RoomController, LocationController, AdminBookingController, ContactController
- `APP_LOCALE=vi` set in `.env.example`

**Acceptance Criteria:**

- [x] `APP_LOCALE=vi` → all system messages in Vietnamese
- [x] `APP_LOCALE=en` → English
- [ ] Date format follows locale (Carbon) — not yet implemented

---

### I18N-002 🟢 Frontend i18n (React)

**Description:** Replace hardcoded Vietnamese strings with i18n keys.

**Note:** Do not use react-i18next until the new dependency is explicitly approved.

**Acceptance Criteria:**

- [ ] Create `src/shared/lib/i18n.ts` with a simple key-value map
- [ ] Language toggle in the Header
- [ ] Falls back to Vietnamese if a key is missing

---

## EPIC 4 — Tech Debt & Code Quality

### TD-001 ✅ Standardize Error Response Format (Backend) — Done 2026-02-27

**Source:** TD-002 from KNOWN_LIMITATIONS.md

**Description:** Some exceptions return `{"error": "..."}` instead of `{"message": "...", "errors": {...}}`.

**Files modified:**

- `backend/app/Traits/ApiResponse.php` (add `trace_id`, `conflict()`)
- `backend/bootstrap/app.php` (exception handlers)
- `backend/app/Http/Middleware/EnsureUserHasRole.php`
- `backend/tests/Feature/ApiErrorFormatTest.php` (new — 10 tests, 57 assertions)

**Acceptance Criteria:**

- [x] All HTTP exceptions return standardized format with `trace_id`
- [x] Error format test added (ApiErrorFormatTest.php)
- [x] No stack trace leak in production

---

### TD-002 ✅ Standardize Comments (English only) — Done 2026-02-28

**Source:** TD-001 from KNOWN_LIMITATIONS.md

**Description:** Some backend files contain Vietnamese comments (e.g. CreateBookingService). Standardize to English.

**Acceptance Criteria:**

- [x] Grep all Vietnamese comments in `backend/app/`
- [x] Translate to English across 13 PHP files, preserve meaning
- [x] String literals (user-facing Vietnamese messages) intentionally preserved

---

### TD-003 ✅ Test Factory Completeness — Done 2026-03-01

**Source:** TD-003 from KNOWN_LIMITATIONS.md

**Description:** Add factories for missing edge cases.

**Acceptance Criteria:**

- [x] `BookingFactory::expired()` — booking that has already expired
- [x] `BookingFactory::multiDay(int $days)` — multi-day booking with specific dates
- [x] `BookingFactory::cancelledByAdmin()` — cancelled by an admin

---

### TD-004 🟡 Audit Log Retention Policy

**Source:** LIM-010 from KNOWN_LIMITATIONS.md

**Description:** Soft-deleted records and application logs grow indefinitely; a retention policy is needed.

**Acceptance Criteria:**

- [ ] Artisan command `bookings:archive --older-than=2y`
- [ ] Log rotation: Laravel daily driver, keep 30 days
- [ ] Update docs in OPERATIONAL_PLAYBOOK.md

---

## EPIC 5 — Deployment & Infrastructure

### OPS-001 🟠 Complete Deployment Pipeline (currently 60%)

**Description:** The CI/CD pipeline needs to be finalized to deploy to production.

**Completed (March 1, 2026):**

- [x] `docker-compose.prod.yml` with healthchecks (db, redis, backend, frontend)
- [x] `.env.production.example` (pgsql, no secrets)
- [x] Frontend multi-stage Dockerfile (nginx:1.27-alpine)
- [x] Caddy reverse proxy with auto-HTTPS (optional `--profile proxy`)
- [x] Docker rollback + HTTPS setup docs in OPERATIONAL_PLAYBOOK.md
- [x] Redis `protected-mode`, non-root Docker, security headers
- [x] CI: `frontend-typecheck` job, fixed hardcoded URLs, pinned actions
- [x] Secrets managed via GitHub Secrets (no hardcoding)

**Remaining:**

- [ ] SSH-based deploy step requires `DEPLOY_HOST` secret in GitHub
- [ ] Automated health check after deploy
- [ ] Automatic rollback if health check fails

---

### OPS-002 🟡 Email Delivery Tracking

**Source:** LIM-007 from KNOWN_LIMITATIONS.md

**Description:** Emails are currently fire-and-forget. Delivery tracking is needed.

**Acceptance Criteria:**

- [ ] Integrate SendGrid/Mailgun with webhook delivery events
- [ ] Bounce handling → mark invalid email addresses
- [ ] Delivery status shown in booking detail (admin view)

---

### OPS-003 🟢 Read Replica / Database Scaling

**Source:** LIM-001 from KNOWN_LIMITATIONS.md

**Description:** As traffic grows, a read replica is needed to reduce load on the primary DB.

**Acceptance Criteria:**

- [ ] Configure Laravel `database.connections.pgsql_read`
- [ ] Route SELECT queries to the read replica
- [ ] Benchmark before and after

---

## EPIC 6 — Feature Enhancements

### FEAT-001 🟡 Waitlist for Sold-Out Dates

**Source:** FG-004 from KNOWN_LIMITATIONS.md

**Description:** Guests can join a waitlist when a room is fully booked. Auto-notify when a slot opens.

**Acceptance Criteria:**

- [ ] `POST /v1/waitlist` — register by room + date range
- [ ] When a booking is cancelled → notify waitlist in registration order
- [ ] Guest can cancel their waitlist entry

---

### FEAT-002 🟢 Group Booking (multiple rooms in one transaction)

**Source:** FG-005 from KNOWN_LIMITATIONS.md

**Description:** Book multiple rooms in a single atomic transaction.

**Note:** Requires a DB schema redesign (booking_group table). Create an ADR before implementing.

**Acceptance Criteria:**

- [ ] `POST /v1/booking-groups` with an array of room_ids
- [ ] Atomic: all succeed or all rollback
- [ ] UI: select multiple rooms in BookingForm

---

### FEAT-003 🟢 Guest Messaging System

**Source:** FG-002 from KNOWN_LIMITATIONS.md

**Description:** Staff can message guests within the system (without going through email).

**Acceptance Criteria:**

- [ ] Thread messages per booking
- [ ] Real-time via WebSocket (Laravel Reverb or Pusher)
- [ ] Unread count badge in the dashboard

---

## Dependency Map

```
FE-001 (Detail Panel)    → independent
FE-002 (Admin Actions)   → backend restore + force-delete endpoints already exist
FE-003 (Pagination)      → independent
PAY-001 (Stripe)         → blocks PAY-002, PAY-003
I18N-001 (Backend)       → should be done before I18N-002 (Frontend)
OPS-001 (Deploy)         → blocks all production-bound items
FEAT-002 (Group Booking) → requires a new ADR before coding
```

---

## Current Sprint (Q1-2026)

| Item                                                          | Assignee | Status                                   |
| ------------------------------------------------------------- | -------- | ---------------------------------------- |
| FE-001 Booking Detail Panel                                   | —        | ✅ Done (2026-02-27)                     |
| FE-002 Admin Restore/Force-Delete                             | —        | ✅ Done (2026-02-27)                     |
| FE-003 Admin Pagination                                       | —        | ✅ Done (2026-02-27)                     |
| TD-001 Error Response Format                                  | —        | ✅ Done (2026-02-27)                     |
| TD-002 Standardize Comments                                   | —        | ✅ Done (2026-02-28)                     |
| TD-003 Test Factory Completeness                              | —        | ✅ Done (2026-03-01)                     |
| I18N-001 Backend i18n                                         | —        | ✅ Done (2026-03-01)                     |
| PAY-001 Stripe Bootstrap + Webhooks                           | —        | ✅ Bootstrap Done (2026-03-01)           |
| Batch 3 Backend Quality (H-04, H-05, M-01..M-09, M-12)        | —        | ✅ Done (2026-03-02)                     |
| Batch 4 Frontend Quality (H-08, H-09, M-14, M-17, M-18, M-21) | —        | ✅ Done (2026-03-02)                     |
| OPS-001 Deploy Pipeline                                       | —        | 🟠 60% (infra done, deploy step pending) |

---

## Done (reference)

| Item                                                                                          | Completed         | Notes     |
| --------------------------------------------------------------------------------------------- | ----------------- | --------- |
| ✅ Auth system (Bearer + HttpOnly)                                                            | Dec 2025          | 44 tests  |
| ✅ Booking system (lock, soft delete, audit)                                                  | Dec 2025          | 60 tests  |
| ✅ Room management + optimistic lock                                                          | Jan 2026          | 151 tests |
| ✅ RBAC (3 roles, enum)                                                                       | Dec 2025          | 47 tests  |
| ✅ Security headers + XSS + rate limiting                                                     | Dec 2025–Jan 2026 | 91 tests  |
| ✅ Email templates + notifications                                                            | Jan 2026          | 36 tests  |
| ✅ Redis caching + event-driven invalidation                                                  | Dec 2025          | 6 tests   |
| ✅ Monitoring + health probes                                                                 | Jan 2026          | 30 tests  |
| ✅ Multi-location architecture (ADR-013)                                                      | Feb 2026          | —         |
| ✅ Frontend Phase 0-4 (Dashboard, Search, Admin, Booking)                                     | Feb 2026          | 194 tests |
| ✅ Audit v1–v4 (20/20 findings resolved)                                                      | Feb 2026          | —         |
| ✅ CLAUDE.md governance framework                                                             | Feb 2026          | —         |
| ✅ OpenAPI 3.1 spec + Redoc                                                                   | Jan 2026          | —         |
| ✅ Email verification (MustVerifyEmail)                                                       | Jan 2026          | —         |
| ✅ Branded email templates                                                                    | Jan 2026          | 13 tests  |
| ✅ FE-001 Booking Detail Panel                                                                | Feb 27, 2026      | 14 tests  |
| ✅ FE-002 Admin Restore/Force-Delete                                                          | Feb 27, 2026      | 10 tests  |
| ✅ FE-003 Admin Pagination                                                                    | Feb 27, 2026      | —         |
| ✅ TD-001 Standardize API Error Format                                                        | Feb 27, 2026      | 10 tests  |
| ✅ TD-002 Standardize Comments (English)                                                      | Feb 28, 2026      | —         |
| ✅ Phase 5 Clean-up (ship script, rollup CVE)                                                 | Feb 28, 2026      | —         |
| ✅ OPS-001 Infra (prod compose, Caddy, Docker hardening)                                      | Mar 1, 2026       | —         |
| ✅ PAY-001 Cashier Bootstrap + Stripe Webhooks                                                | Mar 1, 2026       | 14 tests  |
| ✅ I18N-001 Backend i18n (47 keys, en + vi)                                                   | Mar 1, 2026       | 9 tests   |
| ✅ TD-003 BookingFactory helpers                                                              | Mar 1, 2026       | —         |
| ✅ DevSecOps Batch 1 (Redis, Caddy, CI gates)                                                 | Mar 1, 2026       | —         |
| ✅ Batch 2 Backend Fixes (C-01, C-02, H-01, H-03)                                             | Mar 1, 2026       | 21 tests  |
| ✅ minimatch CVE fix (GHSA-7r86, GHSA-23c5)                                                   | Mar 1, 2026       | —         |
| ✅ Batch 3: HealthService extraction, FormRequests, PHPStan/Larastan, Contact+Review tests    | Mar 2, 2026       | 67 tests  |
| ✅ Batch 4: AbortController cleanup, vi.hoisted auth mocks, no-console ESLint, RoomList tests | Mar 2, 2026       | 8 tests   |

---

_See product goals at [PRODUCT_GOAL.md](./PRODUCT_GOAL.md) — See project health at [PROJECT_STATUS.md](./PROJECT_STATUS.md)_
