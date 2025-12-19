# ⚡ Laravel Octane & Performance

> High-performance setup with Laravel Octane and N+1 detection

## Overview

Soleil Hostel uses Laravel Octane for **4-8x performance improvement**:

| Metric     | Standard | Octane   | Improvement |
| ---------- | -------- | -------- | ----------- |
| Latency    | 40ms     | 5-10ms   | 4-8x faster |
| Throughput | 500 RPS  | 2000 RPS | 4x higher   |
| Memory     | ~50MB    | ~80MB    | Shared      |

---

## Installation

```bash
cd backend

# Install Octane
composer require laravel/octane

# Install Swoole extension (recommended)
pecl install swoole

# Initialize Octane
php artisan octane:install
```

---

## Configuration

```php
// config/octane.php

return [
    'server' => env('OCTANE_SERVER', 'swoole'),
    'host' => env('OCTANE_HOST', '0.0.0.0'),
    'port' => env('OCTANE_PORT', 8000),

    // Auto-detect CPU cores
    'workers' => env('OCTANE_WORKERS', 'auto'),

    // Recycle workers after 500 requests (prevent memory leaks)
    'max_requests' => env('OCTANE_MAX_REQUESTS', 500),
];
```

---

## Running Octane

### Development

```bash
# Start Octane server
php artisan octane:start

# With file watching (auto-reload)
php artisan octane:start --watch

# Stop server
php artisan octane:stop
```

### Production

```bash
# Start with optimal settings
php artisan octane:start --workers=auto --max-requests=500

# Or via Supervisor
supervisorctl start octane
```

### Supervisor Configuration

```ini
[program:octane]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan octane:start --server=swoole --host=0.0.0.0 --port=8000
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/octane.log
```

---

## N+1 Query Detection

Automatic detection of N+1 query problems.

### Configuration

```php
// config/query-detector.php

return [
    'enabled' => env('QUERY_DETECTOR_ENABLED', true),
    'in_test' => true,  // Enable in tests
    'threshold' => env('QUERY_DETECTOR_THRESHOLD', 50),

    'models' => [
        'App\Models\Booking',
        'App\Models\Room',
        'App\Models\User',
    ],

    'whitelist' => [
        'select * from information_schema',
        'select * from sqlite_master',
    ],
];
```

### Octane N+1 Listener

```php
// App\Octane\NPlusOneDetectionListener

class NPlusOneDetectionListener
{
    private array $queryMetrics = [];

    public function handle(RequestReceived $event): void
    {
        $this->queryMetrics = [
            'total' => 0,
            'models' => [],
            'start_time' => microtime(true),
        ];

        // Attach query listener
        foreach (app('db')->getConnections() as $connection) {
            $connection->listen(function ($query) {
                $this->recordQuery($query);
            });
        }
    }

    private function recordQuery($query): void
    {
        $this->queryMetrics['total']++;

        // Log warning if threshold exceeded
        if ($this->queryMetrics['total'] > 50) {
            Log::warning('N+1 Query Detection: Query count exceeded 50', [
                'total_queries' => $this->queryMetrics['total'],
                'request_path' => request()->path(),
            ]);
        }
    }

    private function detectNPlusOne(): bool
    {
        // If single table queried many times, likely N+1
        foreach ($this->queryMetrics['models'] as $table => $count) {
            if ($count > 5) {
                return true;
            }
        }
        return false;
    }
}
```

### QueryDebuggerListener (Standard Laravel)

```php
// App\Listeners\QueryDebuggerListener

class QueryDebuggerListener
{
    public function handle(QueryExecuted $event): void
    {
        if (!config('query-detector.enabled')) {
            return;
        }

        // Track query count
        $this->queries[] = $event->sql;

        // Alert if threshold exceeded
        if (count($this->queries) > config('query-detector.threshold')) {
            Log::warning('⚠️ N+1 QUERY DETECTED!');

            // Fail tests if N+1 detected
            if (app()->runningUnitTests()) {
                throw new \RuntimeException(
                    "N+1 Query Detected: " . count($this->queries) . " queries"
                );
            }
        }
    }
}
```

### QueryMetricsTable (Octane Shared Memory)

```php
// App\Octane\Tables\QueryMetricsTable

class QueryMetricsTable
{
    public static function create(): Table
    {
        return Table::make('query-metrics')
            ->column('total_queries', initialValue: 0)
            ->column('slow_queries', initialValue: 0)
            ->column('request_count', initialValue: 0)
            ->column('last_nplusone_detection', initialValue: null)
            ->column('avg_query_time', initialValue: 0.0);
    }
}
```

| Column                  | Type   | Description                  |
| ----------------------- | ------ | ---------------------------- |
| total_queries           | int    | Total queries across workers |
| slow_queries            | int    | Queries >1s                  |
| request_count           | int    | Total requests handled       |
| last_nplusone_detection | string | Timestamp of last N+1        |
| avg_query_time          | float  | Average query time (ms)      |

}

````

---

## Preventing N+1 Queries

### Problem

```php
// ❌ BAD: N+1 Query (1 + N queries)
$bookings = Booking::all();
foreach ($bookings as $booking) {
    echo $booking->room->name;  // Each iteration = 1 query
}
// With 100 bookings = 101 queries!
````

### Solution

```php
// ✅ GOOD: Eager loading (2 queries total)
$bookings = Booking::with('room')->get();
foreach ($bookings as $booking) {
    echo $booking->room->name;  // No extra query
}

// ✅ GOOD: Specific columns
$bookings = Booking::with(['room:id,name,price'])->get();

// ✅ GOOD: Nested eager loading
$bookings = Booking::with(['room', 'user'])->get();
```

### withCommonRelations Scope

```php
// App\Models\Room

public function scopeWithCommonRelations($query)
{
    return $query->with([
        'activeBookings:id,room_id,check_in,check_out',
    ])->withCount('activeBookings');
}

// Usage
$rooms = Room::withCommonRelations()->get();
```

---

## API Resources

Transform models consistently for API responses.

### BookingResource

```php
// App\Http\Resources\BookingResource

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'check_in' => $this->check_in->format('Y-m-d'),
            'check_out' => $this->check_out->format('Y-m-d'),
            'guest_name' => $this->guest_name,
            'status' => $this->status,
            'nights' => $this->nights,

            // Conditional relationships
            'room' => $this->whenLoaded('room', fn() => new RoomResource($this->room)),
            'user' => $this->whenLoaded('user', fn() => new UserResource($this->user)),

            // Soft delete info (admin only)
            'is_trashed' => $this->when($this->trashed(), true),
            'deleted_at' => $this->when($this->trashed(), fn() => $this->deleted_at?->toIso8601String()),
            'deleted_by' => $this->whenLoaded('deletedBy', fn() => [
                'id' => $this->deletedBy->id,
                'name' => $this->deletedBy->name,
            ]),

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

### RoomResource

```php
// App\Http\Resources\RoomResource

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'max_guests' => $this->max_guests,
            'status' => $this->status,

            // Optimistic locking - client MUST send this on update
            'lock_version' => $this->lock_version,

            // Booking count
            'active_bookings_count' => $this->active_bookings_count ?? 0,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

### Usage in Controllers

```php
// Always use Resources for consistent output
return response()->json([
    'success' => true,
    'data' => BookingResource::collection($bookings),
]);

return response()->json([
    'success' => true,
    'data' => new RoomResource($room),
]);
```

---

## Tests

```bash
# N+1 prevention tests
php artisan test tests/Feature/NPlusOneQueriesTest.php

# Performance tests
php artisan test --filter=performance
```

| Test Suite           | Count |
| -------------------- | ----- |
| N+1 Prevention       | 7     |
| Eager Loading        | 4     |
| Query Count Tracking | 3     |
