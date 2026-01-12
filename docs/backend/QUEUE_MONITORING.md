# Queue Monitoring Implementation — Production Design

## Executive Summary

Implemented Laravel Horizon for Redis-based queue monitoring with admin-only access control. Zero custom UI built—Horizon provides a complete dashboard at `/horizon` with real-time metrics, failed jobs management, and worker supervision.

**Key decisions:**
- Horizon over custom endpoints (Redis driver confirmed)
- Gate-based authorization (`view-queue-monitoring`) admin-only
- Dedicated supervisors for default, emails, and notifications queues
- Scheduled snapshots every 5 minutes + daily cleanup
- ext-pcntl/ext-posix ignored for Windows dev (required in Linux prod)

---

## 1. Architecture & Rationale

### Why Horizon?

| Requirement | Horizon Solution | Alternative (Custom JSON API) |
|------------|------------------|-------------------------------|
| View failed jobs | Built-in UI with filters | Manual DB queries on `failed_jobs` |
| Retry failed jobs | One-click or bulk retry | POST endpoints + manual dispatching |
| Monitor queue backlog | Real-time metrics | Redis LLEN polling |
| Worker supervision | Integrated process monitoring | None (Supervisor only) |
| Maintenance cost | Zero (first-party) | Ongoing (custom code) |

**Verdict**: Horizon wins in all categories. Custom endpoints only justified for non-Redis drivers (database queue).

### Driver Confirmation

```php
// backend/config/queue.php
'default' => env('QUEUE_CONNECTION', 'redis'), // Line 16
```

Redis is the default. Horizon is the correct tool.

---

## 2. Implementation Breakdown

### Files Modified

| File | Purpose | Key Change |
|------|---------|------------|
| [`backend/app/Providers/HorizonServiceProvider.php`](../../backend/app/Providers/HorizonServiceProvider.php) | Gate registration | `viewHorizon` delegates to `view-queue-monitoring` gate |
| [`backend/app/Providers/AuthServiceProvider.php`](../../backend/app/Providers/AuthServiceProvider.php) | Gate definition | New `view-queue-monitoring` gate (admin-only) |
| [`backend/config/horizon.php`](../../backend/config/horizon.php) | Horizon config | Production supervisors + wait thresholds |
| [`backend/routes/console.php`](../../backend/routes/console.php) | Scheduler | `horizon:snapshot` (5 min) + `horizon:clear` (daily) |
| [`config/supervisor/horizon.conf`](../../config/supervisor/horizon.conf) | Process management | Supervisor config for production |

### Authorization Flow

```
User → /horizon → Horizon middleware → viewHorizon gate → view-queue-monitoring gate → isAdmin()
```

- **Non-admins**: 403 Forbidden
- **Unauthenticated**: 403 Forbidden (no redirect—Horizon handles this internally)
- **Admins**: Full dashboard access

**Why no `auth` middleware?** Horizon's internal gate check is sufficient. Adding `auth` middleware causes redirect-to-login errors when no login route exists (SPA architecture).

---

## 3. Horizon Configuration

### Queue Supervisors (Production)

```php
'environments' => [
    'production' => [
        'supervisor-1' => [  // Default queue
            'connection' => 'redis',
            'queue' => ['default'],
            'maxProcesses' => 10,
            'minProcesses' => 2,
            'tries' => 3,
            'timeout' => 90,
        ],
        'supervisor-emails' => [  // Dedicated email queue
            'connection' => 'redis',
            'queue' => ['emails'],
            'maxProcesses' => 5,
            'tries' => 3,
            'timeout' => 60,
        ],
        'supervisor-notifications' => [  // Dedicated notification queue
            'connection' => 'redis',
            'queue' => ['notifications'],
            'maxProcesses' => 5,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
],
```

**Rationale:**
- Separate supervisors prevent email bursts from starving notifications
- `minProcesses: 2` ensures immediate processing for default queue
- `tries: 3` balances retry attempts vs. noise in failed jobs

### Wait Time Thresholds

```php
'waits' => [
    'redis:default' => 60,       // 1 minute before alert
    'redis:emails' => 30,         // 30 seconds (user-facing)
    'redis:notifications' => 30,  // 30 seconds (user-facing)
],
```

Fires `Illuminate\Queue\Events\LongWaitDetected` event when thresholds exceeded. Hook into Sentry/logging for alerting.

---

## 4. Failure Modes & Edge Cases

### Redis Down
**Symptom**: Horizon dashboard inaccessible (500 error)  
**Detection**: Health check on `/horizon` endpoint (expect 200 for admin)  
**Mitigation**: Horizon gracefully degrades—workers queue jobs locally until Redis returns

### High Failed Job Volume (1000+)
**Symptom**: Horizon UI sluggish when paginating failed jobs  
**Mitigation**: Use `artisan queue:flush` to clear failed jobs after investigation. Log count before flushing for metrics.

### Unauthorized Access Attempts
**Detection**: 403 responses on `/horizon/*` routes  
**Logging**: Automatically logged by LogPerformance middleware (correlation ID + user ID)  
**Action**: No additional rate limiting needed (Gate check prevents brute force)

### Worker Process Stopped
**Detection**: Supervisor restarts worker (see `autorestart=true` in horizon.conf)  
**Alerting**: Horizon dashboard shows worker as "dead" (red indicator)  
**Manual check**: `supervisorctl status horizon`

### Retry Failure Loop
**Scenario**: Job fails → retries → fails again (infinite loop)  
**Prevention**: `tries: 3` in supervisor config limits retries  
**Detection**: High `failed_jobs` table growth (monitor via Sentry)  
**Resolution**: Fix job logic, then `php artisan queue:retry all`

---

## 5. Testing Strategy

### Automated Tests ([QueueMonitoringAuthorizationTest.php](../../backend/tests/Feature/Queue/QueueMonitoringAuthorizationTest.php))

| Test | Assertion | Why |
|------|-----------|-----|
| `admin_can_access_horizon_dashboard` | 200 OK | Verifies gate allows admin |
| `moderator_cannot_access_horizon_dashboard` | 403 Forbidden | Ensures moderators blocked |
| `regular_user_cannot_access_horizon_dashboard` | 403 Forbidden | Ensures users blocked |
| `unauthenticated_user_cannot_access_horizon_dashboard` | 403 Forbidden | Horizon handles auth internally |
| `admin_has_view_queue_monitoring_gate` | Gate passes | Gate logic correct |
| `non_admin_does_not_have_view_queue_monitoring_gate` | Gate fails | Gate logic correct |

**What we DON'T test:**
- Horizon's internal retry logic (tested by Laravel)
- Redis connection (integration test, not unit test)
- Supervisor restart behavior (system test, not application test)

### Manual Verification

**Local development (Windows + Laragon):**
```powershell
# Start Horizon worker
php artisan horizon

# Dispatch test job
php artisan tinker
>>> dispatch(new App\Jobs\ReconcileRefundsJob);

# Access dashboard
http://localhost:8000/horizon
```

**Production deployment:**
```bash
# Check Supervisor status
supervisorctl status horizon

# View logs
tail -f backend/storage/logs/horizon.log

# Manual snapshot
php artisan horizon:snapshot
```

---

## 6. Production Deployment Checklist

### Pre-Deployment

- [ ] Verify `QUEUE_CONNECTION=redis` in `.env`
- [ ] Ensure Redis accessible (test with `redis-cli ping`)
- [ ] Install ext-pcntl and ext-posix (Linux only):
  ```bash
  sudo apt-get install php8.3-pcntl php8.3-posix
  ```
- [ ] Copy [`config/supervisor/horizon.conf`](../../config/supervisor/horizon.conf) to `/etc/supervisor/conf.d/`
- [ ] Update paths in horizon.conf (replace `/var/www/html/backend` with actual path)

### Deployment Steps

```bash
# 1. Install Horizon
composer install --no-dev --optimize-autoloader

# 2. Reload Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

# 3. Verify worker running
supervisorctl status horizon  # Should show "RUNNING"

# 4. Run scheduled tasks (cron)
# Add to crontab: * * * * * cd /var/www/html/backend && php artisan schedule:run >> /dev/null 2>&1
```

### Post-Deployment Verification

- [ ] Access `/horizon` as admin (should see dashboard)
- [ ] Access `/horizon` as non-admin (should see 403)
- [ ] Dispatch test job (verify in "Recent Jobs")
- [ ] Force job failure (verify in "Failed Jobs")
- [ ] Retry failed job (verify success)
- [ ] Check `storage/logs/horizon.log` for errors

---

## 7. Monitoring & Alerting Recommendations

### Metrics to Track

| Metric | Source | Alerting Threshold |
|--------|--------|-------------------|
| Failed jobs count | `failed_jobs` table | > 100 in 1 hour |
| Queue wait time | Horizon dashboard | > 60 seconds |
| Worker memory | Supervisor logs | > 256 MB |
| Horizon uptime | `supervisorctl status` | < 99% over 24h |

### Sentry Integration (Already Configured)

Horizon automatically reports failed jobs to Sentry via existing `SENTRY_LARAVEL_DSN` config. No additional setup needed.

**Sample alert:**
```
Job Failed: App\Jobs\ReconcileRefundsJob
Queue: default
Attempts: 3/3
Error: Stripe API timeout (HTTP 504)
```

### Slack Notifications (Optional)

Uncomment in [`backend/app/Providers/HorizonServiceProvider.php`](../../backend/app/Providers/HorizonServiceProvider.php):
```php
Horizon::routeSlackNotificationsTo('slack-webhook-url', '#ops-alerts');
```

---

## 8. Decision Log

### Horizon vs. Custom Endpoints
**Decision**: Use Horizon  
**Rationale**: Redis driver confirmed. Horizon provides 95% of monitoring needs with zero maintenance. Custom endpoints only justified for database queue driver (not applicable here).  
**Rejected**: Building custom JSON API for failed jobs (3-5 hours development + ongoing maintenance).

### Security: Gate vs. Middleware
**Decision**: Gate-only (`viewHorizon` gate → `view-queue-monitoring` gate)  
**Rationale**: Horizon's internal gate check is sufficient. Adding `auth` middleware caused redirect errors in SPA architecture.  
**Rejected**: Custom middleware wrapping Horizon routes (adds complexity without benefit).

### Queue Separation: Default vs. Dedicated
**Decision**: 3 supervisors (default, emails, notifications)  
**Rationale**: Prevents email bursts from blocking time-sensitive notifications. Production booking systems require guaranteed notification delivery.  
**Rejected**: Single `default` queue (email spike blocks refund reconciliation).

### Observability: Sentry vs. CloudWatch
**Decision**: Sentry (already configured)  
**Rationale**: Existing Sentry integration captures failed jobs automatically. No additional setup.  
**Rejected**: AWS CloudWatch (requires new service + cost).

### Windows Development: Platform Requirements
**Decision**: Ignore ext-pcntl/ext-posix in Windows dev (use `--ignore-platform-req`)  
**Rationale**: Extensions Windows-incompatible but not needed for development (Horizon runs in foreground). Production Linux has extensions.  
**Rejected**: Running Horizon in WSL (adds Docker/VM overhead for minimal gain).

---

## 9. Common Issues & Solutions

### Issue: "Route [login] not defined" Error
**Cause**: Adding `auth` middleware to Horizon routes in config  
**Solution**: Remove `auth` from `config/horizon.php` middleware array. Horizon uses Gate-based auth.

### Issue: ext-pcntl Missing on Windows
**Cause**: Windows doesn't support PCNTL extension  
**Solution**: Use `composer require laravel/horizon --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix`  
**Note**: Extension required in production Linux. Don't commit `.gitignore` changes.

### Issue: Failed Jobs Not Retrying
**Cause**: Job `$tries` property conflicts with supervisor config  
**Solution**: Remove `$tries` property from Job class. Let supervisor config control retries:
```php
// BEFORE (conflicts with supervisor)
public $tries = 5;

// AFTER (uses supervisor config)
// Remove property entirely
```

### Issue: Horizon Dashboard Blank
**Cause**: Assets not published  
**Solution**: `php artisan horizon:install && php artisan vendor:publish --tag=horizon-assets`

---

## 10. Future Enhancements (Deferred)

**Not implementing now (LOW priority for 2-hour task):**

- [ ] Custom Horizon theme (default is fine)
- [ ] Queue priority tuning (no evidence of backlog yet)
- [ ] Auto-scaling workers based on queue depth (premature optimization)
- [ ] Laravel Telescope integration (separate tool, different purpose)
- [ ] Horizon metrics export to Prometheus (no Prometheus setup)

**When to revisit:**
- Job volume > 10,000/day → consider auto-scaling
- Multiple environments → custom Horizon names (`HORIZON_NAME` env var)
- Compliance requirements → add audit logging for failed job retries

---

## References

- [Laravel Horizon Docs](https://laravel.com/docs/11.x/horizon)
- [Supervisor Configuration](http://supervisord.org/configuration.html)
- [Gate Authorization (Laravel)](https://laravel.com/docs/11.x/authorization#gates)
- [Sentry Laravel Integration](https://docs.sentry.io/platforms/php/guides/laravel/)
