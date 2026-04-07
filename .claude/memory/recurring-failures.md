# Recurring Failures — Soleil Hostel

Patterns with evidence of recurrence. Each must help the next agent avoid a known trap.

## Stable Memory

### Docs Drift After Code Changes
- Route paths, middleware assignments, and permission mappings in docs go stale after backend changes
- PERMISSION_MATRIX.md has 5 open follow-ups (FU-1 through FU-5) from verification audits showing test/endpoint drift
- Mitigation: run docs-sync agent after any route or middleware change
  - Source: `docs/PERMISSION_MATRIX.md` § Follow-ups, `.claude/commands/sync-docs.md`

### Frontend ↔ Backend Contract Drift
- Admin/moderator UI surfaces drift from backend endpoints after endpoint changes
- FU-2: `restore-bulk` has no moderator-denial test; `trashed/{id}` has no v1 pin test
- FU-5: Room CUD auth tests still target legacy `/api/rooms/*` instead of `/api/v1/rooms/*`
  - Source: `docs/PERMISSION_MATRIX.md` FU-2, FU-5

### SQLite Test Masking
- SQLite tests pass but miss PostgreSQL-specific constraint behavior (EXCLUDE USING gist, btree_gist, triggers)
- The `no_overlapping_bookings` exclusion constraint is PostgreSQL-only — SQLite cannot enforce it
- Mitigation: booking-domain tests must run against PostgreSQL
  - Source: `.agent/rules/migration-safety.md`, `docs/DB_FACTS.md` § AI Rules

### Restore-Overlap TOCTOU
- Soft-delete restore can reintroduce booking conflicts if overlap check is not inside the same transaction with lock
- Known: `finalizeCancellation()` lacks `lockForUpdate()` (F-33 in FINDINGS_BACKLOG)
  - Source: `docs/FINDINGS_BACKLOG.md` F-33, `.agent/rules/booking-integrity.md`

### Agent Overclaiming
- Agents assert runtime truth (e.g., "CSRF works correctly") from repo evidence alone
- Repo evidence proves code exists, not that it runs correctly in production
- Mitigation: tag all runtime claims as `[UNPROVEN]` unless tested
  - Source: output style contracts in `.claude/output-styles/`

### Build/Test Fix → Docs Skip
- After fixing build or test failures, agents skip docs sync and contract verification
- Mitigation: CONTRACT.md DoD requires COMPACT.md update after all code changes
  - Source: `docs/agents/CONTRACT.md` § DoD: Code Changes

### Security Reviews Missing Business Logic
- OWASP-focused reviews catch syntax issues (XSS, SQLi) but miss domain integrity: double-booking bypass, payment state violations, cancellation race conditions
- 66 findings in FINDINGS_BACKLOG.md — including critical concurrency issues (F-26 through F-41)
  - Source: `docs/FINDINGS_BACKLOG.md`, `.claude/agents/security-reviewer.md`

## Learned Patterns

- (No agent-learned entries yet — AGENT_LEARNINGS.md has zero active entries)

## Revalidation Notes

- After resolving any FU-* item: update PERMISSION_MATRIX.md and remove from this list
- After F-33 is fixed: remove restore-overlap TOCTOU entry
- After AGENT_LEARNINGS.md gains active entries: cross-reference with this file for duplicates
