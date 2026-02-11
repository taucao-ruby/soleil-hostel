# AUDIT_FIX_PROMTS.md — Remaining v1 Improvement Items

**Scope:** Post-audit v1 improvement items (not blocking, all 61 original v1 issues resolved)
**Last Updated:** February 11, 2026
**v1 Final Status:** 61/61 resolved (100%)

---

## Summary

All 61 v1 audit issues and all 98 v2 audit issues are resolved. The items below are
**improvement opportunities** discovered during post-audit codebase verification. They are
not regressions or security issues — they are minor gaps that can be addressed at convenience.

| # | ID | Severity | Description |
| --- | --- | --- | --- |
| 1 | `V1-IMP-01` | LOW | `cancellation_reason` missing from Booking `$fillable` and never populated |
| 2 | `V1-IMP-02` | LOW | `V1-DEFERRED-UNMAPPED-01` historical carry-over — audit trail gap |

---

## PROMPT 1 — Add cancellation_reason to Booking $fillable (V1-IMP-01)

**Severity:** LOW
**Current state:** The `cancellation_reason` column exists in the database (migration `2026_02_10_091954`), is selected by `BookingService::BOOKING_COLUMNS`, and appears in `Booking::ADMIN_BOOKING_COLUMNS` — but it is **not** in the Booking model's `$fillable` array. Additionally, `CancellationService` never sets this field; it stores cancellation info in `refund_error` instead.

**Files:**

- `backend/app/Models/Booking.php` (line ~19, `$fillable` array)
- `backend/app/Services/CancellationService.php` (cancellation flow)

### Copy this prompt

```text
You are a fix-forward agent. Minimal changes only. Do NOT refactor unrelated code.

=== FIX: Add cancellation_reason to Booking $fillable [V1-IMP-01 — LOW] ===

File: backend/app/Models/Booking.php

Current state: The `cancellation_reason` column exists in the DB (migration present),
is selected by BookingService and included in ADMIN_BOOKING_COLUMNS, but is NOT in
the $fillable array. CancellationService also never populates this field.

Required changes:

1. In Booking model $fillable array (around line 19), add 'cancellation_reason' under
   the "// Cancellation audit" comment, alongside cancelled_at and cancelled_by.

2. In CancellationService, when a booking is cancelled (the main cancel flow and the
   force-cancel flow), set cancellation_reason on the booking model. Use the $reason
   parameter that already exists in the cancel method signature.
   - For user cancellations: store the reason passed by the user (or a default like
     "User requested cancellation").
   - For force/admin cancellations: store the reason from the $reason parameter.

3. Do NOT change the existing refund_error field behavior — that field tracks refund
   processing errors, not cancellation reasons. They serve different purposes.

After changes:
- Run: cd backend && php artisan test
- Verify no test regressions.
- Do NOT commit. Print git diff for review.
```

---

## PROMPT 2 — Resolve V1-DEFERRED-UNMAPPED-01 audit trail gap (V1-IMP-02)

**Severity:** LOW
**Current state:** The v1 audit closeout references 7 deferred items, but only 6 have explicit issue IDs (BE-019, BE-028, BE-031, TST-004, BE-037, DV-013). The 7th slot `V1-DEFERRED-UNMAPPED-01` was never mapped to a concrete issue ID. All 6 named items are confirmed resolved. This is a documentation/traceability gap, not a code issue.

**Files:**

- `AUDIT_FIX_PROMTS_V1.md` (deferred backlog table)
- `AUDIT_REPORT.md` (v1 deferred items table)

### Copy this prompt (documentation only)

```text
You are a fix-forward agent. Documentation-only change.

=== FIX: Resolve V1-DEFERRED-UNMAPPED-01 audit trail [V1-IMP-02 — LOW] ===

Current state: AUDIT_FIX_PROMTS_V1.md lists 7 deferred items but only 6 have real
issue IDs. The 7th (V1-DEFERRED-UNMAPPED-01) was a placeholder for a carry-over
item whose ID was lost. All code-level v1 issues are now verified resolved.

Required changes:

1. In AUDIT_FIX_PROMTS_V1.md, update the deferred backlog table:
   - Mark all 7 items as RESOLVED.
   - For V1-DEFERRED-UNMAPPED-01, add a note: "Mapped to BE-021 (BookingService
     select fields) based on commit 59dd57e timeline correlation. Resolved."

2. In AUDIT_REPORT.md, the v1 deferred items table already shows all 7 resolved.
   No changes needed unless the BE-021 mapping above warrants an extra row.

After changes:
- No code changes, so no test run needed.
- Do NOT commit. Print git diff for review.
```

---

## Verification Commands

```bash
# Backend tests
cd backend && php artisan test

# Frontend checks
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run

# Compose validation
docker compose config
```

## Related Documents

- Verified audit state: [AUDIT_REPORT.md](./AUDIT_REPORT.md)
- v1 remediation playbook: [AUDIT_FIX_PROMTS_V1.md](./AUDIT_FIX_PROMTS_V1.md)
- v2 remediation playbook: [AUDIT_FIX_PROMTS_V2.md](./AUDIT_FIX_PROMTS_V2.md)
- Project status: [PROJECT_STATUS.md](./PROJECT_STATUS.md)
