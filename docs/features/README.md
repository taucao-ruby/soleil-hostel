# 📚 Feature Documentation Index

> Detailed documentation for each feature module

## Features

| Feature         | Status | Tests | Documentation                            |
| --------------- | ------ | ----- | ---------------------------------------- |
| Authentication  | ✅     | 26    | [AUTHENTICATION.md](./AUTHENTICATION.md) |
| Booking System  | ✅     | 60    | [BOOKING.md](./BOOKING.md)               |
| Room Management | ✅     | 24    | [ROOMS.md](./ROOMS.md)                   |
| RBAC            | ✅     | 47    | [RBAC.md](./RBAC.md)                     |
| Redis Caching   | ✅     | 6     | [CACHING.md](./CACHING.md)               |

---

## Feature Highlights

### 🔐 Authentication

- Dual mode: Bearer Token + HttpOnly Cookie
- Token expiration & rotation
- Multi-device support
- Suspicious activity detection

### 📅 Booking System

- **Pessimistic locking** prevents double-booking
- Soft deletes with audit trail
- Half-open interval logic
- Admin restore/force-delete

### 🏨 Room Management

- **Optimistic locking** prevents lost updates
- Real-time availability cache
- Status management (available/occupied/maintenance)

### 👥 RBAC

- Type-safe enum: USER, MODERATOR, ADMIN
- Helper methods: `isAdmin()`, `isAtLeast()`
- 6 authorization gates
- Middleware for route protection

### ⚡ Caching

- Redis-based with event-driven invalidation
- Tag-based cache management
- Fallback to database cache
