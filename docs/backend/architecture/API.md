# 🌐 API Reference

> Complete API endpoint documentation for Soleil Hostel

## Base URL

```
Production:  https://api.soleil-hostel.com/api
Development: http://localhost:8000/api
```

---

## API Versioning

### Current Versions

| Version    | Status         | Base Path  | Description                          |
| ---------- | -------------- | ---------- | ------------------------------------ |
| **v1**     | ✅ Stable      | `/api/v1/` | Current production version           |
| **v2**     | 🚧 Development | `/api/v2/` | Returns 501 Not Implemented          |
| **Legacy** | ⚠️ Deprecated  | `/api/`    | Proxy to v1 with deprecation headers |

### Versioned Endpoints

All bookings and rooms endpoints are now versioned:

```
✅ /api/v1/bookings/*      - Recommended (stable)
✅ /api/v1/rooms/*         - Recommended (stable)
✅ /api/v1/admin/bookings/* - Recommended (stable)

⚠️ /api/bookings/*         - Deprecated (sunset: July 1, 2026)
⚠️ /api/rooms/*            - Deprecated (sunset: July 1, 2026)

🚧 /api/v2/*               - Under development (returns 501)
```

### Legacy Endpoints (Deprecated)

Legacy endpoints at `/api/bookings` and `/api/rooms` remain functional for backward compatibility but include deprecation headers:

```http
HTTP/1.1 200 OK
Deprecation: Sat, 17 Jan 2026 00:00:00 GMT
Sunset: Sat, 01 Jul 2026 00:00:00 GMT
Link: </api/v1/bookings>; rel="successor-version"
X-Deprecation-Notice: This endpoint is deprecated and will be removed on 2026-07-01. Use /api/v1/bookings instead.
```

### Migration Guide

To migrate from legacy to v1 endpoints, simply add `/v1` prefix:

| Legacy (Deprecated)  | V1 (Recommended)        |
| -------------------- | ----------------------- |
| `GET /api/bookings`  | `GET /api/v1/bookings`  |
| `POST /api/bookings` | `POST /api/v1/bookings` |
| `GET /api/rooms`     | `GET /api/v1/rooms`     |
| `POST /api/rooms`    | `POST /api/v1/rooms`    |

---

## Authentication

### Public Endpoints

| Method | Endpoint               | Description               | Rate Limit |
| ------ | ---------------------- | ------------------------- | ---------- |
| POST   | `/auth/register`       | Register new user         | 5/min      |
| POST   | `/auth/login`          | Login (Bearer token)      | 5/min      |
| POST   | `/auth/login-v2`       | Login with token metadata | 5/min      |
| POST   | `/auth/login-httponly` | Login (HttpOnly cookie)   | 5/min      |
| GET    | `/auth/csrf-token`     | Get CSRF token            | -          |

### Protected Endpoints (Bearer Token)

| Method | Endpoint              | Description        | Auth     |
| ------ | --------------------- | ------------------ | -------- |
| POST   | `/auth/logout`        | Logout             | Required |
| POST   | `/auth/refresh`       | Refresh token      | Required |
| GET    | `/auth/me`            | Get current user   | Required |
| POST   | `/auth/logout-v2`     | Logout (v2)        | Required |
| POST   | `/auth/refresh-v2`    | Refresh (v2)       | Required |
| POST   | `/auth/logout-all-v2` | Logout all devices | Required |
| GET    | `/auth/me-v2`         | Get user (v2)      | Required |

### Protected Endpoints (HttpOnly Cookie)

| Method | Endpoint                 | Description   | Auth   |
| ------ | ------------------------ | ------------- | ------ |
| POST   | `/auth/refresh-httponly` | Refresh token | Cookie |
| POST   | `/auth/logout-httponly`  | Logout        | Cookie |
| GET    | `/auth/me-httponly`      | Get user      | Cookie |

### Unified Auth Endpoints (Mode-Agnostic)

Auto-detects Bearer token or HttpOnly cookie. Recommended for new clients.

| Method | Endpoint                   | Description        | Auth     |
| ------ | -------------------------- | ------------------ | -------- |
| GET    | `/auth/unified/me`         | Get current user   | Required |
| POST   | `/auth/unified/logout`     | Logout session     | Required |
| POST   | `/auth/unified/logout-all` | Logout all devices | Required |

### Email Verification

| Method | Endpoint                           | Description            | Auth     |
| ------ | ---------------------------------- | ---------------------- | -------- |
| GET    | `/email/verify`                    | Verification notice    | Required |
| GET    | `/email/verify/{id}/{hash}`        | Verify email (signed)  | Required |
| POST   | `/email/verification-notification` | Resend verification    | Required |
| GET    | `/email/verification-status`       | Check status           | Required |

---

## Locations

### Public Endpoints

| Method | Endpoint                            | Description           | Rate Limit |
| ------ | ----------------------------------- | --------------------- | ---------- |
| GET    | `/v1/locations`                     | List all locations    | -          |
| GET    | `/v1/locations/{slug}`              | Get location by slug  | -          |
| GET    | `/v1/locations/{slug}/availability` | Location availability | -          |

---

## Rooms

### Public Endpoints

| Method | Endpoint         | Description    | Rate Limit |
| ------ | ---------------- | -------------- | ---------- |
| GET    | `/v1/rooms`      | List all rooms | -          |
| GET    | `/v1/rooms/{id}` | Get room by ID | -          |

### Admin Endpoints

| Method | Endpoint         | Description | Auth  |
| ------ | ---------------- | ----------- | ----- |
| POST   | `/v1/rooms`      | Create room | Admin |
| PUT    | `/v1/rooms/{id}` | Update room | Admin |
| DELETE | `/v1/rooms/{id}` | Delete room | Admin |

### Room Request/Response

```json
// Request (Create/Update)
{
  "name": "Deluxe Suite",
  "description": "Ocean view room",
  "price": 150.00,
  "max_guests": 4,
  "status": "available",
  "lock_version": 1  // Required for update (optimistic locking)
}

// Response
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Deluxe Suite",
    "price": "150.00",
    "max_guests": 4,
    "status": "available",
    "lock_version": 2
  }
}
```

---

## Bookings

### User Endpoints

| Method | Endpoint            | Description        | Rate Limit |
| ------ | ------------------- | ------------------ | ---------- |
| GET    | `/v1/bookings`      | List user bookings | -          |
| POST   | `/v1/bookings`      | Create booking     | 10/min     |
| GET    | `/v1/bookings/{id}` | Get booking        | -          |
| PUT    | `/v1/bookings/{id}` | Update booking     | 10/min     |
| DELETE | `/v1/bookings/{id}` | Cancel booking     | 10/min     |

### Booking Status Endpoints

| Method | Endpoint                      | Description     | Rate Limit | Auth  |
| ------ | ----------------------------- | --------------- | ---------- | ----- |
| POST   | `/v1/bookings/{id}/confirm`   | Confirm booking | 10/min     | Admin |
| POST   | `/v1/bookings/{id}/cancel`    | Cancel booking  | 10/min     | User  |

### Admin Endpoints

| Method | Endpoint                          | Description            | Auth  |
| ------ | --------------------------------- | ---------------------- | ----- |
| GET    | `/v1/admin/bookings`              | All bookings + trashed | Admin |
| GET    | `/v1/admin/bookings/trashed`      | Trashed bookings       | Admin |
| GET    | `/v1/admin/bookings/trashed/{id}` | View trashed booking   | Admin |
| POST   | `/v1/admin/bookings/{id}/restore` | Restore booking        | Admin |
| POST   | `/v1/admin/bookings/restore-bulk` | Bulk restore           | Admin |
| DELETE | `/v1/admin/bookings/{id}/force`   | Permanent delete       | Admin |

### Booking Request/Response

```json
// Request (Create)
{
  "room_id": 1,
  "check_in": "2025-01-15",
  "check_out": "2025-01-20",
  "guest_name": "John Doe",
  "guest_email": "john@example.com"
}

// Response
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 1,
    "room_id": 1,
    "check_in": "2025-01-15",
    "check_out": "2025-01-20",
    "status": "confirmed",
    "guest_name": "John Doe",
    "room": { ... }
  }
}
```

---

## Contact

### Public Endpoint

| Method | Endpoint   | Description         | Rate Limit |
| ------ | ---------- | ------------------- | ---------- |
| POST   | `/contact` | Submit contact form | 3/min      |

```json
// Request
{
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Booking Question",
  "message": "I have a question about..."
}
```

### Admin Contact Message Endpoints

| Method | Endpoint                               | Description       | Auth  |
| ------ | -------------------------------------- | ----------------- | ----- |
| GET    | `/v1/admin/contact-messages`           | List all messages | Admin |
| PATCH  | `/v1/admin/contact-messages/{id}/read` | Mark as read      | Admin |

---

## Health Check

### Public Health Endpoints

| Method | Endpoint        | Description                           |
| ------ | --------------- | ------------------------------------- |
| GET    | `/health`       | Basic health check                    |
| GET    | `/health/live`  | Liveness probe (is process alive?)    |
| GET    | `/health/ready` | Readiness probe (can accept traffic?) |
| GET    | `/ping`         | Simple ping                           |

### Admin Health Endpoints

| Method | Endpoint           | Description              | Auth  |
| ------ | ------------------ | ------------------------ | ----- |
| GET    | `/health/detailed` | Full system health       | Admin |
| GET    | `/health/full`     | Alias for detailed       | Admin |
| GET    | `/health/db`       | Database health          | Admin |
| GET    | `/health/cache`    | Cache (Redis) health     | Admin |
| GET    | `/health/queue`    | Queue worker health      | Admin |

### Health Response

```json
{
  "status": "healthy",
  "timestamp": "2025-12-19T10:00:00+00:00",
  "services": {
    "database": { "status": "up" },
    "redis": { "status": "up" },
    "memory": { "usage_mb": 45.2, "limit_mb": 512 }
  }
}
```

---

## Security

| Method | Endpoint                | Description             |
| ------ | ----------------------- | ----------------------- |
| POST   | `/csp-violation-report` | CSP violation reporting |

---

## Error Responses

### 401 Unauthorized

```json
{
  "success": false,
  "message": "Token expired or invalid"
}
```

### 403 Forbidden

```json
{
  "success": false,
  "message": "Insufficient permissions"
}
```

### 409 Conflict (Optimistic Lock)

```json
{
  "success": false,
  "message": "Resource modified by another user. Please refresh.",
  "expected_version": 5,
  "actual_version": 6
}
```

### 422 Validation Error

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "check_in": ["The check in field is required."]
  }
}
```

### 429 Too Many Requests

```json
{
  "success": false,
  "message": "Too many requests. Try again in 60 seconds."
}
```

---

## Middleware Stack

### Public Routes

```
api → throttle:api
```

### Protected Routes

```
api → check_token_valid
    ↓
check_httponly_token (for cookie auth)
```

### Admin Routes

```
api → check_token_valid → role:admin
```

---

## Controllers

| Controller                         | Responsibility                          |
| ---------------------------------- | --------------------------------------- |
| `AuthController`                   | Legacy Bearer token auth                |
| `Auth\AuthController`              | Token expiration auth (v2)              |
| `Auth\HttpOnlyTokenController`     | HttpOnly cookie auth                    |
| `Auth\UnifiedAuthController`       | Mode-agnostic auth (Bearer/Cookie)      |
| `Auth\EmailVerificationController` | Email verification flows                |
| `LocationController`               | Location listing and availability       |
| `RoomController`                   | Room CRUD with optimistic locking       |
| `BookingController`                | Booking CRUD with pessimistic lock      |
| `AdminBookingController`           | Soft delete management                  |
| `ContactController`                | Contact form + admin message management |
| `HealthController`                 | System health monitoring                |
| `CspViolationReportController`     | CSP violation logging                   |
