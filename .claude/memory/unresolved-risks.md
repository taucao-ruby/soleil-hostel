# Unresolved Risks — Soleil Hostel

Active risk areas requiring explicit revalidation. Phrased as risks, not facts. Verified 2026-04-07.

## Stable Memory

### Payment / Checkout Flow
- **Risk**: Payment columns exist (`payment_intent_id`, `refund_id`, `refund_status`, `refund_amount`, `deposit_amount`) but checkout flow completion status is unverified at runtime
- **Why unresolved**: No runtime test evidence confirming end-to-end payment processing
  - Source: `docs/agents/ARCHITECTURE_FACTS.md` § Payment/Refund Columns

### Cancellation Race Condition (F-33)
- **Risk**: `finalizeCancellation()` runs without `lockForUpdate()` — race between refund success and status update to CANCELLED
- **Why unresolved**: Documented in FINDINGS_BACKLOG but not yet fixed
  - Source: `docs/FINDINGS_BACKLOG.md` F-33, `CancellationService.php:268-282`

### Moderator UI vs Backend Alignment
- **Risk**: Moderator-facing UI surfaces may expose actions not permitted by backend policies
- **Why unresolved**: FU-2 notes missing moderator-denial tests for `restore-bulk` and `trashed/{id}`
  - Source: `docs/PERMISSION_MATRIX.md` FU-2

### Restore Path Overlap Protection
- **Risk**: Restoring a soft-deleted booking may reintroduce overlap without transactional overlap re-check
- **Why unresolved**: Restore path concurrency not verified end-to-end
  - Source: `.agent/rules/booking-integrity.md`, `docs/FINDINGS_BACKLOG.md`

### Config Source Verification (FU-3)
- **Risk**: `config('booking.cancellation.allow_after_checkin')` — source file and production value unknown
- **Why unresolved**: Config file location not verified; severity of BR-3 depends on this
  - Source: `docs/PERMISSION_MATRIX.md` FU-3

### Room CUD Policy Post-Hardening (FU-4)
- **Risk**: `RoomController` `$this->authorize()` call-sites not re-verified after hardening batch
- **Why unresolved**: VERIFICATION-INCOMPLETE status after hardening
  - Source: `docs/PERMISSION_MATRIX.md` FU-4

### Legacy Test Path Drift (FU-1, FU-5)
- **Risk**: Tests targeting legacy `/api/bookings/` and `/api/rooms/` paths may pass on old routes while v1 routes diverge
- **Why unresolved**: Migration to v1 test paths not complete
  - Source: `docs/PERMISSION_MATRIX.md` FU-1, FU-5

### Auth/CSRF Runtime Behavior
- **Risk**: Token validity enforcement (`revoked_at`, `expires_at`), CSRF interceptor, and `withCredentials` are confirmed in source but not runtime-tested
- **Why unresolved**: Repo evidence only; middleware execution order not runtime-confirmed
  - Source: `.agent/rules/auth-token-safety.md`

## Learned Patterns

- (Populated as risks are resolved or new risks discovered during audits)

## Revalidation Notes

- After payment integration work: re-assess payment/checkout risk
- After F-33 fix: remove cancellation race condition entry
- After FU-* closures: remove corresponding entries and update PERMISSION_MATRIX.md
- After any auth middleware change: re-verify CSRF and token validity enforcement
