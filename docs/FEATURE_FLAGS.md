# Feature Flags

**Last updated**: 2026-05-03 (Batch 8 platform hardening)

Soleil Hostel uses Redis-backed runtime flags to flip features without redeploying. The `App\Services\FeatureFlag` facade exposes two read variants with intentionally different failure semantics. Picking the wrong one is a real bug — read this before adding a new flag or migrating an existing one.

## Two flag flavours

| Variant | Method | Redis miss | Redis down | Fallback to config? | Use when |
|---------|--------|------------|------------|---------------------|----------|
| Soft toggle | `FeatureFlag::get($key, $default)` | `$default` | `$default` | yes — caller-supplied | feature should keep behaving "the normal way" through a Redis blip |
| Hard kill switch | `FeatureFlag::killSwitch($key)` | `false` | `false` | **no** | feature must stay OFF unless someone explicitly authorised it |

Both share the same Redis key prefix (`feature:`), the same `php artisan feature:toggle` operator UX, and the same 30-second local cache TTL. They differ only in what they treat as the *safe answer when uncertain*.

## When to choose `killSwitch()` (sticky-off)

Use `killSwitch()` when **the absence of a positive affirmation is itself the safe state**. Concretely:

- The feature is dangerous-by-default and must be opt-in (e.g. an experimental AI surface).
- An operator-set runtime disable must survive infrastructure failure. If Redis goes down while the flag is OFF, you do **not** want it to silently flip back ON via a config default.
- You are willing to require an explicit `feature:toggle <key> on` to bring the feature up after a fresh deploy or a Redis restore.

`killSwitch()` does **not** consult `config()`. There is no fallback. If Redis is unreachable, `killSwitch()` returns `false` — the feature is off until Redis recovers and the operator confirms the desired state.

**Current killSwitch flags**

| Flag | Owner | Purpose |
|------|-------|---------|
| `ai_harness.enabled` | AI Platform | Master kill switch for the AI harness surface. Must be ON in Redis for `/api/ai/*` routes to respond; otherwise the middleware returns 404. Migrated to `killSwitch()` in Batch 8 because the prior `get()` variant could resurrect the harness via the env default during a Redis outage, defeating the operator-flipped runtime disable. |

When you add a new killSwitch flag, append a row to this table and document the operator command in `docs/ROLLOUT_AND_KILL_SWITCH.md`.

## When to choose `get()` (soft toggle)

Use `get()` for operational toggles where Redis disappearing should not change behaviour. Pattern: feature defaults to a known-safe state and the flag is used to flip it for a specific operational reason.

**Current soft-toggle flags**

| Flag | Default | Owner | Purpose |
|------|---------|-------|---------|
| `booking.expire_pending` | `true` | Booking | Pending-booking TTL expiry job. Defaults to ON so the scheduler keeps draining stale rows; operators flip it OFF during incident response if expiry is itself the source of pain. Soft semantics: a Redis blip during normal operation should not stall the queue. |

### Soft-toggle drift risk

A soft toggle whose default is ON has a known limitation: an operator-set OFF will *not* survive a Redis outage. If you need both "default ON for normal traffic" **and** "operator-set OFF must stick through Redis loss", layer a kill switch in front of the soft toggle:

```php
// Halt flag is sticky-off (defaults to false = "not halted").
// An operator sets `booking.expire_pending.halt` ON to override the soft toggle
// regardless of Redis health. The soft toggle still controls the everyday on/off.
if (FeatureFlag::killSwitch('booking.expire_pending.halt')) {
    return;
}

if (! FeatureFlag::get('booking.expire_pending', true)) {
    return;
}
```

This composition gives you: default ON, soft OFF survives most reads, hard halt survives Redis loss.

## Local-cache semantics

Both variants cache the resolved Redis state in `Cache` for 30 seconds (`LOCAL_CACHE_TTL_SECONDS`). One important asymmetry:

- `get()` caches the `$default`-equivalent answer when Redis is unreachable. That is acceptable because soft toggles are deliberately tolerant of Redis hiccups.
- `killSwitch()` deliberately does **not** cache the absent/unreachable result. Smearing a transient Redis outage across the cache TTL would mean the kill switch stays "off" for up to 30 extra seconds *after* Redis recovers — exactly the opposite of what an operator wants when re-enabling the flag mid-incident.

Cross-instance convergence: any node whose local cache has expired sees a Redis change within `LOCAL_CACHE_TTL_SECONDS` (≤ 31s by contract, asserted in `FeatureFlagTest`).

## Operator commands

```bash
# Inspect (artisan)
php artisan feature:toggle ai_harness.enabled status

# Enable (sets feature:ai_harness.enabled to "on")
php artisan feature:toggle ai_harness.enabled on

# Disable (sets feature:ai_harness.enabled to "off")
php artisan feature:toggle ai_harness.enabled off

# Auto-expiring temporary disable (TTL in seconds)
php artisan feature:toggle ai_harness.enabled off --ttl=900
```

Behaviour after each operator action is identical for both flavours; the difference is what each returns when Redis is unreachable, not what `feature:toggle` writes.

## Adding a new flag

1. Pick the flavour using the table above.
2. Add an entry to **either** the killSwitch or the soft-toggle table in this document.
3. If the flag has incident-response implications, add the operator runbook entry to `docs/ROLLOUT_AND_KILL_SWITCH.md`.
4. Cover the new flag in a feature test that exercises both the ON and OFF paths.
5. For killSwitch flags only: add a deploy step that sets the initial state (`feature:toggle <key> on`) so production traffic is not surprised by the sticky-off default.

## Cross-references

- Service: `backend/app/Services/FeatureFlag.php`
- Tests: `backend/tests/Feature/FeatureFlagTest.php`
- Operator command: `backend/app/Console/Commands/FeatureToggleCommand.php`
- AI harness gating: `backend/app/AiHarness/Middleware/AiHarnessEnabled.php`
- Booking expiry job: `backend/app/Jobs/ExpireStaleBookings.php`
- AI rollout / kill-switch playbook: `docs/ROLLOUT_AND_KILL_SWITCH.md`
