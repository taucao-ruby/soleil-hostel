# Operational Playbook

> Runbooks for common operational scenarios and incidents

## Overview

This playbook provides step-by-step procedures for handling operational events. Each runbook follows the format:

- **Scenario**: What happened?
- **Detection**: How do we know?
- **Impact**: What's affected?
- **Steps**: What to do?
- **Verification**: How to confirm resolution?
- **Post-mortem**: What to document?

---

## Quick Reference

| Scenario                                                    | Severity | Page                                 |
| ----------------------------------------------------------- | -------- | ------------------------------------ |
| [Application Down](#application-down)                       | Critical | [Jump](#application-down)            |
| [Database Connection Failure](#database-connection-failure) | Critical | [Jump](#database-connection-failure) |
| [Redis Down](#redis-down)                                   | High     | [Jump](#redis-down)                  |
| [High Error Rate](#high-error-rate)                         | High     | [Jump](#high-error-rate)             |
| [Rate Limit Breach](#rate-limit-breach)                     | Medium   | [Jump](#rate-limit-breach)           |
| [Email Delivery Failure](#email-delivery-failure)           | Medium   | [Jump](#email-delivery-failure)      |
| [Queue Backlog](#queue-backlog)                             | Medium   | [Jump](#queue-backlog)               |
| [Disk Space Full](#disk-space-full)                         | High     | [Jump](#disk-space-full)             |
| [Memory Exhaustion](#memory-exhaustion)                     | High     | [Jump](#memory-exhaustion)           |
| [Security Incident](#security-incident)                     | Critical | [Jump](#security-incident)           |
| [Double Booking Reported](#double-booking-reported)         | High     | [Jump](#double-booking-reported)     |
| [Slow Response Times](#slow-response-times)                 | Medium   | [Jump](#slow-response-times)         |
| [Failed Deployment Rollback](#failed-deployment-rollback)   | High     | [Jump](#failed-deployment-rollback)  |

---

## Critical Incidents

### Application Down

**Severity**: Critical  
**SLA**: Respond within 15 minutes

#### Detection

- Health check fails: `GET /api/health/live` returns non-200
- Uptime monitoring alert (UptimeRobot, Pingdom)
- User reports "site not loading"

#### Impact

- All users unable to access system
- Bookings cannot be made
- Revenue loss

#### Steps

1. **Check application status**

   ```bash
   # Check if PHP-FPM is running
   systemctl status php-fpm

   # Check if Nginx is running
   systemctl status nginx

   # Check Laravel logs
   tail -100 storage/logs/laravel.log
   ```

2. **Restart application**

   ```bash
   # Restart PHP-FPM
   sudo systemctl restart php-fpm

   # If using Octane
   php artisan octane:reload
   ```

3. **Check for deployment issues**

   ```bash
   # Recent deployments
   git log --oneline -5

   # If recent deploy broke it, rollback
   git checkout HEAD~1
   php artisan config:cache
   php artisan route:cache
   ```

4. **Check server resources**

   ```bash
   # CPU/Memory
   htop

   # Disk space
   df -h
   ```

#### Verification

```bash
curl -s https://your-domain.com/api/health/live
# Expected: {"status":"ok"}
```

#### Escalation

If not resolved in 30 minutes, escalate to senior developer/DevOps.

---

### Database Connection Failure

**Severity**: Critical  
**SLA**: Respond within 15 minutes

#### Detection

- Error: `SQLSTATE[HY000] [2002] Connection refused`
- Health check: `GET /api/health/ready` returns database unhealthy
- Application logs show PDOException

#### Impact

- All data operations fail
- Users see 500 errors
- Complete service outage

#### Steps

1. **Check PostgreSQL status**

   ```bash
   sudo systemctl status postgresql

   # Check if listening
   sudo netstat -tlnp | grep 5432
   ```

2. **Check connection from app server**

   ```bash
   # Test connection
   PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE -c "SELECT 1"
   ```

3. **Check database logs**

   ```bash
   sudo tail -100 /var/log/postgresql/postgresql-15-main.log
   ```

4. **Check connection pool**

   ```bash
   # If using PgBouncer
   sudo systemctl status pgbouncer

   # Check active connections
   psql -c "SELECT count(*) FROM pg_stat_activity"
   ```

5. **Restart database (last resort)**
   ```bash
   sudo systemctl restart postgresql
   ```

#### Verification

```bash
curl -s https://your-domain.com/api/health/ready | jq '.checks.database'
# Expected: "healthy"
```

#### Post-Mortem

- Was connection limit exceeded?
- Was there a network issue?
- Did the database run out of resources?

---

### Redis Down

**Severity**: High  
**SLA**: Respond within 30 minutes

#### Detection

- Warning: `Redis rate limiter failed, using fallback`
- Cache operations slow
- Health check shows Redis unhealthy

#### Impact

- Falls back to database cache (slower)
- Rate limiting less accurate
- Session storage may fail (if using Redis sessions)

#### Steps

1. **Check Redis status**

   ```bash
   sudo systemctl status redis
   redis-cli ping
   # Expected: PONG
   ```

2. **Check Redis memory**

   ```bash
   redis-cli info memory
   # Check used_memory_human
   ```

3. **Check for connection issues**

   ```bash
   redis-cli -h $REDIS_HOST -p $REDIS_PORT -a $REDIS_PASSWORD ping
   ```

4. **Restart Redis**

   ```bash
   sudo systemctl restart redis
   ```

5. **Clear cache if needed**
   ```bash
   php artisan cache:clear
   ```

#### Verification

```bash
redis-cli ping
# Expected: PONG

curl -s https://your-domain.com/api/health/ready | jq '.checks.redis'
```

#### Notes

- Application should continue working with degraded performance
- `RateLimitService` falls back to in-memory automatically
- `HasCacheTagSupport` trait handles graceful degradation

---

## High Severity Incidents

### High Error Rate

**Severity**: High  
**SLA**: Respond within 30 minutes

#### Detection

- Sentry alert: Error rate > 5%
- Grafana: 5xx responses spiking
- User complaints increasing

#### Steps

1. **Check error logs**

   ```bash
   tail -200 storage/logs/laravel.log | grep -i error

   # Or use artisan
   php artisan log:tail --lines=100
   ```

2. **Check Sentry for patterns**

   - Group by error type
   - Check if single endpoint or widespread
   - Look for common user actions

3. **Check recent changes**

   ```bash
   git log --oneline -10
   git diff HEAD~1 --stat
   ```

4. **Check external dependencies**

   - Database connectivity
   - Redis availability
   - Third-party APIs (Stripe, email)

5. **Enable debug mode temporarily** (if needed)

   ```bash
   # In .env (NEVER in production unless critical)
   APP_DEBUG=true

   # Remember to disable after debugging
   ```

#### Verification

Monitor error rate in Sentry/Grafana for 15 minutes after fix.

---

### Rate Limit Breach

**Severity**: Medium  
**SLA**: Respond within 1 hour

#### Detection

- Logs: `Rate limit exceeded for user X`
- Security alert: Unusual request patterns
- User complaint about being blocked

#### Steps

1. **Identify the source**

   ```bash
   grep "rate limit" storage/logs/laravel.log | tail -50
   ```

2. **Check if legitimate or attack**

   - Single IP hammering? Likely attack
   - Multiple users affected? Configuration issue
   - API client bug? Contact developer

3. **If attack - block IP**

   ```bash
   # Nginx
   sudo echo "deny 1.2.3.4;" >> /etc/nginx/conf.d/blocked.conf
   sudo nginx -t && sudo systemctl reload nginx

   # Or via iptables
   sudo iptables -A INPUT -s 1.2.3.4 -j DROP
   ```

4. **If legitimate - adjust limits**

   ```php
   // config/rate-limits.php
   'api' => [
       'max_attempts' => 120, // Increase if needed
       'decay_minutes' => 1,
   ],
   ```

5. **Clear rate limit for user**
   ```bash
   # Clear specific user's rate limit
   redis-cli DEL "rate:user:123:booking"
   ```

#### Verification

```bash
# Test as the affected user
curl -H "Authorization: Bearer $TOKEN" https://api/bookings
```

---

### Email Delivery Failure

**Severity**: Medium  
**SLA**: Respond within 2 hours

#### Detection

- Queue jobs failing with mail errors
- Users report not receiving confirmation emails
- Bounce rate increasing in mail provider

#### Steps

1. **Check failed jobs**

   ```bash
   php artisan queue:failed
   ```

2. **Check mail configuration**

   ```bash
   php artisan tinker
   >>> config('mail.default')
   >>> config('mail.mailers.smtp')
   ```

3. **Test mail sending**

   ```bash
   php artisan tinker
   >>> Mail::raw('Test', fn($m) => $m->to('test@example.com')->subject('Test'));
   ```

4. **Check mail provider dashboard**

   - SendGrid/Mailgun/SES
   - Look for bounces, complaints
   - Check API limits

5. **Retry failed jobs**

   ```bash
   # Retry all
   php artisan queue:retry all

   # Retry specific
   php artisan queue:retry 5
   ```

#### Verification

```bash
# Send test notification
php artisan tinker
>>> $booking = Booking::first();
>>> $booking->user->notify(new BookingConfirmed($booking));
```

---

### Queue Backlog

**Severity**: Medium  
**SLA**: Respond within 1 hour

#### Detection

- Queue size growing
- Notifications delayed
- Horizon dashboard shows pending jobs

#### Steps

1. **Check queue status**

   ```bash
   # If using Horizon
   php artisan horizon:status

   # Check Redis queue
   redis-cli LLEN queues:default
   redis-cli LLEN queues:notifications
   ```

2. **Check worker status**

   ```bash
   sudo supervisorctl status
   ```

3. **Scale up workers temporarily**

   ```bash
   # Add more workers
   sudo supervisorctl start soleil-worker:*

   # Or run manually
   php artisan queue:work --queue=notifications,default
   ```

4. **Check for stuck jobs**

   ```bash
   php artisan queue:failed
   php artisan queue:retry all
   ```

5. **Check for slow jobs**
   - Look at job execution times in logs
   - Consider breaking large jobs into smaller ones

#### Verification

```bash
# Queue should drain
redis-cli LLEN queues:default
# Expected: 0 or low number
```

---

## Infrastructure Incidents

### Disk Space Full

**Severity**: High  
**SLA**: Respond within 30 minutes

#### Detection

- Error: `No space left on device`
- `df -h` shows 100% usage
- Log writes failing

#### Steps

1. **Identify large directories**

   ```bash
   du -sh /* | sort -hr | head -20
   ```

2. **Clear logs** (temporary relief)

   ```bash
   # Rotate and clear old logs
   sudo truncate -s 0 /var/log/nginx/access.log
   php artisan log:clear

   # Or keep last 1000 lines
   tail -1000 storage/logs/laravel.log > /tmp/laravel.log
   mv /tmp/laravel.log storage/logs/laravel.log
   ```

3. **Clear cache**

   ```bash
   php artisan cache:clear
   php artisan view:clear
   php artisan config:clear
   ```

4. **Remove old backups/artifacts**

   ```bash
   # Find large files older than 7 days
   find /var/backups -mtime +7 -type f -delete
   ```

5. **Check for runaway logs**
   ```bash
   find /var/log -name "*.log" -size +100M
   ```

#### Verification

```bash
df -h
# Target: < 80% usage
```

---

### Memory Exhaustion

**Severity**: High  
**SLA**: Respond within 30 minutes

#### Detection

- OOM killer in logs
- Application becomes unresponsive
- `free -m` shows near-zero available

#### Steps

1. **Check memory usage**

   ```bash
   free -m
   htop
   ```

2. **Identify memory hogs**

   ```bash
   ps aux --sort=-%mem | head -20
   ```

3. **Restart PHP-FPM** (quick relief)

   ```bash
   sudo systemctl restart php-fpm
   ```

4. **Check for memory leaks**

   - Recent code changes?
   - Long-running processes?
   - Large file uploads being held in memory?

5. **If using Octane, restart workers**
   ```bash
   php artisan octane:reload
   ```

#### Prevention

```php
// For large data processing
DB::cursor()->each(function ($row) {
    // Process one at a time instead of loading all
});
```

---

## Security Incidents

### Security Incident

**Severity**: Critical  
**SLA**: Respond immediately

#### Detection

- Unusual login patterns
- Data access from unexpected IPs
- Suspicious API activity
- User reports account compromise

#### Immediate Actions

1. **Assess scope**

   - Single user or multiple?
   - Data accessed?
   - Ongoing or past?

2. **Contain**

   ```bash
   # Revoke all tokens for user
   php artisan tinker
   >>> User::find($id)->tokens()->delete();

   # Block suspicious IP
   sudo echo "deny 1.2.3.4;" >> /etc/nginx/conf.d/blocked.conf
   sudo nginx -t && sudo systemctl reload nginx
   ```

3. **Preserve evidence**

   ```bash
   # Copy logs
   cp storage/logs/laravel.log /var/backups/incident-$(date +%Y%m%d).log

   # Export database logs
   pg_dump -t audit_logs > /var/backups/audit-$(date +%Y%m%d).sql
   ```

4. **Investigate**

   - Review access logs
   - Check for unauthorized data access
   - Trace attack vector

5. **Notify** (if data breach)
   - Management
   - Legal/Compliance
   - Affected users (if required)

#### Post-Incident

- Rotate all secrets
- Force password reset if needed
- Update security measures
- Write incident report

---

## Application-Specific Incidents

### Double Booking Reported

**Severity**: High  
**SLA**: Respond within 1 hour

#### Detection

- Customer complaint
- Two bookings for same room/date
- Should be impossible with pessimistic locking

#### Steps

1. **Verify the overlap**

   ```bash
   php artisan tinker
   >>> Booking::where('room_id', $roomId)
   >>>    ->where('check_in', '<', $checkOut)
   >>>    ->where('check_out', '>', $checkIn)
   >>>    ->get(['id', 'check_in', 'check_out', 'created_at']);
   ```

2. **Check creation timestamps**

   - Were they created at the same time?
   - Different transactions?

3. **If genuine overlap**

   - Contact both guests immediately
   - Offer alternative room or compensation
   - Cancel one booking with full refund

4. **Investigate root cause**

   - Check logs for the time window
   - Was locking bypassed?
   - Database constraint missing?

5. **Add exclusion constraint** (if missing)
   ```sql
   ALTER TABLE bookings ADD CONSTRAINT no_overlap
   EXCLUDE USING gist (room_id WITH =, daterange(check_in, check_out) WITH &&)
   WHERE (status != 'cancelled');
   ```

---

### Slow Response Times

**Severity**: Medium  
**SLA**: Respond within 2 hours

#### Detection

- Health check: `GET /api/health/full` shows slow response
- User reports "site is slow"
- APM shows increased latency

#### Steps

1. **Check performance logs**

   ```bash
   grep "Slow request" storage/logs/performance.log
   ```

2. **Identify slow queries**

   ```bash
   grep "Slow query" storage/logs/query.log
   ```

3. **Check N+1 queries**

   ```bash
   # Look for repeated queries in logs
   grep "select" storage/logs/query.log | sort | uniq -c | sort -rn | head
   ```

4. **Check cache hit rate**

   ```bash
   redis-cli info stats | grep keyspace
   ```

5. **Check database performance**

   ```sql
   -- Long-running queries
   SELECT pid, now() - pg_stat_activity.query_start AS duration, query
   FROM pg_stat_activity
   WHERE state != 'idle'
   ORDER BY duration DESC
   LIMIT 10;
   ```

6. **Clear and warm cache**
   ```bash
   php artisan cache:clear
   # Hit common endpoints to warm cache
   curl https://api/rooms
   ```

---

### Failed Deployment Rollback

**Severity**: High  
**SLA**: Respond within 15 minutes

#### Detection

- Health checks fail after deployment
- Error rate spikes
- Users reporting issues

#### Steps

1. **Quick rollback**

   ```bash
   # If using deploy script
   php artisan down --message="Maintenance in progress"

   # Revert to previous release
   git checkout HEAD~1

   # Rebuild caches
   composer install --optimize-autoloader
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache

   php artisan up
   ```

2. **If database migration issue**

   ```bash
   php artisan migrate:rollback --step=1
   ```

3. **If using Forge/Envoyer**

   - One-click rollback in dashboard
   - Reverts symlink to previous release

4. **Verify rollback**

   ```bash
   curl -s https://your-domain.com/api/health/live
   ```

5. **Investigate before retrying deployment**
   - What broke?
   - Local testing missed issue?
   - CI/CD tests incomplete?

---

## Preventive Measures

### Daily Checks

```bash
# Health endpoints
curl https://api/health/live
curl https://api/health/ready

# Disk space
df -h

# Queue status
php artisan horizon:status
```

### Weekly Checks

```bash
# Database size
psql -c "SELECT pg_size_pretty(pg_database_size('soleil_hostel'))"

# Slow query review
grep "Slow query" storage/logs/query.log | wc -l

# Failed job count
php artisan queue:failed | wc -l
```

### Monthly Checks

- Review Sentry error trends
- Check backup restoration works
- Rotate secrets/API keys
- Security dependency audit

---

## Contact Information

| Role             | Name     | Contact                   |
| ---------------- | -------- | ------------------------- |
| On-Call Engineer | Rotation | oncall@soleilhostel.com   |
| DevOps Lead      | TBD      | devops@soleilhostel.com   |
| Security         | TBD      | security@soleilhostel.com |

---

## Related Documentation

- [MONITORING_LOGGING.md](./backend/guides/MONITORING_LOGGING.md)
- [KNOWN_LIMITATIONS.md](./KNOWN_LIMITATIONS.md)
- [DEPLOYMENT.md](./backend/guides/DEPLOYMENT.md)
