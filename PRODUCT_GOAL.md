# PRODUCT_GOAL.md — Soleil Hostel

> **Product goals and strategic direction**
> Last updated: 2026-05-05
>
> **UI DESIGN CONTEXT (Google Stitch):**
> This is the primary product brief for UI generation. Send this file first.
> Language: **Vietnamese** (all labels, buttons, status badges, error messages).
> Layout contract: **mobile-first** — BottomNav on `/` (mobile), Header+Footer on all other routes (desktop+mobile).
> Design system: **TailwindCSS utility classes** — no custom component library.
> Color signals: `pending` → yellow/amber | `confirmed` → green | `cancelled` → red/muted | `refund_failed` → orange + escalation alert.
> **Do NOT design**: `/admin/reviews`, `/admin/messages` (routes not implemented).
> **Do NOT design**: `number_of_guests` or `special_requests` form persistence (backend not wired to read these).
> **Do NOT design**: Online payment checkout or Stripe UI (backend ready — payment-hold + durable refund idempotency landed Apr 22; frontend checkout still pending).
> **Do NOT design**: AI proposal-confirmation widget visuals beyond the existing `RoomDiscoveryWidget` (AI proposal lifecycle is durable + proposer-bound; Stitch should not redesign without checking `docs/HARNESS_ENGINEERING.md`).

---

## 1. Vision

> **"A simple, transparent, and reliable hostel booking experience for travelers in Hue."**

Soleil Hostel is an in-house booking platform for the **Soleil** hostel chain in Hue City, Vietnam. The system covers the full lifecycle of a booking — from room search, reservation, and management through check-out — on a single web interface that works well on both mobile and desktop.

---

## 2. Target Users (Personas)

| Group         | Vietnamese Label  | Description                                       | Core Need                                                    | Entry Point         |
| ------------- | ----------------- | ------------------------------------------------- | ------------------------------------------------------------ | ------------------- |
| **Guest**     | Khách             | Domestic and international travelers visiting Hue | Find rooms, book quickly, view and cancel their own bookings | `/` → `/booking` → `/dashboard` |
| **Moderator** | Nhân viên         | Staff at Soleil properties                        | Read-only admin: view all bookings, view customer profiles   | `/dashboard` → `/admin/bookings` |
| **Admin**     | Quản trị viên     | System and operations managers                    | Full control: manage bookings, rooms, customers, restore/force-delete | `/dashboard` → `/admin/*` |

> **Persona note for Stitch**: Guest sees `GuestDashboard`. Moderator also sees `GuestDashboard` at `/dashboard` but additionally has access to the full `/admin/*` tree (read-only). Admin sees `AdminDashboard` at `/dashboard` AND full `/admin/*` write access.

---

## 3. Core Value Propositions

### For guests

- **No double-booking** — The system prevents conflicts via pessimistic locking + PostgreSQL exclusion constraint
- **Vietnamese UI, mobile-first** — Interface fully in Vietnamese, optimized for phone screens
- **Transparent status** — Guests can see their full booking history (All / Upcoming / Past) and cancel with confirmation

### For operations

- **No data loss** — Soft delete + audit trail (deleted_at, deleted_by, cancelled_at, cancelled_by, cancellation_reason)
- **Recoverable** — Admin can restore or force-delete soft-deleted bookings
- **High security** — Bearer + HttpOnly cookie dual auth, token rotation, CSRF protection, A+ security headers

---

## 4. Current Feature Set

### Screen Inventory (for UI design)

| Screen | Route | Persona | Status |
| --- | --- | --- | --- |
| Homepage + Search | `/` | All | ✅ Live |
| Room List | `/rooms` | All | ✅ Live |
| Location List | `/locations` | All | ✅ Live |
| Location Detail + Availability | `/locations/:slug` | All | ✅ Live |
| Booking Form | `/booking?room_id=&check_in=&check_out=` | Authenticated | ✅ Live |
| My Bookings List | `/my-bookings` | Authenticated | ✅ Live |
| My Booking Detail | `/my-bookings/:id` | Authenticated | ✅ Live |
| Guest Dashboard | `/dashboard` (role: user, moderator) | Guest + Moderator | ✅ Live |
| Admin Dashboard | `/dashboard` (role: admin) | Admin | ✅ Live |
| Admin Overview | `/admin` | Moderator + Admin | ✅ Live |
| Admin All Bookings | `/admin/bookings` | Moderator + Admin | ✅ Live |
| Admin Booking Calendar | `/admin/bookings/calendar` | Moderator + Admin | ✅ Live |
| Admin Today Operations | `/admin/bookings/today` | Moderator + Admin | ✅ Live |
| Admin Booking Detail | `/admin/bookings/:id` | Moderator + Admin | ✅ Live |
| Admin Customers | `/admin/customers` | Moderator + Admin | ✅ Live |
| Admin Customer Profile | `/admin/customers/:email` | Moderator + Admin | ✅ Live |
| Admin Rooms List | `/admin/rooms` | Moderator + Admin | ✅ Live |
| Admin Room Create | `/admin/rooms/new` | **Admin only** | ✅ Live |
| Admin Room Edit | `/admin/rooms/:id/edit` | **Admin only** | ✅ Live |
| Login | `/login` | Public | ✅ Live |
| Register | `/register` | Public | ✅ Live |
| 404 Not Found | `*` | All | ✅ Live |

> **Screens NOT to design** (not yet implemented):
> - `/admin/reviews` — route does not exist
> - `/admin/messages` — route does not exist
> - Payment/checkout UI — backend bootstrapped, frontend not started
> - Admin confirm booking action — no frontend button yet (backend-only)

### Backend (Laravel 12) — 99% complete

> Per-module test counts moved to [PROJECT_STATUS.md](./PROJECT_STATUS.md) (single source of truth — Mar 31 baseline 1047/2875; re-verification required after Apr–May AI proposal lifecycle, OPS-004, CONC-005/006, AUTH-004, and Stripe webhook idempotency batches).

| Module                                                | Status               |
| ----------------------------------------------------- | -------------------- |
| Authentication (Bearer + HttpOnly cookie, 2FA-ready)  | ✅ Complete          |
| Sanctum hardening (atomic refresh, fingerprint binding, fence-post unification, `findToken` Bearer lookup F-32) | ✅ Complete (Apr 25–28) |
| Email Verification (OTP — race-hardened AUTH-004)     | ✅ Complete          |
| Booking system (create, cancel, soft delete, audit)   | ✅ Complete          |
| Booking state-machine invariants                      | ✅ Complete (Apr 22) |
| Booking payment-hold on creation + pending-limit      | ✅ Complete (Apr 22) |
| Booking immutable actor snapshots (booking + audit log) | ✅ Complete (May 1) |
| `no_overlapping_bookings` exclusion constraint + pre-deploy assertion | ✅ Complete (May 1) |
| Stay-domain cancellation propagation (OPS-004)        | ✅ Complete (May 2)  |
| Deposit FSM lifecycle + null-user reconciliation (CONC-005/006) | ✅ Complete (May 2) |
| Restore path integrity (transaction + FOR UPDATE + cache invalidation) | ✅ Complete |
| Booking email notifications + branded templates       | ✅ Complete          |
| Stripe/Cashier integration + payment-hold + durable refund-event idempotency (TOCTOU eliminated) | ✅ Backend complete; checkout UI pending |
| AI Harness Phases 0–4 (7 endpoints, 7-layer safety pipeline, kill switch, canary routing, eval framework, proposal confirmation) | ✅ Complete |
| AI Harness hardening (durable proposal lifecycle, drift detection, proposer binding, HMAC audit, PII hard-block, AI-001 prompt-injection defense, batch-8 kill-switch hardening + E2E smoke gate) | ✅ Complete (May 2) |
| Backend i18n (en + vi)                                | ✅ Complete          |
| Room management (optimistic locking, status, image_url, 45 seeded rooms across 5 branches) | ✅ Complete |
| RBAC (3 roles: user / moderator / admin) + RBAC-001 contact-message admin lockdown | ✅ Complete (Apr 26) |
| Security headers (CSP with pinned Stripe origins, HSTS, XSS)  | ✅ Complete          |
| XSS Protection (HTML Purifier)                        | ✅ Complete          |
| Rate Limiting (multi-tier) + AI proposal `decide` 5/m throttle | ✅ Complete         |
| Redis Caching (event-driven invalidation) + kill switches  | ✅ Complete          |
| Monitoring & Health probes (admin-gated detail per OBS-002) | ✅ Complete (Apr 28) |
| PII redaction across all log channels and Sentry      | ✅ Complete (Apr 27) |
| Optimistic Locking (rooms + locations)                | ✅ Complete          |
| Repository Layer                                      | ✅ Complete          |
| PHPStan/Larastan (Level 5)                            | ✅ 0 errors (no baseline) |
| Admin Audit Log (append-only; actor, IP, metadata, immutable actor snapshot) | ✅ Complete |
| Customer management (admin guest view)                | ✅ Complete          |
| Password complexity enforcement (registration)        | ✅ Complete          |
| Operational domain (stays, room_assignments, service_recovery_cases, readiness, deposit, settlement, escalation) | ✅ Complete |
| Admin booking filters (7 params: check_in, check_out, status, location_id, search) | ✅ Complete |
| Transaction exceptions (SRP hierarchy)                | ✅ Refactored (May 1) |
| **Backend total**                                     | **✅ Production-quality booking core; integrated product not yet production-ready** |

### Frontend (React 19 + TypeScript) — 97% complete

| Phase    | Feature                                                                      | Status      |
| -------- | ---------------------------------------------------------------------------- | ----------- |
| Phase 0  | DashboardPage lazy-loaded, ProtectedRoute, role-based routing                | ✅ Complete |
| Phase 1  | GuestDashboard: booking list, filter tabs, cancel with confirm               | ✅ Complete |
| Phase 2  | SearchCard wired to live API `/v1/locations`, navigate with URL params       | ✅ Complete |
| Phase 3  | AdminDashboard: 3 tabs (Bookings / Trashed / Contacts), lazy fetch           | ✅ Complete |
| Phase 4  | BookingForm: URL param pre-fill, Vietnamese UI, v1 endpoints                 | ✅ Complete |
| Phase 5  | Booking detail panel, admin restore/force-delete, pagination                 | ✅ Complete |
| Quality  | AbortController cleanup, vi.hoisted mocks, no-console ESLint, RoomList tests | ✅ Complete |
| Phase 5+ | Admin panel expansion (AdminLayout, sidebar, room/booking/customer mgmt), RBAC mobile route guard | ✅ Complete |
| Phase 5+ | Moderator SPA access (`AdminRoute.tsx` `minRole` prop), admin booking filters | ✅ Complete |
| Phase 5+ | `ReviewForm.tsx` — star-rating review submission for confirmed past bookings  | ✅ Complete |
| Phase 5+ | Email verification SPA (`EmailVerifyPage.tsx` — 6-digit OTP)                  | ✅ Complete |
| Phase 5+ | LocationDetail boutique redesign (hero gallery + reviews — `e6673dd`)         | ✅ Complete |
| Phase 5+ | LoginPage / RoomList / Footer / TrustBar redesign                             | ✅ Complete |
| Phase 5+ | HeaderMobile hamburger drawer + stronger password validation                  | ✅ Complete |
| Phase 5+ | RoomDiscoveryWidget (AI proposal-confirmation flow)                           | ✅ Complete |
| Phase 5+ | TodayOperations admin view                                                    | ✅ Complete |
| Phase 5+ | Operations API hardening (`4323e90`)                                          | ✅ Complete |
| Phase 5+ | Vite 6.4.2 pin (CVE GHSA-p9ff-h696-f583), axios 1.15+ (CVE GHSA-3p68-rc4w-qgx5), picomatch overrides | ✅ Complete |
| Phase 6  | Stripe checkout UI, frontend i18n, PWA, admin reviews/messages routes        | 🔄 Next     |

**Frontend tests:** see [PROJECT_STATUS.md](./PROJECT_STATUS.md). Mar 31 baseline: 261 tests / 25 files. As of May 5: 39 test files on disk — re-verification required.

### Multi-Location Architecture

- Supports multiple physical properties within a single system (Hue City)
- Each location has an SEO-friendly slug (`/locations/soleil-hue-center`)
- API: `GET /v1/locations`, `GET /v1/locations/{slug}`, `GET /v1/locations/{slug}/availability`

---

## 5. Technology Stack

| Layer          | Technology                                                     |
| -------------- | -------------------------------------------------------------- |
| Frontend       | React 19, TypeScript 5.7, Vite 6, TailwindCSS 3, Vitest 2      |
| Backend        | Laravel 12, PHP 8.2+, Sanctum (custom token columns)           |
| Database       | PostgreSQL 16 (production + tests default), SQLite opt-in      |
| Cache / Queue  | Redis 7                                                        |
| Infrastructure | Docker Compose, GitHub Actions CI                              |
| API Spec       | OpenAPI 3.1 (Redoc interactive docs)                           |
| Monitoring     | Sentry, structured JSON logging, Correlation ID, Health probes |

---

## 6. Design Principles

1. **Mobile-first** — UI built from small screens up; `BottomNav` + `HeaderMobile` on `/` (homepage only); standard `Header` + `Footer` on all other routes; `AdminLayout` (sidebar for admin) on `/admin/*`
2. **Data preservation** — Bookings are never hard-deleted; all critical actions have an audit trail
3. **No double-booking** — Half-open interval `[check_in, check_out)` + PostgreSQL exclusion constraint + pessimistic locking
4. **Defense in depth** — XSS (HTML Purifier), CSRF (sessionStorage token), rate limiting, A+ security headers
5. **Test before deploy** — 4 mandatory CI gates: artisan test + tsc + vitest + docker compose config
6. **Explicit API versioning** — URL path versioning (`/v1/`, `/v2/`), RFC 8594 deprecation headers

---

## 7. Business Goals

| Goal                     | Success Metric                              |
| ------------------------ | ------------------------------------------- |
| Reduce booking time      | < 3 minutes from search to confirmation     |
| Zero double-bookings     | 0 overlapping reservations on the same room |
| Controlled cancellations | Admin processes requests within 24 hours    |
| System uptime            | ≥ 99.5% (SLA target)                        |
| API response time        | < 200ms p95 for booking endpoints           |

---

## 8. Current Scope & Known Limitations

### Out of current scope

- **Online payment checkout UI** — Backend ready as of Apr 22 (payment-hold on creation, durable refund-event idempotency). Frontend checkout session + payment form still pending.
- **Frontend i18n** — Backend i18n complete (47 keys, en + vi); frontend strings still hardcoded Vietnamese.
- **Multi-tenancy** — One installation = one hostel chain; no shared DB across brands.
- **PWA / Offline** — No service worker yet.
- **Admin reviews + messages routes** — Sidebar links exist; routes intentionally not implemented (see "Do NOT design" notes above).

### Confirmed external dependencies

- Redis 7 (caching, distributed rate limiting, AI proposal cache, kill-switch envelope) — Redis auth enforced in non-local environments via dual-layer guard (`1737970`).
- PostgreSQL 16 (`EXCLUDE` exclusion constraint required for production booking overlap protection — assertion gated by pre-deploy hook `92f1ad1`).
- SMTP / Mailer (booking notification + OTP verification emails).
- Stripe (Cashier — payment-hold on creation, signed webhooks, durable refund event idempotency via UNIQUE(`stripe_refund_id`)).
- Anthropic / OpenAI (AI Harness providers — gated by `AI_HARNESS_ENABLED` feature flag with cache-TTL=0 implicit kill switch).

---

## 9. High-Level Roadmap

```
[DONE] Q4-2025  — Core backend: auth, booking, rooms, RBAC, security
[DONE] Q1-2026  — Email templates, caching, locking, monitoring, repo layer
[DONE] Q1-2026  — Frontend Phases 0-4: dashboard, search, admin, booking form
[DONE] Q1-2026  — Audit v1-v4 (179/179 findings resolved), CLAUDE.md governance
[DONE] Q1-2026  — Frontend Phase 5: detail panel, admin actions, pagination
[DONE] Q1-2026  — Phase 5 clean-up: TD-002 (comments EN), ship script, rollup CVE fix
[DONE] Q1-2026  — DevSecOps: Docker/Redis/Caddy hardening, CI gates, Cashier bootstrap
[DONE] Q1-2026  — Backend i18n (47 keys), Stripe webhooks, review purification fix
[DONE] Q1-2026  — Batch 3: HealthService extraction, FormRequests, PHPStan/Larastan, Contact+Review tests
[DONE] Q1-2026  — Batch 4: AbortController cleanup, vi.hoisted auth mocks, no-console ESLint, RoomList tests
[DONE] Q1-2026  — Four-layer operational domain: stays, room_assignments, service_recovery_cases + backfill command
[DONE] Q1-2026  — Operational completion: readiness, classification, deposit, settlement, escalation engine + OperationalDashboardService
[DONE] Q1-2026  — PHPStan Level 5 clean (0 errors, no baseline), Psalm Level 1 (0 blocking)
[DONE] Q1-2026  — Restore path integrity (TOCTOU-safe), admin booking filters, moderator SPA access, ReviewForm
[DONE] Q2-2026  — AI Harness Phases 0–4: 7 endpoints, 7-layer safety pipeline, kill switch, canary routing, eval framework, proposal-confirmation flow (Apr 9–11)
[DONE] Q2-2026  — F-06 proposer-binding (cache-envelope), F-04 deploy-host gate, OpenAPI Spectral contract-lint gate (Apr 13–18)
[DONE] Q2-2026  — Email verification OTP flow (full-stack 6-digit code) + concurrent-booking HTTP 500 fix + tsconfig TS5103 fix (Apr 3–4)
[DONE] Q2-2026  — Booking integrity wave: state-machine invariants, payment-hold, durable Stripe refund-event idempotency (TOCTOU eliminated), immutable actor snapshots, no-overlap constraint hardening, deposit FSM, stay cancellation propagation (Apr 19 → May 1)
[DONE] Q2-2026  — Auth + observability hardening: batch-2 Sanctum, F-32 detectAuthMode, RBAC-001 contact-message lockdown, PII redaction, OBS-001/OBS-002, CSP Stripe origin pinning (Apr 25–28)
[DONE] Q2-2026  — AI Harness hardening (batch-8): durable proposal lifecycle + drift detection + proposer binding, HMAC audit, PII hard-block, AI-001 prompt-injection defense, kill-switch hardening, E2E smoke gate, AUTH-004 OTP race (May 2)
[NEXT] Q2-2026  — Stripe checkout UI (frontend), frontend i18n, PWA, admin reviews + messages routes
[NEXT] Q2-2026  — Deployment pipeline complete (currently 60%) — SSH deploy step, automated health-check rollback
[PLAN] Q3-2026  — Webhook delivery tracking, email delivery tracking, audit log retention, 2FA TOTP issuance, OTA integration
```

---

_See technical details at [docs/README.md](./docs/README.md) — See full backlog at [BACKLOG.md](./BACKLOG.md)_
