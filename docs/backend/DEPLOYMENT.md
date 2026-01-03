# Deployment Runbook – Soleil Hostel

> **Purpose**: Step-by-step deployment procedure for production releases.  
> **Audience**: On-call engineer, DevOps, backend team.  
> **Last Updated**: 2026-01-03

---

## Assumptions

- Deployment target: Docker containers (backend + queue workers)
- Database: MySQL or PostgreSQL with Laravel migrations
- Cache/Queue: Redis
- CI/CD: GitHub Actions (or manual fallback)
- Zero-downtime goal via rolling container replacement
- Git tags used for releases (e.g., `v1.2.3`)

---

## Pre-Deployment Checklist

Complete **ALL** items before proceeding:

| #   | Check                                                          | Status |
| --- | -------------------------------------------------------------- | ------ |
| 1   | All tests pass on `main` branch                                | ☐      |
| 2   | Migration files reviewed (no destructive changes without plan) | ☐      |
| 3   | Current production DB backup completed (< 1 hour old)          | ☐      |
| 4   | Redis queue is drained or low (< 100 pending jobs)             | ☐      |
| 5   | Release tag created and pushed (e.g., `v1.2.3`)                | ☐      |
| 6   | Health check endpoint responding: `GET /api/health`            | ☐      |
| 7   | Rollback plan reviewed (see ROLLBACK.md)                       | ☐      |
| 8   | Team notified in Slack/Discord (#deployments)                  | ☐      |

### STOP Condition

> ❌ **DO NOT DEPLOY** if any pre-check fails.  
> Fix the issue first or escalate.

---

## Deployment Steps

### Step 1: Enable Maintenance Mode (Optional)

Only if deployment includes breaking changes or long migrations:

```bash
# On production server / container
php artisan down --retry=60 --secret="emergency-bypass-token"
```

> 💡 Access site with `?secret=emergency-bypass-token` to bypass maintenance mode.

---

### Step 2: Pull Latest Code

```bash
# SSH into production or trigger via CI
cd /var/www/soleil-hostel
git fetch --all --tags
git checkout tags/v1.2.3  # Replace with actual tag
```

Or via Docker:

```bash
docker pull your-registry/soleil-backend:v1.2.3
```

---

### Step 3: Install Dependencies

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

> ⚠️ If composer fails, **STOP**. Do not proceed. Check `composer.lock` consistency.

---

### Step 4: Run Migrations (CRITICAL)

**Before running:**

```bash
# Preview migrations
php artisan migrate:status
```

Verify:

- [ ] No pending migrations that DROP columns/tables
- [ ] If destructive migration exists, confirm data backup

**Run migrations:**

```bash
php artisan migrate --force
```

**If migration fails:**

> ⛔ **STOP DEPLOYMENT IMMEDIATELY**  
> See ROLLBACK.md → "Handling Failed Migrations"

---

### Step 5: Clear & Warm Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

### Step 6: Restart Queue Workers

```bash
php artisan queue:restart
```

> Workers will gracefully finish current job, then restart with new code.

If using Supervisor:

```bash
supervisorctl restart soleil-worker:*
```

If using Docker:

```bash
docker-compose restart queue-worker
```

---

### Step 7: Restart Application Server

For Laravel Octane:

```bash
php artisan octane:reload
```

For Docker:

```bash
docker-compose up -d --no-deps backend
```

> Container health check must pass before traffic is routed.

---

### Step 8: Disable Maintenance Mode

```bash
php artisan up
```

---

## Post-Deployment Verification

Complete **ALL** checks within 5 minutes of deployment:

| #   | Verification          | Command / Action                         | Expected Result               | Status |
| --- | --------------------- | ---------------------------------------- | ----------------------------- | ------ |
| 1   | Health check          | `curl https://api.soleil.com/api/health` | `{"status":"healthy"}` 200 OK | ☐      |
| 2   | Database connectivity | Health check includes DB                 | `"database":"connected"`      | ☐      |
| 3   | Redis connectivity    | Health check includes Redis              | `"redis":"connected"`         | ☐      |
| 4   | Queue processing      | Create test job or check Horizon         | Jobs processing               | ☐      |
| 5   | Booking sanity        | Create test booking via API/UI           | Booking created, no errors    | ☐      |
| 6   | Error logs clean      | `tail -f storage/logs/laravel.log`       | No new exceptions             | ☐      |
| 7   | Response times normal | Check APM or manual timing               | < 500ms for booking endpoints | ☐      |

### Booking Sanity Check (Manual)

```bash
# Quick API test - adjust endpoint as needed
curl -X POST https://api.soleil.com/api/bookings/availability \
  -H "Content-Type: application/json" \
  -d '{"room_id": 1, "check_in": "2026-02-01", "check_out": "2026-02-03"}'
```

Expected: Valid availability response, no 500 errors.

---

## STOP & ABORT Conditions

Immediately trigger rollback (see ROLLBACK.md) if:

| Condition                                   | Action                                |
| ------------------------------------------- | ------------------------------------- |
| Health check returns non-200 for > 1 minute | **ROLLBACK**                          |
| Error rate > 5% in first 5 minutes          | **ROLLBACK**                          |
| Any booking endpoint returns 500            | **ROLLBACK**                          |
| Double-booking detected                     | **ROLLBACK + INCIDENT**               |
| Queue workers crash-looping                 | **ROLLBACK**                          |
| Migration failed mid-execution              | **ROLLBACK** (see migration recovery) |

---

## Deployment Rollback Summary

If any abort condition is met:

1. **Do not try to fix forward** under time pressure
2. Follow ROLLBACK.md immediately
3. Investigate root cause **after** system is stable

---

## Emergency Contacts

| Role           | Contact       |
| -------------- | ------------- |
| Backend Lead   | [Add contact] |
| DevOps         | [Add contact] |
| Database Admin | [Add contact] |

---

## Appendix: Docker Compose Deployment

If deploying via docker-compose:

```bash
# Pull new images
docker-compose pull

# Rolling update (zero-downtime if health checks configured)
docker-compose up -d --no-deps --build backend

# Verify
docker-compose ps
docker-compose logs -f --tail=100 backend
```

---

## Appendix: CI/CD Auto-Deployment

If GitHub Actions handles deployment:

1. Push tag: `git tag v1.2.3 && git push origin v1.2.3`
2. Monitor Actions workflow
3. If workflow fails → manual deployment or rollback
4. Complete post-deployment verification regardless of automation

---

_Document maintained by: Backend Team_  
_Review quarterly or after any deployment incident._
