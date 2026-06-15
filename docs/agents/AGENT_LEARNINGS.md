# Agent Self-Learning Memory — Soleil Hostel

## Purpose

This file contains ONLY operational learning entries produced by AI agents during real tasks
in this repository. Its sole purpose is to prevent recurring agent execution failures.

## What This File Is NOT

- Not an architecture source. Architecture lives in `ARCHITECTURE_FACTS.md`.
- Not a second invariant registry. Invariants live in `ARCHITECTURE_FACTS.md` and `CLAUDE.md`.
- Not an append-only diary. Entries must survive the rejection rules in the operating rules file.
- Not a knowledge base. Only failure patterns with real evidence belong here.
- Not a place for speculation or hypothesis entries.

## References

- **Schema**: see `AGENT_LEARNINGS_REFERENCE.md` (Part 1) for field definitions and entry format.
- **Write and read rules**: see `AGENT_LEARNINGS_OPERATING_RULES.md` before writing any entry.
- **Format examples**: see `AGENT_LEARNINGS_REFERENCE.md` (Part 2) for illustrative entries (schema training only — do NOT cite them as real failures).

## Scope

Mandatory reads apply to these four task domains only (see operating rules R-01–R-04):
1. Booking mutations (any write to the `bookings` table)
2. Migrations and schema changes
3. RBAC / authorization middleware changes
4. Frontend ↔ backend API contract changes

## Active Entries

<!-- No entries yet. Entries are added only after real failures
     with real evidence. See AGENT_LEARNINGS_OPERATING_RULES.md
     section W-01 before writing any entry. -->

---

## PROPOSED ENTRIES — PENDING HUMAN REVIEW

<!-- Agent-proposed entries go here first.
     Do not treat as ACTIVE until reviewed and moved above.
     Format must match AGENT_LEARNINGS_REFERENCE.md (Part 1) exactly.
     Agent: set review_status: SELF_RECORDED on all proposed entries.
     Human: move to Active Entries section after verifying evidence,
     corrected_pattern, and invariant alignment. Then commit with:
     "learning(SL-NNN): promote proposed entry — [area] [mistake-summary]" -->

```yaml
id: SL-001
status: PROPOSED            # PROPOSED — awaiting human review before activation
review_status: SELF_RECORDED
confidence: INFERENCE
date: 2026-06-10
area: booking-concurrency-harness
tags: [booking, locking, overlap, transaction, ci-harness]
task: >
  RCA of CI concurrent-booking stress test (50-request burst, 4 forked
  built-in-server workers) returning 0/50 successes: 9x HTTP 422
  retry-exhaustion + 41x HTTP 0.
mistake: >
  Two coupled errors. (1) Agent-authored harness treated "no HTTP 201
  observed" as "no booking committed": the stub never asserts DB end-state,
  so a winner whose response was lost to the 30s curl timeout is
  indistinguishable from a true zero-winner livelock
  (backend/tests/stubs/concurrent_booking_test.php:91-201 counts statuses
  only). (2) Retry design assumed app-layer SELECT ... FOR UPDATE serializes
  contenders, but on an EMPTY overlap set it locks zero rows
  (CreateBookingService.php:304-307); first-booking bursts are arbitrated
  solely by the GiST EXCLUDE constraint, where concurrent conflicting
  INSERTs mutually wait and the deadlock detector (deadlock_timeout,
  default 1s, untuned in CI) resolves rounds slowly while the 10-50ms
  deadlock backoff (CreateBookingService.php:229) re-synchronizes losers
  into the next collision wave.
evidence: >
  CI log 2026-06: 9x422 "Không thể tạo booking sau 3 lần thử" + 41xHTTP 0 +
  0x429 (throttle budget 20/min never reached => <20 requests served in
  30s); backend/tests/stubs/concurrent_booking_test.php:32-37 (50 distinct
  users => user-row lock disjoint, not the contention source), :71
  (CURLOPT_TIMEOUT=30); backend/app/Services/CreateBookingService.php:146
  (catch PDOException), :190-235 (classifier + 10-50ms deadlock backoff),
  :304-315 (empty-set FOR UPDATE); vendor QueryException.php:10 (extends
  PDOException — catch-type mismatch falsified); .github/workflows/tests.yml
  stress job (no PG deadlock_timeout tuning); git ee9a5ba (T-2 fork workers
  = first time the race was actually exercised).
corrected_pattern: >
  Concurrency harnesses for booking writes MUST assert database end-state
  (exactly N committed bookings for the contested room/range) in addition
  to HTTP outcomes, and capture laravel.log + PG SQLSTATE evidence on
  failure. When reasoning about the create path, treat the app-layer
  overlap FOR UPDATE as serializing only against COMMITTED conflicting
  rows; the PostgreSQL EXCLUDE constraint is the sole arbiter for
  concurrent first bookings, and retry backoff there must be
  de-synchronized (full jitter), never fixed-narrow. Any change to lock
  acquisition, retry counts, or backoff in this path requires human
  approval per CLAUDE.md escalation rules.
stale_after: 2026-09-08
notes: >
  Prior entry for this failure class: NONE (first entry in file).
