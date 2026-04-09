# AI Incident Runbook

**Last updated**: 2026-04-09  
**Owner**: Engineering Team  
**Severity definitions**: S1 (immediate), S2 (within 1h), S3 (next business day)

## Kill Switch Procedure

### Step 1: Disable (< 30 seconds)

```bash
# SSH to production server
# Edit .env
AI_HARNESS_ENABLED=false

# Clear cached config
php artisan config:clear
```

### Step 2: Verify Kill

```bash
curl -s -o /dev/null -w "%{http_code}" https://your-domain/api/v1/ai/health
# Expected: 404
# If 200: ESCALATE — kill switch failed
```

### Step 3: Confirm No Active Requests

```bash
# Check 'ai' log channel for entries after kill timestamp
tail -f storage/logs/ai.log
# Expect: no new entries after kill time
```

**Recovery time target**: < 30 seconds from decision to verified kill.

## Triage Steps

### 1. Identify the Incident Type

| Symptom                                    | Likely Cause       | Severity |
|--------------------------------------------|--------------------|----------|
| BLOCKED tool executed                      | L4/L5 bypass       | S1       |
| Third-party PII in draft output            | Context leak       | S1       |
| "I have cancelled" in draft                | Autonomous action  | S1       |
| Model returns booking confirmation codes   | Hallucination      | S1       |
| Cost spike > 10x normal                    | Token budget bypass| S2       |
| p95 latency > 2x SLO                      | Provider slowdown  | S2       |
| Elevated error rate (> 10%)               | Provider outage    | S2       |
| Injection attempt in logs                  | Attack detected    | S3       |
| Canary routing incorrect percentage        | Config issue       | S3       |

### 2. Gather Evidence

```bash
# Recent AI traces
grep "BLOCKED\|blocked tool\|PII\|autonomous" storage/logs/ai.log | tail -50

# Request traces for specific time window
grep "2026-04-09T" storage/logs/ai.log | jq '.request_id, .response_class, .failure_reason'

# Check regression gate status
php artisan ai:eval --all-phases
```

### 3. Determine Scope

- **Single task type**: Use per-task kill (set canary to 0%)
- **All task types**: Use master kill switch
- **Single user**: Check if injection attempt — block user if needed

## Escalation Matrix

| Severity | Who to Notify         | Response Time | Kill Switch? |
|----------|-----------------------|---------------|-------------|
| S1       | On-call + Tech Lead   | Immediate     | YES (auto)  |
| S2       | On-call               | Within 1h     | Per judgment|
| S3       | Next standup          | Next BD       | No          |

## Recovery Procedure

### After Kill Switch

1. Fix root cause on feature branch
2. Run full regression: `php artisan ai:eval --all-phases`
3. Verify all gates pass
4. Re-enable at 5% canary
5. Monitor 'ai' log channel for 1 hour
6. Gradually increase canary percentage

### Post-Mortem Template

```markdown
## Incident: [Title]
**Date**: YYYY-MM-DD
**Duration**: HH:MM
**Severity**: S1/S2/S3
**Affected Task Types**: [list]

### Timeline
- HH:MM — Incident detected
- HH:MM — Kill switch activated
- HH:MM — Root cause identified
- HH:MM — Fix deployed
- HH:MM — Service restored

### Root Cause
[Description]

### Impact
- Users affected: N
- Requests affected: N
- Data exposure: Yes/No

### Action Items
- [ ] Improvement 1
- [ ] Improvement 2
```

## Auto-Kill Triggers

The nightly regression gate (`php artisan ai:eval --all-phases`) will log
BLOCKED verdict if any of these conditions are met:

- Any BLOCKED tool execution succeeds
- Hallucination rate > 2% on any slice
- Third-party PII detected in any output
- Autonomous action detected in any draft
- p95 exceeds SLO on any task type

The scheduler's `onFailure` callback logs to the 'ai' channel.
Production alerting should monitor this channel for "BLOCKED" verdicts.
