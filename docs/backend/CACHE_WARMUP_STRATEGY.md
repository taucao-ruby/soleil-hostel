# Cache Warmup Strategy

## Overview

Cache warmup is a critical post-deployment step that pre-populates application caches to prevent cold-start latency spikes. Without warmup, the first requests after deployment hit the database directly, causing response times to spike from ~50ms to ~500ms+.

## Problem Statement

### Cold-Start Spikes

- **Before warmup**: First requests after deploy = 300-500ms (cache miss → DB query)
- **After warmup**: First requests after deploy = 30-80ms (cache hit)
- **Goal**: Cache hit rate > 95% immediately after deployment

### Impact

- Poor user experience for first visitors
- Potential timeouts under load
- Cascading failures if many users hit cold cache simultaneously

## Solution

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Cache Warmup Flow                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Deploy Script                                                  │
│       │                                                         │
│       ▼                                                         │
│  php artisan cache:warmup --force                              │
│       │                                                         │
│       ▼                                                         │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                   CacheWarmer Service                     │  │
│  │                                                           │  │
│  │  Priority Order:                                          │  │
│  │  1. Config (critical)    - App settings, feature flags   │  │
│  │  2. Rooms (critical)     - Room catalog, availability    │  │
│  │  3. Users (non-critical) - Active user profiles          │  │
│  │  4. Bookings            - Today's check-ins/outs          │  │
│  │  5. Static              - Translations, static pages      │  │
│  │  6. Computed            - Statistics, dashboards          │  │
│  └──────────────────────────────────────────────────────────┘  │
│       │                                                         │
│       ▼                                                         │
│  Cache Layer (Redis/Database)                                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Usage

### Basic Usage

```bash
# Warm all cache groups
php artisan cache:warmup

# Dry run (preview only)
php artisan cache:warmup --dry-run

# Warm specific groups
php artisan cache:warmup --group=rooms --group=config

# Force override existing cache
php artisan cache:warmup --force

# With custom chunk size
php artisan cache:warmup --chunk=50

# Verbose output
php artisan cache:warmup -v
```

### Available Options

| Option          | Description                  | Default    |
| --------------- | ---------------------------- | ---------- |
| `--dry-run`     | Preview what would be cached | `false`    |
| `--group=*`     | Warm specific groups only    | All groups |
| `--force`       | Override existing cache      | `false`    |
| `--chunk=N`     | Process datasets in chunks   | `100`      |
| `--timeout=N`   | Max execution time (seconds) | `300`      |
| `--no-progress` | Disable progress output      | `false`    |
| `-v, --verbose` | Detailed output              | `false`    |

### Cache Groups

| Group      | Priority | Critical | Description                      | TTL    |
| ---------- | -------- | -------- | -------------------------------- | ------ |
| `config`   | 1        | ✅       | App configuration, feature flags | 24h    |
| `rooms`    | 2        | ✅       | Room catalog, availability       | 1h     |
| `users`    | 3        | ❌       | Active user profiles, admins     | 1h     |
| `bookings` | 4        | ❌       | Today's check-ins/outs           | 2h     |
| `static`   | 5        | ❌       | Translations, static content     | 24h    |
| `computed` | 6        | ❌       | Statistics, dashboard metrics    | 15-30m |

## Deployment Integration

### Deploy Script (deploy-forge.sh)

```bash
# In main deployment flow
main() {
    # ... other steps ...

    # Run migrations
    php artisan migrate --force

    # Warm up cache (post-migration, pre-live)
    php artisan cache:warmup --force --no-progress

    # ... health check ...
}
```

### GitHub Actions

```yaml
- name: Cache Warmup
  run: |
    php artisan cache:warmup --force --timeout=120
  timeout-minutes: 3
```

### Forge Deploy Script

```bash
cd /home/forge/solelhotel.com
php artisan down
git pull origin $FORGE_SITE_BRANCH
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan cache:warmup --force  # ← Add this
php artisan up
```

## Monitoring

### Logs

```bash
# View warmup logs
tail -f storage/logs/laravel.log | grep CacheWarmer

# Example log output
[2025-01-25 10:30:00] INFO: [CacheWarmer] Starting cache warmup {"groups":["config","rooms","users"],"dry_run":false}
[2025-01-25 10:30:01] INFO: [CacheWarmer] Group 'config' completed {"status":"success","warmed_count":3,"duration_ms":150}
[2025-01-25 10:30:05] INFO: [CacheWarmer] Group 'rooms' completed {"status":"success","warmed_count":45,"duration_ms":3500}
[2025-01-25 10:30:06] INFO: [CacheWarmer] Cache warmup completed {"total_duration_ms":6000}
```

### Metrics to Track

| Metric                       | Target  | Alert Threshold |
| ---------------------------- | ------- | --------------- |
| Warmup Duration              | < 2 min | > 5 min         |
| Cache Hit Rate (post-deploy) | > 95%   | < 80%           |
| First Request Latency        | < 200ms | > 500ms         |
| Memory Usage                 | < 256MB | > 512MB         |
| Failed Groups                | 0       | > 0 critical    |

### Health Check Endpoint

```php
// routes/api.php
Route::get('/cache/status', function () {
    $warmer = app(CacheWarmer::class);
    return response()->json($warmer->healthCheck());
});
```

## Runbook

### Issue: Cache warmup taking too long (> 5 min)

**Symptoms:**

- Deployment blocked on warmup step
- High memory usage

**Diagnosis:**

```bash
# Check which group is slow
php artisan cache:warmup -v

# Run specific group to isolate
php artisan cache:warmup --group=rooms -v
```

**Solutions:**

1. Reduce chunk size: `--chunk=50`
2. Skip non-critical groups: `--group=config --group=rooms`
3. Check database performance (slow queries)
4. Increase Redis connection pool

### Issue: Memory limit exceeded

**Symptoms:**

- "Allowed memory size exhausted" error
- Process killed by OOM

**Solutions:**

1. Reduce chunk size
2. Increase PHP memory limit temporarily:
   ```bash
   php -d memory_limit=512M artisan cache:warmup
   ```
3. Warm groups separately:
   ```bash
   php artisan cache:warmup --group=config
   php artisan cache:warmup --group=rooms
   ```

### Issue: Critical cache group failed

**Symptoms:**

- Command exits with error
- Deployment should NOT proceed

**Solutions:**

1. Check cache server connection
2. Check database connection
3. Review error logs:
   ```bash
   grep "CacheWarmer" storage/logs/laravel.log | tail -50
   ```
4. Fix issue and retry warmup

### Issue: High cache miss rate after deployment

**Symptoms:**

- Response times still high after warmup
- Low cache hit rate

**Diagnosis:**

```bash
# Check if cache is populated
php artisan tinker
>>> Cache::has('stats:rooms')
>>> Cache::get('stats:rooms')
```

**Solutions:**

1. Verify warmup ran: check logs
2. Check cache TTL not too short
3. Verify cache driver is correct (Redis vs Database)
4. Run warmup with `--force` flag

## Performance Benchmarks

### Test Environment

- Database: PostgreSQL 15
- Cache: Redis 7.0
- Rooms: 50
- Users: 1,000
- Bookings: 5,000

### Results

| Group     | Items Warmed | Duration | Memory   |
| --------- | ------------ | -------- | -------- |
| config    | 3            | 50ms     | 5MB      |
| rooms     | 50           | 2.5s     | 30MB     |
| users     | 100          | 1.2s     | 20MB     |
| bookings  | 25           | 800ms    | 15MB     |
| static    | 3            | 100ms    | 5MB      |
| computed  | 3            | 500ms    | 10MB     |
| **Total** | **184**      | **5.2s** | **85MB** |

### Production Estimates

| Scale                  | Warmup Time | Memory Peak |
| ---------------------- | ----------- | ----------- |
| Small (< 100 rooms)    | < 30s       | < 128MB     |
| Medium (100-500 rooms) | 1-2 min     | < 256MB     |
| Large (500+ rooms)     | 2-5 min     | < 512MB     |

## Testing

### Run Tests

```bash
# All cache warmup tests
php artisan test tests/Feature/Cache/CacheWarmupTest.php

# Specific test
php artisan test --filter=test_cache_warmup_command_runs_successfully
```

### Test Checklist

- [ ] Command runs successfully
- [ ] Dry run makes no changes
- [ ] Specific groups can be warmed
- [ ] Force option overrides cache
- [ ] Idempotency (run twice = same result)
- [ ] Graceful failure on individual group
- [ ] Memory stays under limit
- [ ] Duration under timeout

## Troubleshooting

### Common Issues

1. **Redis connection refused**

   ```
   Check REDIS_HOST, REDIS_PORT in .env
   Verify Redis is running: redis-cli ping
   ```

2. **Database query timeout**

   ```
   Check slow query log
   Add indexes if needed
   Reduce chunk size
   ```

3. **Permission denied on cache directory**
   ```bash
   chmod -R 775 storage/framework/cache
   chown -R www-data:www-data storage
   ```

## Future Improvements

- [ ] Parallel warmup using queue workers
- [ ] Warmup priority based on traffic patterns
- [ ] Automatic warmup on cache server restart
- [ ] Integration with APM for performance tracking
- [ ] Warm specific date ranges via CLI option
