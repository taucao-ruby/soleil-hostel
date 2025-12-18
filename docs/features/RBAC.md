# 👥 Role-Based Access Control (RBAC)

> Type-safe role system with enum, gates, and middleware

## Overview

Soleil Hostel uses a **backed enum** for user roles with Laravel Gates for authorization.

---

## Roles

| Role      | Value         | Permissions                             |
| --------- | ------------- | --------------------------------------- |
| USER      | `'user'`      | Own bookings only                       |
| MODERATOR | `'moderator'` | + View all bookings, moderate content   |
| ADMIN     | `'admin'`     | + Manage users, rooms, restore bookings |

---

## Implementation

### UserRole Enum

```php
// app/Enums/UserRole.php
enum UserRole: string
{
    case USER = 'user';
    case MODERATOR = 'moderator';
    case ADMIN = 'admin';

    public static function default(): self
    {
        return self::USER;
    }
}
```

### User Model Helpers

```php
// app/Models/User.php
public function isAdmin(): bool
{
    return $this->role === UserRole::ADMIN;
}

public function isModerator(): bool
{
    return in_array($this->role, [UserRole::MODERATOR, UserRole::ADMIN]);
}

public function isAtLeast(UserRole $role): bool
{
    $hierarchy = [
        UserRole::USER->value => 1,
        UserRole::MODERATOR->value => 2,
        UserRole::ADMIN->value => 3,
    ];
    return $hierarchy[$this->role->value] >= $hierarchy[$role->value];
}
```

---

## Authorization Gates

```php
// bootstrap/app.php or AuthServiceProvider
Gate::define('admin', fn (User $user) => $user->isAdmin());
Gate::define('moderator', fn (User $user) => $user->isModerator());
Gate::define('manage-users', fn (User $user) => $user->isAdmin());
Gate::define('manage-rooms', fn (User $user) => $user->isAdmin());
Gate::define('moderate-content', fn (User $user) => $user->isModerator());
Gate::define('view-all-bookings', fn (User $user) => $user->isModerator());
```

### Usage in Controllers

```php
// Authorization check
Gate::authorize('manage-rooms');

// Conditional
if (Gate::allows('view-all-bookings')) {
    return Booking::all();
}
return $user->bookings;
```

---

## Middleware

### EnsureUserHasRole

```php
// app/Http/Middleware/EnsureUserHasRole.php
public function handle(Request $request, Closure $next, string $role)
{
    $requiredRole = UserRole::from($role);

    if (!$request->user()?->isAtLeast($requiredRole)) {
        abort(403, 'Insufficient permissions');
    }

    return $next($request);
}
```

### Route Registration

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/admin/users', [UserController::class, 'store']);
    Route::delete('/admin/users/{id}', [UserController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:moderator'])->group(function () {
    Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);
});
```

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
