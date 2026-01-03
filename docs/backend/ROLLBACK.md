# Rollback Runbook – Soleil Hostel

> **Purpose**: Emergency rollback procedure when deployment fails.  
> **Goal**: Restore stable state in < 5 minutes.  
> **Audience**: On-call engineer, anyone with production access.  
> **Last Updated**: 2026-01-03

---

## Assumptions

- Previous stable release tag is known (e.g., `v1.2.2`)
- Database backup exists from before deployment
- Docker images for previous version are available in registry
- You have SSH/console access to production

---

## When to Rollback

**Mandatory rollback triggers:**

| Trigger                                            | Severity    |
| -------------------------------------------------- | ----------- |
| Health check failing > 1 minute                    | 🔴 Critical |
| Booking endpoints returning 500 errors             | 🔴 Critical |
| Double-booking or data corruption detected         | 🔴 Critical |
| Error rate > 5% post-deployment                    | 🔴 Critical |
| Queue workers crash-looping                        | 🟠 High     |
| Migration failed mid-execution                     | 🟠 High     |
| Significant performance degradation (> 2x latency) | 🟠 High     |

> ⚠️ **Rule**: If in doubt, rollback first, investigate later.  
> A 5-minute rollback is cheaper than a 30-minute outage.

---

## Rollback Decision Tree

```
Deployment failed?
    │
    ├─ Migration ran successfully?
    │   ├─ YES → Code-only rollback (Step A)
    │   └─ NO  → Code + Migration rollback (Step B)
    │
    └─ Is data corrupted?
        ├─ YES → Database restore required (Step C)
        └─ NO  → Proceed with code rollback
```

---

## Step A: Code-Only Rollback (No Migration Issues)

**Use when**: New code is broken, but migrations ran fine and are backward-compatible.

**Time estimate**: 2-3 minutes

### A1. Stop Queue Workers

```bash
php artisan queue:restart
# Or
supervisorctl stop soleil-worker:*
# Or
docker-compose stop queue-worker
```

### A2. Revert to Previous Release

**Git method:**

```bash
cd /var/www/soleil-hostel
git fetch --all --tags
git checkout tags/v1.2.2  # Previous stable tag
composer install --no-dev --optimize-autoloader
```

**Docker method:**

```bash
docker pull your-registry/soleil-backend:v1.2.2
docker-compose up -d --no-deps backend
```

### A3. Clear Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### A4. Restart Services

```bash
# Octane
php artisan octane:reload

# Or Docker
docker-compose restart backend

# Restart queue workers
php artisan queue:restart
supervisorctl start soleil-worker:*
```

### A5. Verify (see Post-Rollback Checklist below)

---

## Step B: Code + Migration Rollback

**Use when**: Migration was applied but needs to be reversed.

**Time estimate**: 3-5 minutes

> ⚠️ Only works if migration has a proper `down()` method.

### B1. Identify Migrations to Rollback

```bash
php artisan migrate:status
```

Note the batch number of newly applied migrations.

### B2. Rollback Migrations

```bash
# Rollback last batch
php artisan migrate:rollback --step=1

# Or rollback specific number of migrations
php artisan migrate:rollback --step=3
```

### B3. Verify Migration State

```bash
php artisan migrate:status
```

Confirm unwanted migrations are no longer "Ran".

### B4. Proceed with Code Rollback (Step A)

Follow Steps A1-A5 above.

---

## Step C: Database Restore (Data Corruption)

**Use when**: Data is corrupted and migration rollback is insufficient.

**Time estimate**: 10-30 minutes (depends on DB size)

> 🔴 **CRITICAL**: This causes data loss for any changes since backup.  
> Only use as last resort.

### C1. Enable Maintenance Mode

```bash
php artisan down --retry=60
```

### C2. Stop All Write Operations

```bash
# Stop queue workers
supervisorctl stop soleil-worker:*

# Stop web containers accepting writes
docker-compose stop backend
```

### C3. Restore Database

**MySQL:**

```bash
mysql -u root -p soleil_hostel < /backups/soleil_backup_YYYYMMDD_HHMM.sql
```

**PostgreSQL:**

```bash
pg_restore -U postgres -d soleil_hostel /backups/soleil_backup_YYYYMMDD_HHMM.dump
```

### C4. Verify Database State

```bash
php artisan migrate:status
php artisan tinker
# > \App\Models\Booking::count()
# > \App\Models\Room::count()
```

### C5. Deploy Previous Stable Version

Follow Step A (Code-Only Rollback).

### C6. Disable Maintenance Mode

```bash
php artisan up
```

### C7. Incident Report Required

Any database restore requires a post-incident review.

---

## Handling Partially Applied Migrations

**Scenario**: Migration failed mid-way, database is in inconsistent state.

### Diagnosis

```bash
php artisan migrate:status
```

Look for:

- Migrations marked as "Ran" but tables/columns missing
- Errors when running `migrate:rollback`

### Recovery Options

**Option 1: Manual SQL Fix**

```bash
# Connect to database
mysql -u root -p soleil_hostel

# Manually complete or revert the partial migration
# Example: drop partially created table
DROP TABLE IF EXISTS failed_table_name;

# Remove migration record
DELETE FROM migrations WHERE migration = '2026_01_03_failed_migration';
```

**Option 2: Restore from Backup**
If manual fix is risky, restore database (Step C).

---

## Queue / Job Safety

### Before Rollback

Check pending jobs:

```bash
php artisan queue:monitor redis:default,redis:bookings
# Or check Redis directly
redis-cli LLEN queues:default
```

### During Rollback

Jobs in progress will complete with **old code** after worker restart.

**Critical job types to watch:**

- `ProcessBooking` – may fail if schema changed
- `SendConfirmationEmail` – safe, no schema dependency
- `SyncInventory` – may fail if API changed

### After Rollback

Check for failed jobs:

```bash
php artisan queue:failed
```

Retry or delete as appropriate:

```bash
# Retry all
php artisan queue:retry all

# Delete all (if jobs are incompatible with rolled-back code)
php artisan queue:flush
```

---

## Post-Rollback Verification Checklist

Complete **ALL** within 5 minutes:

| #   | Check                 | Command                         | Expected                 | Status |
| --- | --------------------- | ------------------------------- | ------------------------ | ------ |
| 1   | Health endpoint       | `curl /api/health`              | 200 OK, all healthy      | ☐      |
| 2   | Database connected    | Health check response           | `"database":"connected"` | ☐      |
| 3   | Redis connected       | Health check response           | `"redis":"connected"`    | ☐      |
| 4   | Queue workers running | `supervisorctl status`          | RUNNING                  | ☐      |
| 5   | No new errors         | `tail storage/logs/laravel.log` | No exceptions            | ☐      |
| 6   | Booking test          | Manual booking via API          | Success                  | ☐      |
| 7   | Current git tag       | `git describe --tags`           | Previous stable tag      | ☐      |

### Quick Health Check

```bash
curl -s https://api.soleil.com/api/health | jq .
```

Expected:

```json
{
  "status": "healthy",
  "database": "connected",
  "redis": "connected",
  "queue": "running"
}
```

---

## Post-Rollback Actions

| Action                  | Owner        | Deadline           |
| ----------------------- | ------------ | ------------------ |
| Notify team of rollback | On-call      | Immediate          |
| Document what failed    | On-call      | Within 1 hour      |
| Create incident ticket  | On-call      | Within 1 hour      |
| Root cause analysis     | Backend lead | Within 24 hours    |
| Fix and re-test         | Developer    | Before next deploy |

---

## Rollback Cheat Sheet (Emergency Quick Reference)

```bash
# 1. Stop workers
php artisan queue:restart

# 2. Checkout previous version
git checkout tags/v1.2.2  # <-- change to actual previous tag
composer install --no-dev --optimize-autoloader

# 3. Clear caches
php artisan config:cache && php artisan route:cache

# 4. Restart app
php artisan octane:reload  # or: docker-compose restart backend

# 5. Restart workers
supervisorctl start soleil-worker:*

# 6. Verify
curl https://api.soleil.com/api/health
```

---

## Emergency Contacts

| Role           | Contact       |
| -------------- | ------------- |
| Backend Lead   | [Add contact] |
| DevOps         | [Add contact] |
| Database Admin | [Add contact] |
| Escalation     | [Add contact] |

---

## Known Rollback Limitations

| Limitation                         | Mitigation                         |
| ---------------------------------- | ---------------------------------- |
| Migrations without `down()` method | Always write reversible migrations |
| Data inserted by new code          | May need manual cleanup            |
| External API changes               | Coordinate with third parties      |
| Cache with new schema              | Clear all caches after rollback    |

---

_Document maintained by: Backend Team_  
_Test rollback procedure quarterly in staging environment._
