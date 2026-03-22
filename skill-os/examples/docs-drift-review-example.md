# Example: Docs-vs-Code Drift Review

> Worked example demonstrating a full drift review pass on Soleil Hostel documentation.

## Review Context

- **Date:** 2026-03-22 (example)
- **Scope:** `docs/agents/ARCHITECTURE_FACTS.md`, `docs/PERMISSION_MATRIX.md`, `docs/frontend/BOOKING.md`
- **Trigger:** Post-migration review after `2026_03_17_000003_add_bookings_status_check.php` was merged

## Files Reviewed

| File | Sections Checked | Divergences Found |
|---|---|---|
| `docs/agents/ARCHITECTURE_FACTS.md` | Booking Status, Enums, DB Hardening | 1 STALE |
| `docs/PERMISSION_MATRIX.md` | Admin endpoints, Role middleware | 1 MISLEADING |
| `docs/frontend/BOOKING.md` (hypothetical) | Date handling, Overlap rules | 1 DANGEROUS |

## Findings

### Finding 1: STALE — Missing status in Architecture Facts

- **File:** `docs/agents/ARCHITECTURE_FACTS.md` §Booking Status
- **What docs say:** Lists statuses as `pending, confirmed, refund_pending, cancelled`
- **What code says:** `BookingStatus.php` enum includes `refund_failed` as a fifth status; CHECK constraint migration `2026_03_17_000003` includes all five
- **Severity:** STALE
- **Impact:** A developer reading the docs would not know about `refund_failed`, but would not write incorrect code because of this omission. No invariant violation risk.
- **Remediation:** Add `refund_failed` to the status list in ARCHITECTURE_FACTS.md
- **Action taken:** Updated docs in same PR

---

### Finding 2: MISLEADING — Wrong role name in Permission Matrix

- **File:** `docs/PERMISSION_MATRIX.md`
- **What docs say:** "Admin booking READ routes require `role:admin` middleware"
- **What code says:** `backend/routes/v1.php` uses `role:moderator` middleware on admin booking READ routes (index, trashed, showTrashed)
- **Severity:** MISLEADING
- **Impact:** A developer adding a new admin booking endpoint might apply `role:admin` middleware based on docs, making the endpoint inaccessible to moderators who should have access. Not an invariant violation, but causes authorization inconsistency.
- **Remediation:** Correct to `role:moderator` in PERMISSION_MATRIX.md
- **Action taken:** Corrected; added note explaining moderator vs admin distinction

---

### Finding 3: DANGEROUS — Closed interval documentation

- **File:** `docs/frontend/BOOKING.md` (hypothetical example for illustration)
- **What docs say:** "Booking date range is inclusive: check_in through check_out, including both days. When checking for conflicts, use `check_in <= existing_check_out && check_out >= existing_check_in`."
- **What code says:**
  - Exclusion constraint uses `daterange(check_in, check_out, '[)')` — half-open interval
  - PHP overlap scope uses `existing.check_in < new.check_out AND existing.check_out > new.check_in` — strict inequalities
  - Both layers treat `check_out` as exclusive (room is free on checkout day)
- **Severity:** DANGEROUS
- **Invariant violated:** INV-1 (half-open `[check_in, check_out)`)
- **Impact:** A developer reading this doc would write `<=` / `>=` overlap checks in new code. This would:
  1. Reject same-day turnover (guest checking out Mar 5 blocks guest checking in Mar 5)
  2. Create a mismatch between PHP logic and the PostgreSQL constraint, causing inconsistent behavior depending on which layer catches the overlap first
  3. If the developer also modifies the constraint to match the docs, the database-level defense is weakened
- **Remediation:** IMMEDIATE. Rewrite to: "Booking date range uses half-open interval `[check_in, check_out)`. The check_out date is exclusive — the room is available starting on checkout day. Overlap check uses strict inequalities: `existing.check_in < new.check_out AND existing.check_out > new.check_in`."
- **Action taken:** Blocked PR until docs corrected. Filed as DANGEROUS drift in review notes.

---

## Summary

| Severity | Count | Items |
|---|---|---|
| DANGEROUS | 1 | Closed-interval documentation (INV-1 violation risk) |
| MISLEADING | 1 | Wrong role name in permission matrix |
| STALE | 1 | Missing `refund_failed` status |

## Overall Drift Risk

**CRITICAL** — DANGEROUS drift detected. Release blocked until Finding 3 is remediated.

## Lessons Captured

- Date range semantics are the highest-risk documentation target. Any doc that mentions check_in/check_out must explicitly state half-open intervals.
- Role names (`moderator` vs `admin`) are a subtle but recurring drift source because both sound plausible.
- The most dangerous docs look correct — "inclusive date range" is intuitive and would not raise suspicion without cross-referencing the constraint SQL.
