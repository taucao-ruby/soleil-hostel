# AI Rollout & Kill Switch Procedure

**Last updated**: 2026-04-18

> Scope note: this document covers the AI harness rollout/kill switch and — as of 2026-04-18 — the pending-booking TTL implicit kill switch. Deploy-level safety (F-04 pre-flight gate, migration-before-health ordering) lives in `docs/OPERATIONAL_PLAYBOOK.md`.

## Canary Rollout

### Per-Task-Type Canary Percentages

| Task Type       | Env Variable                   | Default | Current |
|-----------------|-------------------------------|---------|---------|
| faq_lookup      | AI_CANARY_FAQ_LOOKUP_PCT      | 5%      | 5%      |
| room_discovery  | AI_CANARY_ROOM_DISCOVERY_PCT  | 5%      | 5%      |
| admin_draft     | AI_CANARY_ADMIN_DRAFT_PCT     | 0%      | 0%      |

### Rollout Stages

1. **0%**: Feature flag enabled, canary at 0% — no traffic
2. **5%**: Initial canary — monitor error rates, latency, cost
3. **25%**: Expanded canary — validate at scale
4. **50%**: Half traffic — final validation
5. **100%**: Full rollout

### Rollout Criteria

Before increasing canary percentage:
- [ ] `php artisan ai:eval --phase=N` passes all gates
- [ ] No BLOCKED tool executions in production logs
- [ ] p95 latency within SLO for task type
- [ ] Cost per request within budget ($0.05)
- [ ] No PII leaks detected in 'ai' log channel
- [ ] For admin_draft: manual rubric score ≥ 4/5 on 10 samples

## Kill Switch Procedure

### Immediate Kill (< 30 seconds)

**Step 1**: Set feature flag to false
```bash
# In .env file
AI_HARNESS_ENABLED=false
```

**Step 2**: Clear config cache
```bash
php artisan config:clear
```

**Step 3**: Verify kill
```bash
curl -s https://your-domain/api/v1/ai/health
# Expected: 404 (not found)
# If 200: kill switch failed — escalate immediately
```

**Target recovery time**: < 30 seconds from decision to verified kill.

### Per-Task Kill

To disable a single task type without killing the entire harness:
```bash
# Set canary percentage to 0 for the specific task
AI_CANARY_ADMIN_DRAFT_PCT=0
AI_CANARY_FAQ_LOOKUP_PCT=0
AI_CANARY_ROOM_DISCOVERY_PCT=0
```

Then clear config:
```bash
php artisan config:clear
```

### Verification Checklist

After kill switch activation:
- [ ] `/api/v1/ai/health` returns 404
- [ ] `/api/v1/ai/faq_lookup` returns 404 (if master kill)
- [ ] No new entries in 'ai' log channel after kill time
- [ ] Existing request traces show clean cutoff

## Rollback Procedure

### From Canary Issues

1. Set canary percentage to 0 for affected task type
2. Clear config cache
3. Investigate logs in 'ai' channel
4. Fix issue on feature branch
5. Re-run `php artisan ai:eval --phase=N`
6. Re-enable canary at 5%

### From Production Incident

1. **Immediate**: Execute kill switch procedure (above)
2. **Triage**: Check 'ai' log channel for:
   - Blocked tool execution attempts
   - PII leaks
   - Autonomous action claims
   - Elevated error rates
3. **Root cause**: Identify failing layer (L1–L7)
4. **Fix**: Patch on feature branch
5. **Validate**: Run full regression gate `php artisan ai:eval --all-phases`
6. **Re-enable**: Gradual rollout from 5%

## ABORT Conditions

Any of the following trigger **immediate rollback** and severity-1 bug filing:

- Autonomous action detected in draft output
- BLOCKED tool execution succeeds
- Third-party PII leakage confirmed
- Model bypasses policy enforcement layer
- Cost exceeds 10x threshold in any 1-hour window
- Proposer-binding mismatch spike (`Proposal decide blocked by proposer-binding check` log events, `ai` channel) — potential hash-replay or cross-user confirmation attempt (see `docs/THREAT_MODEL_AI.md` T-13 / V-5)

## Pending-Booking TTL Implicit Kill Switch (Non-AI)

The `ExpireStaleBookings` job (`backend/app/Jobs/ExpireStaleBookings.php`, scheduled every 5 minutes) auto-cancels pending bookings older than `config('booking.pending_ttl_minutes')` (default 30). Setting the TTL to 0 disables the job without removing the schedule: the job logs a warning and returns.

This is NOT part of the AI harness kill chain, but it is the fastest way to pause pending-booking expiry during an incident (e.g. to debug a claim that legitimate pending bookings are being cancelled prematurely).

### To pause expiry

```bash
# .env
BOOKING_PENDING_TTL_MINUTES=0
```

```bash
php artisan config:clear
```

### Verify

```bash
grep "ExpireStaleBookings skipped: non-positive TTL configured" storage/logs/laravel.log | tail -5
```

### To resume

Restore `BOOKING_PENDING_TTL_MINUTES=30` (or the value in `.env.example`), then `php artisan config:clear`. Run the job once by hand to drain the backlog that accumulated while paused:

```bash
php artisan tinker
>>> dispatch_sync(new App\Jobs\ExpireStaleBookings);
```

See `docs/OPERATIONAL_PLAYBOOK.md` §Pending Booking Backlog for the full incident runbook and `docs/agents/ARCHITECTURE_FACTS.md` §Pending TTL for the invariant.
