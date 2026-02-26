# BACKLOG.md — Soleil Hostel

> **Product backlog — prioritized by implementation order**
> Last updated: 2026-02-26 | Source: COMPACT.md + KNOWN_LIMITATIONS.md + FINDINGS_BACKLOG.md

---

## Status Legend

| Symbol | Meaning |
| ------ | ------- |
| 🔴 Blocker | Blocks release or other features |
| 🟠 High | Must be done in the current sprint |
| 🟡 Medium | Next sprint |
| 🟢 Low | When time permits |
| ✅ Done | Completed |
| ❌ Won't Do | Out of scope |

---

## EPIC 1 — Frontend Phase 5+

> Complete the dashboard and booking management experience

### FE-001 🟠 Booking Detail Panel (Guest)

**Description:** Guest clicks a booking → sees full details (room, location, dates, price, status, cancellation reason if applicable).

**Files to create/modify:**

- `src/features/bookings/BookingDetailPanel.tsx` (new)
- `src/features/bookings/GuestDashboard.tsx` (add click handler)
- `src/features/booking/booking.api.ts` (add `GET /v1/bookings/:id`)
- `src/features/bookings/BookingDetailPanel.test.tsx` (new)

**Acceptance Criteria:**

- [ ] Click on a booking card → slide-in panel or modal opens
- [ ] Shows: room name, location, check-in/out dates, guest count, status, created date
- [ ] If cancelled: shows `cancelled_at` + `cancellation_reason`
- [ ] Has a "Close" button and can be dismissed with Escape

---

### FE-002 🟠 Admin Actions: Restore & Force-Delete Trashed Bookings

**Description:** The "Trashed" tab in AdminDashboard needs two action buttons: restore and permanently delete.

**Files to modify:**

- `src/features/admin/AdminDashboard.tsx`
- `src/features/booking/booking.api.ts` (add `POST /v1/bookings/:id/restore`, `DELETE /v1/bookings/:id/force`)
- `src/features/admin/AdminDashboard.test.tsx`

**Acceptance Criteria:**

- [ ] "Restore" button → calls restore endpoint → booking disappears from Trashed tab, appears in Bookings tab
- [ ] "Force Delete" button → confirm dialog → calls force-delete → booking is permanently removed
- [ ] Shows success / error toast notification

---

### FE-003 🟡 Pagination for Admin Tabs

**Description:** Admin currently sees page 1 only. Add pagination to all 3 tabs.

**Files to modify:**

- `src/features/admin/AdminDashboard.tsx`
- `src/features/admin/useAdminFetch.ts` (add page params)

**Acceptance Criteria:**

- [ ] Each tab has page navigation (Previous / Next)
- [ ] URL or state reflects the current page
- [ ] Active tab is preserved when changing pages

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

### PAY-001 🔴 Stripe / VNPay Integration (Phase 1)

**Description:** Replace the mock `processRefund()` with the real Stripe SDK.

**Backend files to modify:**

- `backend/app/Services/CancellationService.php` (processRefund)
- `backend/config/payment.php` (new)
- `backend/app/Jobs/ReconcileRefundsJob.php`

**Acceptance Criteria:**

- [ ] `POST /v1/bookings` → creates a Payment Intent before confirming
- [ ] Payment confirmed → booking status = `confirmed`
- [ ] Cancellation → automatic refund via Stripe
- [ ] No API keys committed (use `config('payment.stripe_secret')`)

---

### PAY-002 🟡 Webhook Handling for Payment Events

**Source:** LIM-006

**Description:** Receive webhooks from Stripe (payment.succeeded, refund.created, charge.failed).

**Acceptance Criteria:**

- [ ] Endpoint `POST /api/webhooks/stripe` with signature verification
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

### I18N-001 🟡 Backend i18n (Laravel)

**Description:** Replace all hardcoded strings with the `__()` helper.

**Files:**

- `backend/resources/lang/vi/` (new)
- `backend/resources/lang/en/` (new)
- All Notifications and validation messages

**Acceptance Criteria:**

- [ ] `APP_LOCALE=vi` → all system messages in Vietnamese
- [ ] `APP_LOCALE=en` → English
- [ ] Date format follows locale (Carbon)

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

### TD-001 🟡 Standardize Error Response Format (Backend)

**Source:** TD-002 from KNOWN_LIMITATIONS.md

**Description:** Some exceptions return `{"error": "..."}` instead of `{"message": "...", "errors": {...}}`.

**Files:**

- `backend/app/Exceptions/Handler.php`

**Acceptance Criteria:**

- [ ] All HTTP exceptions return the same format
- [ ] Add error format test in ExceptionHandlerTest
- [ ] Document in openapi.yaml

---

### TD-002 🟢 Standardize Comments (English only)

**Source:** TD-001 from KNOWN_LIMITATIONS.md

**Description:** Some backend files contain Vietnamese comments (e.g. CreateBookingService). Standardize to English.

**Acceptance Criteria:**

- [ ] Grep all Vietnamese comments in `backend/app/`
- [ ] Translate to English, preserve meaning

---

### TD-003 🟢 Test Factory Completeness

**Source:** TD-003 from KNOWN_LIMITATIONS.md

**Description:** Add factories for missing edge cases.

**Acceptance Criteria:**

- [ ] `BookingFactory::expired()` — booking that has already expired
- [ ] `BookingFactory::multiDay(int $days)` — multi-day booking with specific dates
- [ ] `BookingFactory::cancelledByAdmin()` — cancelled by an admin

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

### OPS-001 🔴 Complete Deployment Pipeline (currently 50%)

**Description:** The CI/CD pipeline needs to be finalized to deploy to production.

**Acceptance Criteria:**

- [ ] GitHub Actions workflow `deploy.yml` for staging and production
- [ ] Automated health check after deploy
- [ ] Automatic rollback if health check fails
- [ ] Secrets managed via GitHub Secrets (no hardcoding)

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

| Item | Assignee | Status |
| ---- | -------- | ------ |
| FE-001 Booking Detail Panel | — | 🟠 Ready |
| FE-002 Admin Restore/Force-Delete | — | 🟠 Ready |
| FE-003 Admin Pagination | — | 🟡 Backlog |
| TD-001 Error Response Format | — | 🟡 Backlog |
| OPS-001 Deploy Pipeline | — | 🔴 Blocker |

---

## Done (reference)

| Item | Completed | Notes |
| ---- | --------- | ----- |
| ✅ Auth system (Bearer + HttpOnly) | Dec 2025 | 44 tests |
| ✅ Booking system (lock, soft delete, audit) | Dec 2025 | 60 tests |
| ✅ Room management + optimistic lock | Jan 2026 | 151 tests |
| ✅ RBAC (3 roles, enum) | Dec 2025 | 47 tests |
| ✅ Security headers + XSS + rate limiting | Dec 2025–Jan 2026 | 91 tests |
| ✅ Email templates + notifications | Jan 2026 | 36 tests |
| ✅ Redis caching + event-driven invalidation | Dec 2025 | 6 tests |
| ✅ Monitoring + health probes | Jan 2026 | 30 tests |
| ✅ Multi-location architecture (ADR-013) | Feb 2026 | — |
| ✅ Frontend Phase 0-4 (Dashboard, Search, Admin, Booking) | Feb 2026 | 194 tests |
| ✅ Audit v1–v4 (20/20 findings resolved) | Feb 2026 | — |
| ✅ CLAUDE.md governance framework | Feb 2026 | — |
| ✅ OpenAPI 3.1 spec + Redoc | Jan 2026 | — |
| ✅ Email verification (MustVerifyEmail) | Jan 2026 | — |
| ✅ Branded email templates | Jan 2026 | 13 tests |

---

*See product goals at [PRODUCT_GOAL.md](./PRODUCT_GOAL.md) — See project health at [PROJECT_STATUS.md](./PROJECT_STATUS.md)*
