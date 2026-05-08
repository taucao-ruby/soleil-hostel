# 👥 Role-Based Access Control (RBAC)

> Type-safe role system with backed enum, gates, middleware, and **defense-in-depth** (route + gate + policy)
>
> **Last Updated:** May 8, 2026

## Overview

Soleil Hostel uses a **backed string enum** for user roles with Laravel Gates for authorization. This provides **compile-time type safety**, IDE autocomplete, and eliminates string literals across the codebase.

> **Source of truth for the actor-to-permission mapping:** [`docs/PERMISSION_MATRIX.md`](../../PERMISSION_MATRIX.md). This file describes the implementation; the matrix describes the enforced surface.

### Defense-in-depth (Mar 10 hardening, ongoing)

Sensitive endpoints are protected at **three** layers — route middleware, controller-level Gate, and resource Policy — so that a regression at any one layer cannot silently expose the resource. All three must agree.

```
Request → role:moderator middleware → Gate::authorize('view-all-bookings') → BookingPolicy::viewAny → Controller
```

If any layer denies, the request 403s. Tests assert each layer independently (moderator-denial tests for admin-only writes; admin-allow tests for moderator-readable resources).

### Apr–May 2026 changes

- **RBAC-001 contact-message admin lockdown** (`04c7d63`, 2026-04-26): `App\Policies\ContactMessagePolicy` registered; routes `/api/v1/admin/contact-messages*` are now admin-only. Closed the moderator path that previously read contact submissions.
- **Immutable actor snapshot** on `bookings` and `admin_audit_logs` (May 1, `048e40b` / `2026_05_01_000002` / `2026_05_01_000003`): every cancellation and audit-log row carries `actor_email` / `actor_role` / `actor_display_name` so attribution survives user deletion.
- **`admin_audit_logs` append-only enforcement** (`2026_03_12_000001`): the application DB user must NOT be granted `UPDATE`/`DELETE` on this table in production.
- **Pre-flight DEPLOY_HOST gate** (F-04, Apr 17): role-aware deploy step refuses to run without an explicit deploy host.

---

## Role Hierarchy

| Role      | Level | Value         | Permissions                                            |
| --------- | ----- | ------------- | ------------------------------------------------------ |
| USER      | 1     | `'user'`      | Own bookings only                                      |
| MODERATOR | 2     | `'moderator'` | + View all bookings (ROUTE-ACCESSIBLE: route `role:moderator` + gate `view-all-bookings`; read-only, A7/A8 tested, A9 FOLLOW-UP REQUIRED), moderate content via contact messages (ROUTE-ACCESSIBLE: route `role:moderator`; gate `moderate-content` invocation in ContactController UNVERIFIED), approve reviews (UNVERIFIED: no moderator-specific route or gate found in v1.php) |
| ADMIN     | 3     | `'admin'`     | + Manage users, rooms, restore bookings, system config |

**Hierarchy-aware**: Higher roles inherit lower role permissions.

---

## Implementation

### UserRole Enum

```php
// app/Enums/UserRole.php
declare(strict_types=1);

enum UserRole: string
{
    case USER = 'user';
    case MODERATOR = 'moderator';
    case ADMIN = 'admin';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::USER;
    }
}
```

### User Model Helpers

```php
// app/Models/User.php

// SECURITY: All role checks MUST use these methods.
// NEVER compare $user->role to strings directly.

public function isAdmin(): bool
{
    return $this->role === UserRole::ADMIN;
}

public function isModerator(): bool
{
    return $this->isAtLeast(UserRole::MODERATOR); // Includes ADMIN
}

public function isUser(): bool
{
    return $this->role === UserRole::USER;
}

public function hasRole(UserRole $role): bool
{
    return $this->role === $role;
}

public function hasAnyRole(array $roles): bool
{
    return in_array($this->role, $roles, true);
}

public function isAtLeast(UserRole $role): bool
{
    static $levels = [
        'user' => 1,
        'moderator' => 2,
        'admin' => 3,
    ];
    return $levels[$this->role->value] >= $levels[$role->value];
}
```

---

## Authorization Gates

7 gates defined in `AuthServiceProvider.php`. See [docs/PERMISSION_MATRIX.md](../../PERMISSION_MATRIX.md) for invocation status and which gates are CURRENT vs LATENT-UNUSED.

| Gate | Check | Level |
|------|-------|-------|
| `admin` | `$user->isAdmin()` | EXACT-MATCH |
| `moderator` | `$user->isModerator()` | HIERARCHY |
| `manage-users` | `$user->isAdmin()` | EXACT-MATCH |
| `moderate-content` | `$user->isModerator()` | HIERARCHY |
| `view-all-bookings` | `$user->isModerator()` | HIERARCHY |
| `manage-rooms` | `$user->isAdmin()` | EXACT-MATCH |
| `view-queue-monitoring` | `$user->isAdmin()` | EXACT-MATCH |

### Usage in Controllers

```php
// Gate check (throws 403 if denied)
Gate::authorize('admin');

// Policy check on resource
$this->authorize('update', $booking);
```

---

## Middleware

### EnsureUserHasRole

```php
// app/Http/Middleware/EnsureUserHasRole.php

public function handle(Request $request, Closure $next, string $role): Response
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    $requiredRole = UserRole::tryFrom($role);

    if ($requiredRole === null) {
        // Invalid role parameter - server config error
        report(new \InvalidArgumentException("Invalid role: {$role}"));
        return response()->json(['message' => 'Server configuration error.'], 500);
    }

    if (!$user->isAtLeast($requiredRole)) {
        return response()->json([
            'message' => 'Forbidden. Insufficient permissions.'
        ], 403);
    }

    return $next($request);
}
```

### Route Registration

Route middleware enforces role requirements. See `routes/api/v1.php` for canonical route definitions.

> **For the full actor-to-permission mapping, see [docs/PERMISSION_MATRIX.md](../../PERMISSION_MATRIX.md).**
> Do not redefine permission mappings in this file.

---

## Database

### Migration

```sql
-- PostgreSQL ENUM type
CREATE TYPE user_role AS ENUM ('user', 'moderator', 'admin');

ALTER TABLE users ADD COLUMN role user_role NOT NULL DEFAULT 'user';
```

### Eloquent Cast

```php
// app/Models/User.php
protected $casts = [
    'role' => UserRole::class,
];
```

---

## Factories

```php
// database/factories/UserFactory.php
public function admin(): self
{
    return $this->state(['role' => UserRole::ADMIN]);
}

public function moderator(): self
{
    return $this->state(['role' => UserRole::MODERATOR]);
}

// Usage
$admin = User::factory()->admin()->create();
$moderator = User::factory()->moderator()->create();
$user = User::factory()->create(); // default: USER
```

---

## API Responses

### 403 Forbidden

```json
{
  "message": "Insufficient permissions"
}
```

### 401 Unauthorized

```json
{
  "message": "Unauthenticated"
}
```

---

## Tests

```bash
# All RBAC tests
php artisan test tests/Feature/Authorization/
php artisan test tests/Feature/Middleware/EnsureUserHasRoleTest.php
php artisan test tests/Unit/Enums/UserRoleTest.php
php artisan test tests/Unit/Models/UserRoleHelpersTest.php
```

> Per-suite test counts moved to [PROJECT_STATUS.md](../../../PROJECT_STATUS.md). Apr–May added moderator-denial tests for AI proposal `decide`, `ContactMessagePolicy` admin-only tests (RBAC-001), and `AdminAuditService` actor-snapshot integration tests.

## AdminAuditService

`AdminAuditService` writes append-only rows to `admin_audit_logs` for every sensitive admin action (force-delete, restore-bulk, contact moderation, AI proposal decide). It is the single chokepoint — controllers do not write to the table directly. Each row carries:

- `actor_id` (FK→`users` SET NULL)
- **immutable actor snapshot**: `actor_email`, `actor_role`, `actor_display_name` (added 2026-05-01 — survives user deletion)
- `action`, `resource_type`, `resource_id`, `metadata` JSON, `ip_address`, `created_at`

> **DB-grant invariant:** the application DB user MUST NOT have `UPDATE` or `DELETE` on `admin_audit_logs` in production. Append-only by convention; integrity depends on this constraint.
