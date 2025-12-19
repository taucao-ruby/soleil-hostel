# 🔧 Traits, Macros & Exceptions

> Reusable traits, Blade directives, FormRequest macros and custom exceptions

## Traits

### Purifiable

Automatically sanitizes HTML fields using HTML Purifier (whitelist approach).

```php
// App\Traits\Purifiable

trait Purifiable
{
    protected array $purifiable = [];
    protected array $purifiable_config = [];

    public static function bootPurifiable(): void
    {
        // Before saving: purify dirty fields
        static::saving(function (Model $model) {
            $fields = $model->getPurifiableFields();
            foreach ($fields as $field) {
                if ($model->isDirty($field) && $model->getAttribute($field) !== null) {
                    $config = $model->getPurifiableConfig($field);
                    $clean = HtmlPurifierService::purify(
                        $model->getAttribute($field),
                        $config
                    );
                    $model->setAttribute($field, $clean);
                }
            }
        });

        // After retrieved: extra safety for old data
        static::retrieved(function (Model $model) {
            // Optional double-purify on read
        });
    }
}
```

### Usage

```php
// App\Models\Booking

use App\Traits\Purifiable;

class Booking extends Model
{
    use Purifiable;

    protected $purifiable = ['guest_name', 'notes'];

    // Optional: per-field config
    protected $purifiable_config = [
        'notes' => [
            'allowed_elements' => ['b', 'i', 'u'],
        ],
    ];
}
```

### Benefits

| Approach        | Bypass Rate | Maintenance |
| --------------- | ----------- | ----------- |
| Regex blacklist | ~99%        | High        |
| HTML Purifier   | 0%          | Low         |

---

## Exceptions

### OptimisticLockException

Thrown when concurrent modification is detected (lost update prevention).

```php
// App\Exceptions\OptimisticLockException

class OptimisticLockException extends RuntimeException
{
    public const HTTP_STATUS_CODE = 409;

    public function __construct(
        string $message = 'The resource has been modified by another user.',
        public ?Model $model = null,
        public ?int $expectedVersion = null,
        public ?int $actualVersion = null,
    ) {
        parent::__construct($message);
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'expected_version' => $this->expectedVersion,
            'actual_version' => $this->actualVersion,
            'model_type' => $this->model?->getMorphClass(),
            'model_id' => $this->model?->getKey(),
        ];
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $this->toArray(),
        ], self::HTTP_STATUS_CODE);
    }
}
```

### Scenario

```
1. User A reads Room (version 5)
2. User B reads Room (version 5)
3. User B updates Room → version becomes 6
4. User A tries to update with version 5 → OptimisticLockException
```

### Usage

```php
// App\Services\RoomService

public function updateWithOptimisticLock(Room $room, array $data, ?int $expectedVersion): Room
{
    if ($expectedVersion !== null && $room->lock_version !== $expectedVersion) {
        throw new OptimisticLockException(
            message: 'Room was modified by another user. Please refresh.',
            model: $room,
            expectedVersion: $expectedVersion,
            actualVersion: $room->lock_version,
        );
    }

    $room->fill($data);
    $room->lock_version++;
    $room->save();

    return $room;
}
```

### Exception Handler

```php
// App\Exceptions\Handler.php

public function render($request, Throwable $e)
{
    if ($e instanceof OptimisticLockException) {
        return $e->render();
    }

    // ... other exceptions
}
```

### Client Handling

```typescript
// Frontend handling
async function updateRoom(data: RoomUpdate) {
  try {
    await api.put(`/rooms/${data.id}`, data);
  } catch (error) {
    if (error.response?.status === 409) {
      // Conflict - refresh data and show message
      await refreshRoom(data.id);
      showNotification("Room was modified. Please review and try again.");
    }
  }
}
```

---

## Enums

### UserRole

Type-safe enum for RBAC roles.

```php
// App\Enums\UserRole

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

### Usage

```php
// In User model
protected $casts = [
    'role' => UserRole::class,
];

// In code
if ($user->role === UserRole::ADMIN) {
    // Admin logic
}

// In migrations
$table->enum('role', UserRole::values())->default(UserRole::default()->value);

// In validation
'role' => ['required', Rule::enum(UserRole::class)],
```

---

## Blade Directives

### @purify Directive

Safely render HTML content in Blade views.

```php
// App\Directives\PurifyDirective

class PurifyDirective
{
    public static function register(): void
    {
        // @purify($content) - purify HTML
        Blade::directive('purify', function ($expression) {
            return "<?php echo \App\Services\HtmlPurifierService::purify({$expression}); ?>";
        });

        // @purifyPlain($content) - strip all HTML
        Blade::directive('purifyPlain', function ($expression) {
            return "<?php echo \App\Services\HtmlPurifierService::plaintext({$expression}); ?>";
        });
    }
}
```

### Usage in Blade

```blade
{{-- Safe: Renders purified HTML --}}
@purify($review->content)

{{-- Safe: Renders plain text only --}}
@purifyPlain($comment->text)

{{-- Safe: Auto-escaped by Laravel --}}
{{ $content }}

{{-- DANGEROUS: Only if already purified --}}
{!! $content !!}
```

---

## FormRequest Macros

### Purify Macro

Purify validated data directly in FormRequest.

```php
// App\Macros\FormRequestPurifyMacro

class FormRequestPurifyMacro
{
    public static function register(): void
    {
        // Purify specific fields
        FormRequest::macro('purify', function (array $fields = []) {
            $validated = $this->validated();

            foreach ($fields as $field) {
                if (isset($validated[$field]) && is_string($validated[$field])) {
                    $validated[$field] = HtmlPurifierService::purify($validated[$field]);
                }
            }

            return $validated;
        });

        // Purify all string fields
        FormRequest::macro('purifyAll', function () {
            $validated = $this->validated();

            foreach ($validated as $key => &$value) {
                if (is_string($value)) {
                    $value = HtmlPurifierService::purify($value);
                }
            }

            return $validated;
        });
    }
}
```

### Usage in Controllers

```php
// Purify specific fields
public function store(StoreReviewRequest $request)
{
    $data = $request->purify(['content', 'title']);
    Review::create($data);
}

// Purify all fields
public function store(ContactRequest $request)
{
    $data = $request->purifyAll();
    Contact::create($data);
}
```

---

## Registration

All directives and macros are registered in `AppServiceProvider`:

```php
// App\Providers\AppServiceProvider

public function boot(): void
{
    // Register Blade directives
    PurifyDirective::register();

    // Register FormRequest macros
    FormRequestPurifyMacro::register();
}
```
