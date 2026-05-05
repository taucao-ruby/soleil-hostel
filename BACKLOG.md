# BACKLOG.md — Soleil Hostel

> **Product backlog — prioritized by implementation order**
> Last updated: 2026-05-05 | Source: COMPACT.md + KNOWN_LIMITATIONS.md + FINDINGS_BACKLOG.md + PERMISSION_MATRIX.md
>
> Apr–May 2026 batches landed on `dev` (commits `347649a` → `10b153e`): AI Harness hardening (proposal lifecycle, HMAC audit, PII hard-block, AI-001 prompt-injection defense), batch-8 kill-switch + E2E smoke gate, Booking integrity wave (state-machine invariants, payment-hold, durable refund idempotency, actor snapshots, no-overlap constraint hardening, deposit FSM, stay cancellation propagation OPS-004), Auth + observability hardening (batch-2 Sanctum, F-32, RBAC-001, PII redaction, OBS-001/OBS-002), AUTH-004 OTP resend race-hardening. **Backend product surface for booking + AI is now production-quality;** frontend payment checkout remains the largest open gap.

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

### PAY-001 🟠 Stripe / VNPay Integration (Phase 2 — Frontend Checkout) — Backend Done, Frontend Pending

**Description:** Wire up the frontend checkout session and confirmation UX. Backend is now ready end-to-end (payment-hold on creation, durable refund-event idempotency).

**Completed (March 1, 2026):**

- [x] Laravel Cashier `^16.3` installed, `Billable` trait on User model
- [x] Cashier migration (stripe_id, pm_type, pm_last_four, trial_ends_at)
- [x] `POST /api/webhooks/stripe` endpoint with signature verification
- [x] Webhook handlers: `payment_intent.succeeded`, `charge.refunded`, `payment_intent.payment_failed`

**Completed (April 22, 2026 — `ae2d070`, `abc3959`):**

- [x] **Stripe payment-hold on booking creation** + pending-limit enforcement (`ae2d070`)
- [x] **Durable refund idempotency**: `IdempotencyGuard` (in-memory, non-durable) deleted (-515 LOC); replaced with `stripe_refund_events` table + `UNIQUE(stripe_refund_id)` (`abc3959`)
- [x] DB INSERT-before-lookup eliminates the application-layer TOCTOU window
- [x] `amount_refunded` sourced from `charge.amount_refunded` (cumulative) — handles partial-then-full refund sequences correctly
- [x] `RefundIdempotencyTest` covering replay delivery, partial refund, and concurrent INSERT
- [x] `BookingPaymentHoldTest`, `StripeWebhookHandlerTest`, `BookingStateMachineInvariantTest`

**Remaining (frontend only):**

- [ ] Checkout session frontend UI (`POST /v1/bookings` already creates the Payment Intent — UI just needs to render Stripe Elements / Checkout)
- [ ] Frontend payment-confirmation status display (success / failed / pending)
- [ ] Cancellation triggers refund — backend already handles this; surface refund status in `BookingDetailPanel`

---

### PAY-002 ✅ Webhook Handling for Payment Events — Done 2026-04-22

**Source:** LIM-006

**Description:** Receive webhooks from Stripe with durable idempotency.

**Completed:**

- [x] Endpoint `POST /api/webhooks/stripe` with Cashier-signed verification
- [x] Event handlers: `payment_intent.succeeded`, `charge.refunded`, `payment_intent.payment_failed`
- [x] **Durable replay fence**: `stripe_refund_events.stripe_refund_id` UNIQUE — survives process restart and horizontal pod scaling (`abc3959`)
- [x] Validates `stripe_event_id` + `payment_intent_id` presence before processing; structured log fields on all early-exit paths
- [x] `booking_id` FK is nullable + `nullOnDelete` to decouple refund audit trail from booking lifecycle

**Remaining (deferred to OPS-004 successor — non-blocking):**

- [ ] Exponential backoff retry on webhook failure (Stripe handles retries on its side; we currently don't requeue on app-side error)
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

### TD-005 🟡 RBAC Follow-Ups (FU-1..FU-5) — from PERMISSION_MATRIX.md

**Source:** `docs/PERMISSION_MATRIX.md` Open Follow-Ups (Mar 10, 2026)

**Description:** 5 follow-up items identified during RBAC hardening. All relate to test coverage gaps and config verification.

**Items:**

- [ ] **FU-1**: Migrate legacy cancellation tests from `/api/bookings/` to `/api/v1/bookings/{id}/cancel`
- [ ] **FU-2**: Add moderator-denial test for `restore-bulk`; add v1 pin test for `trashed/{id}`
- [ ] **FU-3**: Verify `config('booking.cancellation.allow_after_checkin')` source file and production value
- [ ] **FU-4**: Re-inspect `RoomController` post-hardening to confirm policy layer intact
- [ ] **FU-5**: Migrate room CREATE/PUT/DELETE auth tests from `/api/rooms/*` to `/api/v1/rooms/*`

**Acceptance Criteria:**

- [ ] All legacy test paths migrated to v1
- [ ] Moderator-denial coverage for all admin endpoints
- [ ] Config source verified and documented
- [ ] No TEST-SURFACE DRIFT remaining in PERMISSION_MATRIX.md

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

## Current Sprint (Q2-2026)

| Item                                            | Assignee | Status                                    |
| ----------------------------------------------- | -------- | ----------------------------------------- |
| PAY-001 Frontend Stripe checkout UI             | —        | 🟠 **Backend ready** — frontend pending (payment-hold + durable refund idempotency landed Apr 22) |
| OPS-001 Deploy Pipeline                         | —        | 🟠 60% — F-04 pre-flight DEPLOY_HOST gate landed Apr 17; SSH deploy step + automated rollback still pending |
| TD-005 RBAC Follow-ups (FU-1..5)                | —        | 🟡 Next (test migration + config verify) — partly addressed by RBAC-001 lockdown Apr 26 |
| FE-004 Booking History                          | —        | 🟡 Next                                   |
| I18N-002 Frontend i18n                          | —        | 🟢 When time permits                      |
| TD-004 Audit Log Retention                      | —        | 🟡 Next                                   |
| F-25 api.ts CSRF path fix                       | —        | 🟢 Non-critical (CSRF architecture clarified Mar 29; path discrepancy remains low-risk) |
| rooms.status normalization + RoomStatus enum    | —        | 🟡 Phase 3 landed (`89e42b8`); DB CHECK still TBD |
| Legacy migration gating cleanup (2026_02_09)    | —        | 🟢 Standardize config→DB::getDriverName() |
| Frontend AI proposal-confirmation UX hardening  | —        | 🟡 Backend lifecycle is durable + drift-detected; verify FE handles `ProposalNotShownException`, `ProposalExpiredException`, `ProposalPriceChangedException`, `ProposedRoomNoLongerAvailableException` cleanly |
| Admin reviews + messages routes                 | —        | 🟡 Sidebar links exist; routes intentionally not implemented (TL-04) — implement or remove |
| Booking form `number_of_guests` + `special_requests` persistence | — | 🟡 TL-03 — backend doesn't validate/persist; either wire it through or remove from UI |
| TL-01 Admin booking screens parse `data.bookings` (not `data.data`) | — | 🟠 High — fix admin pagination response parsing |

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
| ✅ Docs sync v5 + RBAC UX audit (frontend RBAC.md + RBAC_UX_AUDIT.md)                         | Mar 9, 2026       | —         |
| ✅ RBAC Hardening: defense-in-depth (route+gate), PERMISSION_MATRIX.md, moderator denial tests| Mar 10, 2026      | 16 tests  |
| ✅ RBAC Phases 1-3: enforcement gaps, admin audit log, moderator role activated               | Mar 11, 2026      | —         |
| ✅ Admin panel expansion: AdminLayout, sidebar, room/booking/customer mgmt (frontend)         | Mar 12, 2026      | —         |
| ✅ Repo hygiene: artifact cleanup, toolchain normalization, CI hygiene hooks                  | Mar 12, 2026      | —         |
| ✅ Repository structure audit 2026-03-12 (AUDIT_2026_03_12_STRUCTURE.md)                      | Mar 12, 2026      | —         |
| ✅ RBAC mobile remediation: admin route guard on frontend                                     | Mar 13, 2026      | —         |
| ✅ Password complexity enforcement on registration + EmailVerificationTest                    | Mar 13-14, 2026   | —         |
| ✅ CVE fix: flatted >=3.4.0 + undici >=7.24.0 (ef138cc)                                       | Mar 14, 2026      | —         |
| ✅ DB hardening: FK delete policies (4 FKs CASCADE→SET NULL/RESTRICT)                         | Mar 17, 2026      | 3 migrations, 8 tests |
| ✅ DB hardening: chk_rooms_max_guests CHECK constraint                                        | Mar 17, 2026      | PG-only   |
| ✅ DB hardening: chk_bookings_status CHECK constraint                                         | Mar 17, 2026      | PG-only   |
| ✅ v3.2 operations: room readiness, blockage resolver, financial ops                          | Mar 21, 2026      | 1009 tests |
| ✅ v3.3 static analysis: Psalm 35→0, PHPStan 151→0 (Level 5, no baseline, no ignores)         | Mar 21, 2026      | 1037 tests |
| ✅ v3.4 operational completion: readiness, classification, deposit, settlement, escalation engine, OperationalDashboardService (16 metrics) | Mar 23, 2026 | — |
| ✅ Restore path integrity: `BookingService::restore()` in transaction with FOR UPDATE (TOCTOU-safe) | Mar 29, 2026 | 16 tests |
| ✅ Admin booking filters: 7 server-side filter params + ILIKE search (fixes TL-02)             | Mar 29, 2026      | 24 tests  |
| ✅ Moderator SPA access: `AdminRoute.tsx` `minRole` prop; room routes admin-only (fixes TL-05) | Mar 29, 2026      | —         |
| ✅ ReviewForm.tsx: star-rating review form, Vietnamese UI, 403/422 handling                    | Mar 29, 2026      | 10 tests  |
| ✅ picomatch ReDoS CVE fix (GHSA-c2c7-rcm5-vvqj) via pnpm overrides                           | Mar 30, 2026      | —         |
| ✅ Docs sync v3: 9 findings patched, F-02 resolved via live test run (1047 tests confirmed)    | Mar 31, 2026      | —         |
| ✅ Concurrent booking HTTP 500 fix + IP-rate-limit collapse fix (`bd91e90`)                    | Apr 3–4, 2026     | —         |
| ✅ Email verification OTP flow (full-stack 6-digit code; race-hardened May 2)                  | Apr 3, 2026       | EmailVerificationCodeService + Test |
| ✅ Location availability fix (`scopeWithRoomCounts`; LocationResource + LocationCard use rooms_count) | Apr 3, 2026 | — |
| ✅ PHPStan 10→0 errors + Psalm 4→0 errors after Apr 3 file additions                           | Apr 4, 2026       | Level 5, no baseline |
| ✅ TS5103 tsconfig fix + axios CVE upgrade (GHSA-3p68-rc4w-qgx5)                               | Apr 4, 2026       | —         |
| ✅ AI Harness Phases 0–4: 7 endpoints, 7-layer safety pipeline, kill switch, canary, eval framework, proposal-confirmation flow | Apr 9–11, 2026 | 50+ backend files, 2 migrations, 7 endpoints, 3 middleware |
| ✅ Documentation governance audit + AI Harness docs sync                                       | Apr 12, 2026      | 10 docs updated |
| ✅ F-06 proposer-binding (cache-envelope `proposer_user_id`) + CancellationService ownership gate | Apr 13–17, 2026 | T-13 superseded |
| ✅ F-04 pre-flight DEPLOY_HOST gate + migration-before-health reordering                       | Apr 13–17, 2026   | —         |
| ✅ OpenAPI Spectral contract-lint CI gate (`contract-lint.yml`)                                | Apr 17, 2026      | —         |
| ✅ Documentation governance remediation pass — docs aligned with post-F-06 code truth          | Apr 18, 2026      | 11 docs updated |
| ✅ Booking state-machine invariants + idempotent Stripe webhook handling (`ac7275b`)           | Apr 22, 2026      | —         |
| ✅ Stripe payment-hold on booking creation + pending-limit enforcement (`ae2d070`)             | Apr 22, 2026      | BookingPaymentHoldTest |
| ✅ Batch-4 platform hardening: policy dedup, rooms.status phase 3, Redis kill switches, CI gates (`89e42b8`) | Apr 22, 2026 | — |
| ✅ Durable Stripe refund idempotency: `IdempotencyGuard` deleted, `stripe_refund_events` UNIQUE — TOCTOU eliminated (`abc3959`) | Apr 22, 2026 | RefundIdempotencyTest |
| ✅ SESSION_SECURE_COOKIE explicit env + fail-fast boot guard (`ef1`)                           | Apr 22, 2026      | —         |
| ✅ Batch-2 Sanctum hardening: atomic refresh, fingerprint binding, fence-post unification      | Apr 25, 2026      | —         |
| ✅ Operations API hardening (frontend `4323e90`)                                               | Apr 25, 2026      | —         |
| ✅ Stripe origin pinning in CSP middleware + Caddyfile (`95f9f80`)                             | Apr 26, 2026      | —         |
| ✅ Redis auth enforced in non-local environments via dual-layer guard (`1737970` + `fd796cf`)  | Apr 26, 2026      | —         |
| ✅ Docker compose config gate hardened against host-env shadowing (`093f5ae`)                  | Apr 26, 2026      | —         |
| ✅ TransactionExceptions decomposed into SRP hierarchy (`746a5bf`)                             | Apr 26, 2026      | —         |
| ✅ Token refresh type guard + pending-count lock semantics (`10b5346`)                         | Apr 26, 2026      | —         |
| ✅ Null-booking guard after `fresh()` in ReconcileRefundsJob (`2e120c0`)                       | Apr 26, 2026      | —         |
| ✅ F-32 Sanctum `findToken()` for Bearer lookup in `detectAuthMode` (`4ab9cfd`)                | Apr 27, 2026      | —         |
| ✅ Durable AI proposal lifecycle + drift detection + proposer binding (`5a295c0`)              | Apr 27, 2026      | —         |
| ✅ AI harness PII hard-block + injection bypass prevention + HMAC audit (`e588432`)            | Apr 27, 2026      | —         |
| ✅ AI-001 policy-document prompt-injection defense (`347649a`)                                 | Apr 27, 2026      | —         |
| ✅ OBS-001 + OBS-002: admin-gated detail health probes (no topology leak to anonymous callers) | Apr 28, 2026      | —         |
| ✅ PII redaction across all log channels and Sentry (`cb7911a`)                                | Apr 28, 2026      | —         |
| ✅ RBAC-001: contact messages locked to admin via `ContactMessagePolicy` (`04c7d63`)           | Apr 28, 2026      | —         |
| ✅ Immutable actor snapshot on bookings + admin_audit_logs (`048e40b`)                         | Apr 30, 2026      | AiProposalEventActorPreservationTest |
| ✅ `no_overlapping_bookings` constraint assertion + pre-deploy gate (`92f1ad1`)                | May 1, 2026       | —         |
| ✅ CancellationService actor snapshot type contracts hardened (Psalm/PHPStan zero-error)       | May 1, 2026       | —         |
| ✅ Deposit FSM lifecycle + null-user reconciliation (CONC-005/006) (`b69a7a0`)                 | May 2, 2026       | —         |
| ✅ OPS-004: stay cancellation propagation (`7027adb`)                                          | May 2, 2026       | —         |
| ✅ AUTH-004: OTP resend race-hardened against concurrent requests (`1079946`)                  | May 2, 2026       | —         |
| ✅ Batch-8 kill-switch hardening + E2E smoke gate + CI manifests (`c5a37dc`)                   | May 2, 2026       | —         |
| ✅ AI harness test alignment with AUTH-004 kill-switch migration (`10b153e`)                   | May 2, 2026       | HEAD      |

---

_See product goals at [PRODUCT_GOAL.md](./PRODUCT_GOAL.md) — See project health at [PROJECT_STATUS.md](./PROJECT_STATUS.md)_
