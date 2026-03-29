# Legacy Auth Sunset Tracking

Authoritative tracking record for legacy (unversioned `/api/auth/*`) endpoints.
Status and headers verified from `routes/api.php` as of 2026-03-29.

See `docs/API_DEPRECATION.md` for the general deprecation lifecycle policy and
`DeprecatedEndpoint` middleware implementation.

---

## Status Summary

| Status field       | Value                       |
|--------------------|-----------------------------|
| **Status**         | DEPRECATED                  |
| **Deprecated since** | January 2026              |
| **Sunset date**    | July 1, 2026                |
| **RFC 8594 headers active** | Yes — `Deprecation`, `Sunset`, `Link`, `X-Deprecation-Notice` on all listed routes |
| **Owner**          | TBD — requires owner confirmation |
| **Removal plan**   | TBD — confirmed sunset date is July 1, 2026; removal execution owner not confirmed in repo evidence |

---

## Affected Endpoints

### Public (unauthenticated)

| Method | Legacy route           | Status      | Successor route             | Notes                                      |
|--------|------------------------|-------------|-----------------------------|--------------------------------------------|
| POST   | `/api/auth/register`   | DEPRECATED  | `/api/v2/auth/register`     | Successor returns 501 in all environments (v2 not yet shipped). No active replacement exists yet. |
| POST   | `/api/auth/login`      | DEPRECATED  | `/api/auth/login-v2`        | Replacement is live and active.            |

### Protected (requires valid token via `check_token_valid` middleware)

| Method | Legacy route           | Status      | Successor route             | Notes                                      |
|--------|------------------------|-------------|-----------------------------|--------------------------------------------|
| POST   | `/api/auth/logout`     | DEPRECATED  | `/api/auth/logout-v2`       | Delegates to `TokenAuthController` (same underlying handler). |
| POST   | `/api/auth/refresh`    | DEPRECATED  | `/api/auth/refresh-v2`      | Delegates to `TokenAuthController` (same underlying handler). |
| GET    | `/api/auth/me`         | DEPRECATED  | `/api/auth/me-v2`           | Legacy handler (`AuthController::me`) returns simpler response format without token metadata. |

---

## Response Format Differences (legacy vs. current)

`GET /api/auth/me` (legacy `AuthController`) returns a simpler user object without token
metadata. `GET /api/auth/me-v2` (`TokenAuthController`) returns the same user data plus token
metadata fields. Clients depending on exact response shape should verify before migrating.

All other legacy routes (`logout`, `refresh`) delegate to the same `TokenAuthController`
handlers as their v2 successors — response format is identical.

---

## Register Gap

`POST /api/auth/register` points to `/api/v2/auth/register` as successor, but v2 is not yet
shipped. This is a **known gap**: the legacy register endpoint has no active replacement in
production. This must be resolved before the July 1, 2026 sunset date.

**Required action before sunset:** Either ship `/api/v1/auth/register` or `/api/v2/auth/register`,
or confirm that all clients have migrated off register entirely. Owner: TBD.

---

## Governance Fields

| Field                 | Value                                                              |
|-----------------------|--------------------------------------------------------------------|
| `status`              | DEPRECATED                                                         |
| `deprecated_since`    | January 2026                                                       |
| `sunset_date`         | 2026-07-01                                                         |
| `removal_after`       | 2026-07-01 (confirmed via `deprecated` middleware parameter)       |
| `owner`               | TBD — not determinable from repo evidence; requires confirmation   |
| `scope`               | `/api/auth/register`, `/api/auth/login`, `/api/auth/logout`, `/api/auth/refresh`, `/api/auth/me` |
| `replacement_path`    | See Affected Endpoints table above                                 |
| `headers_active`      | Yes — enforced by `DeprecatedEndpoint` middleware on all routes    |
| `register_gap`        | OPEN — successor `/api/v2/auth/register` returns 501; must resolve before sunset |
