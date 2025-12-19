# ⭐ Reviews Feature

> User-generated reviews với XSS protection và moderation workflow

## Overview

Hệ thống reviews cho phép guests đánh giá phòng với **multi-layer XSS protection**:

| Layer      | Component            | Purpose                    |
| ---------- | -------------------- | -------------------------- |
| Input      | FormRequest          | Validate + auto-purify     |
| Model      | Purifiable trait     | Double-check on save       |
| Output     | @purify directive    | Safe rendering in Blade    |
| Moderation | is_approved workflow | Admin review before public |

---

## Model

```php
// App\Models\Review

class Review extends Model
{
    use HasFactory, Purifiable;

    protected $fillable = [
        'title',
        'content',
        'rating',
        'room_id',
        'user_id',
        'guest_name',
        'guest_email',
    ];

    // Auto-purify these fields on save
    protected array $purifiable = [
        'title',
        'content',
        'guest_name',
    ];

    // Relationships
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    public function scopeHighRated($query)
    {
        return $query->where('rating', '>=', 4);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }
}
```

---

## Database Schema

```sql
CREATE TABLE reviews (
    id BIGSERIAL PRIMARY KEY,
    room_id BIGINT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    guest_name VARCHAR(255) NOT NULL,
    guest_email VARCHAR(255),
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_reviews_room_approved ON reviews (room_id, is_approved);
CREATE INDEX idx_reviews_rating ON reviews (rating);
```

---

## API Endpoints

| Method | Endpoint                | Description           | Auth   |
| ------ | ----------------------- | --------------------- | ------ |
| GET    | `/rooms/{room}/reviews` | List approved reviews | Public |
| POST   | `/reviews`              | Create new review     | Auth   |
| GET    | `/reviews/{id}`         | View single review    | Public |
| PATCH  | `/reviews/{id}`         | Update own review     | Owner  |
| DELETE | `/reviews/{id}`         | Delete own review     | Owner  |
| POST   | `/reviews/import`       | Bulk import (CSV)     | Admin  |
| GET    | `/reviews/audit`        | Audit XSS attempts    | Admin  |

---

## Form Request Validation

```php
// App\Http\Requests\StoreReviewRequest

class StoreReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'rating' => 'required|integer|min:1|max:5',
            'room_id' => 'required|integer|exists:rooms,id',
        ];
    }

    /**
     * Auto-purify after validation
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if (is_array($validated)) {
            $validated = $this->purify(['title', 'content']);
        }

        return $validated;
    }
}
```

---

## XSS Protection Layers

### Layer 1: FormRequest Purification

```php
// In controller
public function store(StoreReviewRequest $request)
{
    // Already purified via validated() override
    $validated = $request->validated();

    Review::create($validated);
}
```

### Layer 2: Model Purifiable Trait

```php
// Automatic on save - even if bypassing FormRequest
trait Purifiable
{
    public static function bootPurifiable(): void
    {
        static::saving(function (Model $model) {
            foreach ($model->getPurifiableFields() as $field) {
                if ($model->isDirty($field)) {
                    $model->setAttribute($field,
                        HtmlPurifierService::purify($model->getAttribute($field))
                    );
                }
            }
        });
    }
}
```

### Layer 3: Blade Output

```blade
{{-- Safe: Uses @purify directive --}}
@purify($review->content)

{{-- Also safe: Laravel auto-escapes --}}
{{ $review->title }}

{{-- DANGEROUS - only if confirmed purified --}}
{!! $review->content !!}
```

---

## Moderation Workflow

### Pending → Approved Flow

```
1. Guest submits review
2. is_approved = false (default)
3. Admin reviews in dashboard
4. Admin approves → is_approved = true
5. Review appears publicly
```

### Controller Logic

```php
public function index(Room $room)
{
    $reviews = $room->reviews()
        ->where('is_approved', true)  // Only show approved
        ->latest('created_at')
        ->paginate(15);

    return view('reviews.index', compact('reviews'));
}
```

---

## Admin Features

### Bulk Import

```php
public function importReviews(Request $request)
{
    Gate::authorize('admin');

    $csvData = $request->file('csv')->getContent();
    $lines = explode("\n", $csvData);

    foreach ($lines as $line) {
        [$title, $content, $rating] = str_getcsv($line);

        // Direct purification for batch
        Review::create([
            'title' => HtmlPurifierService::purify($title),
            'content' => HtmlPurifierService::purify($content),
            'rating' => (int) $rating,
            'is_approved' => false,
        ]);
    }
}
```

### XSS Audit

```php
public function auditXssAttempts()
{
    Gate::authorize('admin');

    $suspicious = Review::where('is_approved', false)
        ->get()
        ->filter(function ($review) {
            $original = $review->getRawOriginal('content');
            $purified = HtmlPurifierService::purify($original);

            // Different = dangerous elements were stripped
            return $original !== $purified;
        });

    return view('reviews.audit', ['suspicious' => $suspicious]);
}
```

---

## Relationships

```php
// Room has many reviews
class Room extends Model
{
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function averageRating(): float
    {
        return $this->approvedReviews()->avg('rating') ?? 0;
    }
}

// User has many reviews
class User extends Model
{
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
```

---

## Query Examples

```php
// Get all approved reviews for a room
$reviews = Review::where('room_id', $roomId)
    ->approved()
    ->recent()
    ->get();

// Get high-rated reviews
$topReviews = Review::approved()
    ->highRated()
    ->with('user')
    ->take(5)
    ->get();

// Room with reviews eager loaded
$room = Room::with(['approvedReviews' => fn($q) => $q->recent()->take(10)])
    ->findOrFail($id);
```

---

## Security Best Practices

| ❌ Never Do                        | ✅ Always Do                  |
| ---------------------------------- | ----------------------------- |
| `{!! $content !!}` without purify  | `@purify($content)`           |
| Regex to filter XSS                | HTML Purifier whitelist       |
| Trust `$request->input()` directly | Use `$request->validated()`   |
| Skip validation for admin imports  | Purify even batch/CSV imports |

---

## Related Documentation

- [XSS_PROTECTION.md](../security/XSS_PROTECTION.md) - HTML Purifier setup
- [TRAITS_EXCEPTIONS.md](../architecture/TRAITS_EXCEPTIONS.md) - Purifiable trait details
- [API.md](../architecture/API.md) - Full API reference
