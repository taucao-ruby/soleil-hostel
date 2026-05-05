# 📚 Feature Documentation Index

> Detailed documentation for each feature module. Per-feature test counts moved to [PROJECT_STATUS.md](../../../PROJECT_STATUS.md) (single source of truth — historical per-area numbers below were captured Mar 2026 and are no longer in lockstep with the suite).

## Features

| Feature            | Status | Documentation                                               |
| ------------------ | ------ | ----------------------------------------------------------- |
| Authentication     | ✅     | [AUTHENTICATION.md](./AUTHENTICATION.md)                    |
| Email Verification (OTP) | ✅ | [AUTHENTICATION.md](./AUTHENTICATION.md#email-verification) |
| Booking System     | ✅     | [BOOKING.md](./BOOKING.md)                                  |
| Booking Cancellation & Refund | ✅ | [../architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md](../architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md) |
| Email Templates    | ✅     | [EMAIL_TEMPLATES.md](./EMAIL_TEMPLATES.md)                  |
| Room Management    | ✅     | [ROOMS.md](./ROOMS.md)                                      |
| Reviews            | ✅     | [REVIEWS.md](./REVIEWS.md)                                  |
| RBAC               | ✅     | [RBAC.md](./RBAC.md) — see also [PERMISSION_MATRIX.md](../../PERMISSION_MATRIX.md) |
| Redis Caching      | ✅     | [CACHING.md](./CACHING.md)                                  |
| Optimistic Locking | ✅     | [OPTIMISTIC_LOCKING.md](./OPTIMISTIC_LOCKING.md)            |
| Health Check       | ✅     | [HEALTH_CHECK.md](./HEALTH_CHECK.md) (admin-gated detail per OBS-002) |
| AI Harness (Ph 0–4)| ✅     | [../../HARNESS_ENGINEERING.md](../../HARNESS_ENGINEERING.md), [THREAT_MODEL_AI](../../THREAT_MODEL_AI.md), [EVAL_STRATEGY](../../EVAL_STRATEGY.md) |
| Operational Stays  | ✅     | [../../DOMAIN_LAYERS.md](../../DOMAIN_LAYERS.md) (stays, room_assignments, service_recovery_cases) |
| Stripe Payments    | ✅ Bootstrap | Cashier + signed-webhook idempotency; checkout UI pending |

---

## Feature Highlights

### 🔐 Authentication

- Dual mode: Bearer Token + HttpOnly Cookie
- Token expiration & rotation
- Multi-device support
- Suspicious activity detection
- **Form Request validation** via `Auth/RegisterRequest` and `Auth/LoginRequest`
- **Email verification** required for protected routes

### 📧 Email Verification

- User model implements `MustVerifyEmail` interface
- `verified` middleware blocks unverified users from booking routes
- Signed URLs with expiration for security
- Uses Laravel's default `VerifyEmail` notification (no custom Mailables)
- Re-verification required when email changes

### 📅 Booking System

- **Pessimistic locking** prevents double-booking
- Soft deletes with audit trail
- Half-open interval logic
- Admin restore/force-delete

### 📧 Email Templates

- Branded Markdown templates for all booking notifications
- Custom `soleil.css` theme with brand colors
- Configurable via `config/email-branding.php`
- XSS protection with `e()` helper
- 13 unit tests for template rendering

### 🏨 Room Management

- **Optimistic locking** prevents lost updates
- Real-time availability cache
- Status management (available/occupied/maintenance)

### 👥 RBAC

- Type-safe enum: USER, MODERATOR, ADMIN
- Helper methods: `isAdmin()`, `isAtLeast()`
- 7 authorization gates (single source: [`docs/PERMISSION_MATRIX.md`](../../PERMISSION_MATRIX.md))
- Middleware for route protection (`role:admin`, `role:moderator`)
- Defense-in-depth — middleware + Gate::authorize at controller layer

### ⚡ Caching

- Redis-based with event-driven invalidation
- Tag-based cache management via `HasCacheTagSupport` trait
- Fallback to database cache
- Uses Laravel's native `Cache::supportsTags()` detection

### ⭐ Reviews

- Auto-purify content via `Purifiable` trait
- XSS protection with HTML Purifier
- Moderation workflow (approved/pending)
