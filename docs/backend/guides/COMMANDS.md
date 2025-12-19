# 🔧 Artisan Commands

> Custom console commands for maintenance and monitoring

## Available Commands

| Command                   | Purpose                            |
| ------------------------- | ---------------------------------- |
| `bookings:prune-deleted`  | Clean up old soft-deleted bookings |
| `octane:monitor-nplusone` | Monitor N+1 queries in Octane      |

---

## PruneOldSoftDeletedBookings

Permanently removes soft-deleted bookings older than retention period (compliance).

### Usage

```bash
# Default: 7 years retention (2555 days)
php artisan bookings:prune-deleted

# Custom retention: 1 year
php artisan bookings:prune-deleted --days=365

# Preview without deleting (dry run)
php artisan bookings:prune-deleted --dry-run
```

### Implementation

```php
// App\Console\Commands\PruneOldSoftDeletedBookings

class PruneOldSoftDeletedBookings extends Command
{
    protected $signature = 'bookings:prune-deleted
                            {--days=2555 : Days after which to prune (default 7 years)}
                            {--dry-run : Preview without deleting}';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $query = Booking::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate);

        $count = $query->count();

        if ($this->option('dry-run')) {
            // Show preview table
            $this->table(['ID', 'Guest', 'Deleted At'], ...);
            return Command::SUCCESS;
        }

        if ($this->confirm("Delete {$count} booking(s)? IRREVERSIBLE!")) {
            $query->forceDelete();
            Log::info("Pruned {$count} soft-deleted bookings");
        }

        return Command::SUCCESS;
    }
}
```

### Scheduling

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Run weekly on Sunday at 3 AM
    $schedule->command('bookings:prune-deleted')
        ->weekly()
        ->sundays()
        ->at('03:00')
        ->appendOutputTo(storage_path('logs/prune.log'));
}
```

### Compliance Notes

| Regulation | Retention Period | Notes                   |
| ---------- | ---------------- | ----------------------- |
| SOX        | 7 years          | US financial records    |
| GDPR       | As needed        | "Right to be forgotten" |
| General    | 7 years          | Safe default            |

---

## OctaneNPlusOneMonitor

Real-time monitoring of N+1 queries in Octane workers.

### Usage

```bash
# Default: check every 5 seconds
php artisan octane:monitor-nplusone

# Custom interval
php artisan octane:monitor-nplusone --interval=10
```

### Implementation

```php
// App\Console\Commands\OctaneNPlusOneMonitor

class OctaneNPlusOneMonitor extends Command
{
    protected $signature = 'octane:monitor-nplusone
                            {--interval=5 : Check interval in seconds}';

    public function handle(): int
    {
        $interval = $this->option('interval');

        while (true) {
            $this->checkNPlusOneMetrics();
            sleep($interval);
        }
    }

    private function checkNPlusOneMetrics(): void
    {
        $table = \Octane::table('query-metrics');

        $totalQueries = $table->get('total_queries');
        $requestCount = $table->get('request_count');
        $avgQueriesPerRequest = $totalQueries / max($requestCount, 1);

        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Avg Queries/Request', round($avgQueriesPerRequest, 2), $this->getStatus($avgQueriesPerRequest)],
                ['Slow Queries (>1s)', $table->get('slow_queries'), '⚠️'],
            ]
        );

        if ($avgQueriesPerRequest > 20) {
            $this->error("⚠️ HIGH QUERY COUNT!");
        }
    }

    private function getStatus(float $avg): string
    {
        if ($avg < 5) return '✅ Excellent';
        if ($avg < 10) return '🟢 Good';
        if ($avg < 20) return '🟡 Fair';
        return '🔴 Critical';
    }
}
```

### Output Example

```
┌────────────────────────┬─────────┬────────────┐
│ Metric                 │ Value   │ Status     │
├────────────────────────┼─────────┼────────────┤
│ Total Queries          │ 1250    │ 📊         │
│ Requests Processed     │ 156     │ ✅         │
│ Avg Queries/Request    │ 8.01    │ 🟢 Good    │
│ Slow Queries (>1s)     │ 2       │ ⚠️         │
│ Last N+1 Detection     │ None    │ ✅         │
└────────────────────────┴─────────┴────────────┘
```

---

## Other Useful Commands

### Laravel Built-in

```bash
# Clear all caches
php artisan optimize:clear

# Rebuild cache (production)
php artisan optimize

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Run tests
php artisan test

# Generate IDE helper (if installed)
php artisan ide-helper:generate
```

### Queue Management

```bash
# Process queue jobs
php artisan queue:work redis --queue=bookings

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Cache Management

```bash
# Clear application cache
php artisan cache:clear

# Clear Redis cache
php artisan cache:clear --store=redis

# Flush rate limiter cache
php artisan cache:forget throttle:*
```
