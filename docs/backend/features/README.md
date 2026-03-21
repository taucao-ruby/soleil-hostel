# 📚 Feature Documentation Index

> Detailed documentation for each feature module

## Features

| Feature            | Status | Tests | Documentation                                               |
| ------------------ | ------ | ----- | ----------------------------------------------------------- |
| Authentication     | ✅     | 26    | [AUTHENTICATION.md](./AUTHENTICATION.md)                    |
| Email Verification | ✅     | 14    | [AUTHENTICATION.md](./AUTHENTICATION.md#email-verification) |
| Booking System     | ✅     | 60    | [BOOKING.md](./BOOKING.md)                                  |
| Email Templates    | ✅     | 13    | [EMAIL_TEMPLATES.md](./EMAIL_TEMPLATES.md)                  |
| Room Management    | ✅     | 24    | [ROOMS.md](./ROOMS.md)                                      |
| Reviews            | ✅     | 48    | [REVIEWS.md](./REVIEWS.md)                                  |
| RBAC               | ✅     | 47    | [RBAC.md](./RBAC.md)                                        |
| Redis Caching      | ✅     | 6     | [CACHING.md](./CACHING.md)                                  |

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
- 7 authorization gates
- Middleware for route protection

### ⚡ Caching

- Redis-based with event-driven invalidation
- Tag-based cache management via `HasCacheTagSupport` trait
- Fallback to database cache
- Uses Laravel's native `Cache::supportsTags()` detection

### ⭐ Reviews

- Auto-purify content via `Purifiable` trait
- XSS protection with HTML Purifier
- Moderation workflow (approved/pending)
