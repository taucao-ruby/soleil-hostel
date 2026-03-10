# 👥 Role-Based Access Control (RBAC)

> Type-safe role system with backed enum, gates, and middleware

## Overview

Soleil Hostel uses a **backed string enum** for user roles with Laravel Gates for authorization. This provides **compile-time type safety**, IDE autocomplete, and eliminates string literals across the codebase.

---

## Role Hierarchy

| Role      | Level | Value         | Permissions                                            |
| --------- | ----- | ------------- | ------------------------------------------------------ |
| USER      | 1     | `'user'`      | Own bookings only                                      |
| MODERATOR | 2     | `'moderator'` | + View all bookings, moderate content, approve reviews |
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

| Test Category      | Count  |
| ------------------ | ------ |
| Gate Tests         | 14     |
| Middleware Tests   | 9      |
| Enum Tests         | 8      |
| Model Helper Tests | 16     |
| **Total**          | **47** |
