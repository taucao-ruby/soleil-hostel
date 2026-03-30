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

- **Schema**: see `AGENT_LEARNINGS_SCHEMA.md` for field definitions and entry format.
- **Write and read rules**: see `AGENT_LEARNINGS_OPERATING_RULES.md` before writing any entry.
- **Format examples**: see `AGENT_LEARNINGS_EXAMPLES.md` for illustrative entries (schema training only — do NOT cite them as real failures).

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
     Format must match AGENT_LEARNINGS_SCHEMA.md exactly.
     Agent: set review_status: SELF_RECORDED on all proposed entries.
     Human: move to Active Entries section after verifying evidence,
     corrected_pattern, and invariant alignment. Then commit with:
     "learning(SL-NNN): promote proposed entry — [area] [mistake-summary]" -->
