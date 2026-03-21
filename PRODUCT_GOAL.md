# PRODUCT_GOAL.md — Soleil Hostel

> **Product goals and strategic direction**
> Last updated: 2026-03-20

---

## 1. Vision

> **"A simple, transparent, and reliable hostel booking experience for travelers in Hue."**

Soleil Hostel is an in-house booking platform for the **Soleil** hostel chain in Hue City, Vietnam. The system covers the full lifecycle of a booking — from room search, reservation, and management through check-out — on a single web interface that works well on both mobile and desktop.

---

## 2. Target Users

| Group         | Description                                       | Core Need                                                    |
| ------------- | ------------------------------------------------- | ------------------------------------------------------------ |
| **Guest**     | Domestic and international travelers visiting Hue | Find rooms, book quickly, view and cancel their own bookings |
| **Moderator** | Staff at Soleil properties                        | View booking lists, handle contact requests                  |
| **Admin**     | System and operations managers                    | Manage all bookings, restore, force-delete, reporting        |

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

### Backend (Laravel 12) — 99% complete

| Module                                               | Status            | Tests         |
| ---------------------------------------------------- | ----------------- | ------------- |
| Authentication (Bearer + HttpOnly cookie, 2FA-ready) | ✅ Complete       | 44 tests      |
| Booking system (create, cancel, soft delete, audit)  | ✅ Complete       | 60 tests      |
| Booking email notifications                          | ✅ Complete       | 23 tests      |
| Branded email templates                              | ✅ Complete       | 13 tests      |
| Stripe/Cashier integration                           | ✅ Bootstrap      | 14 tests      |
| Backend i18n (en + vi)                               | ✅ Complete       | 9 tests       |
| Room management (optimistic locking, status)         | ✅ Complete       | 151 tests     |
| RBAC (3 roles: user / moderator / admin)             | ✅ Complete       | 63 tests      |
| Security headers (CSP, HSTS, XSS)                    | ✅ Complete       | 14 tests      |
| XSS Protection (HTML Purifier)                       | ✅ Complete       | 48 tests      |
| Rate Limiting (multi-tier)                           | ✅ Complete       | 29 tests      |
| Redis Caching (event-driven invalidation)            | ✅ Complete       | 6 tests       |
| Monitoring & Health probes                           | ✅ Complete       | 30 tests      |
| Optimistic Locking (rooms + locations)               | ✅ Complete       | 24 tests      |
| Repository Layer                                     | ✅ Complete       | 53 tests      |
| Email Verification                                   | ✅ Complete       | 26 tests      |
| PHPStan/Larastan (Level 5)                           | ✅ Installed      | Baseline 151  |
| Admin Audit Log (append-only; actor, IP, metadata)  | ✅ Complete       | —             |
| Customer management (admin guest view)               | ✅ Complete       | —             |
| Password complexity enforcement (registration)       | ✅ Complete       | —             |
| Operational domain (stays, room_assignments, service_recovery_cases) | ✅ Complete | 35 tests |
| **Backend total**                                    | **✅ 21 systems** | **989 tests** |

### Frontend (React 19 + TypeScript) — 96% complete

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
| Phase 5+ | Payment UI, i18n, PWA                                                        | 🔄 Next     |

**Frontend tests:** 226 tests across 21 suites (verified March 11, 2026)

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

1. **Mobile-first** — UI built from small screens up; BottomNav for mobile, Header/Footer for desktop
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

- **Online payment checkout UI** — Stripe Cashier bootstrapped + webhooks implemented; checkout session + frontend payment form pending
- **Frontend i18n** — Backend i18n complete (47 keys, en + vi); frontend strings still hardcoded Vietnamese
- **Multi-tenancy** — One installation = one hostel chain; no shared DB across brands
- **PWA / Offline** — No service worker yet

### Confirmed external dependencies

- Redis 7 (caching + distributed rate limiting)
- PostgreSQL 16 (exclusion constraint required for production)
- SMTP/Mailer (booking notification emails)

---

## 9. High-Level Roadmap

```
[DONE] Q4-2025  — Core backend: auth, booking, rooms, RBAC, security
[DONE] Q1-2026  — Email templates, caching, locking, monitoring, repo layer
[DONE] Q1-2026  — Frontend Phases 0-4: dashboard, search, admin, booking form
[DONE] Q1-2026  — Audit v1-v4 (20/20 findings resolved), CLAUDE.md governance
[DONE] Q1-2026  — Frontend Phase 5: detail panel, admin actions, pagination
[DONE] Q1-2026  — Phase 5 clean-up: TD-002 (comments EN), ship script, rollup CVE fix
[DONE] Q1-2026  — DevSecOps: Docker/Redis/Caddy hardening, CI gates, Cashier bootstrap
[DONE] Q1-2026  — Backend i18n (47 keys), Stripe webhooks, review purification fix
[DONE] Q1-2026  — Batch 3: HealthService extraction, FormRequests, PHPStan/Larastan, Contact+Review tests
[DONE] Q1-2026  — Batch 4: AbortController cleanup, vi.hoisted auth mocks, no-console ESLint, RoomList tests
[DONE] Q1-2026  — Four-layer operational domain: stays, room_assignments, service_recovery_cases + backfill command
[NEXT] Q2-2026  — Payment checkout UI (Stripe/VNPay), frontend i18n, PWA
[NEXT] Q2-2026  — Deployment pipeline complete (currently 60%)
[PLAN] Q3-2026  — Webhook system, email delivery tracking, audit log retention
```

---

_See technical details at [docs/README.md](./docs/README.md) — See full backlog at [BACKLOG.md](./BACKLOG.md)_
