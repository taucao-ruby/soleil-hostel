# Security Reviewer — Subagent Memory

Role-scoped memory for business-logic abuse, auth-session integrity, RBAC, booking-payment-refund correctness.

## Stable Memory

### Double-Booking Is a Security Concern
- Overlap bypass = financial loss + operational failure, not just a UX bug
- Two-layer defense: app-layer `lockForUpdate()` in transaction + DB `no_overlapping_bookings` EXCLUDE constraint
- Only `pending` + `confirmed` block overlap; `deleted_at IS NULL` filter active
  - Source: `docs/agents/ARCHITECTURE_FACTS.md` § Booking Domain, `.agent/rules/booking-integrity.md`

### Auth Token Security
- Dual-mode: Bearer + HttpOnly cookie — both must remain active
- Cookie lookup: `token_identifier` → `token_hash` (never raw token value)
- Validity: `revoked_at IS NULL` AND `expires_at` in middleware
- CSRF: `sessionStorage` → `X-XSRF-TOKEN` + `withCredentials: true` in `frontend/src/shared/lib/api.ts`
  - Source: `.agent/rules/auth-token-safety.md`

### Locking Call-Sites (owned scope)
- `lockForUpdate()` confirmed in `CancellationService.php:115-120` (transitionToRefundPending)
- `Booking::scopeWithLock` at `Booking.php:376`
- **Known gap (F-33)**: `finalizeCancellation()` at `CancellationService.php:268-282` runs without `lockForUpdate()` — race condition between refund success and status update
  - Source: `docs/FINDINGS_BACKLOG.md` F-33

### RBAC Boundaries
- Backend middleware + policies are authority; frontend route guards are UX only
- Role levels: `user=1, moderator=2, admin=3` via `isAtLeast()` in `User.php`
- Moderator boundaries must be proven in backend, not assumed from frontend
- FU-2: `restore-bulk` lacks moderator-denial test
  - Source: `docs/PERMISSION_MATRIX.md`, `.agent/rules/backend-preserve-rbac-source-and-request-validation.md`

### Handoff Protocol
- API endpoint security scope is split with frontend-reviewer per `docs/agents/api-handoff-protocol.md`
- This agent owns: CSRF interceptor, 401 refresh queue, route auth middleware, policy/gate coverage, input sanitization
- Escalation triggers: `withCredentials` removal, CSRF interceptor modification, new Axios instance

## Learned Patterns

- Repo evidence ≠ runtime proof — tag all runtime behavior claims as `[UNPROVEN]` unless tested
- Cancellation and restore flows require concurrency scrutiny — they modify booking state and can reintroduce conflicts
- Security reviews must cover business-logic integrity (double-booking, payment state machine), not just OWASP syntax
- Payment state machine transitions must be verified: no orphaned `refund_pending` without resolution

## Revalidation Notes

- After any CancellationService change: verify F-33 status and locking coverage
- After any auth middleware change: re-verify token_identifier → token_hash chain
- After frontend `api.ts` changes: check CSRF interceptor, `withCredentials`, 401 handler
- After role/permission changes: re-verify PERMISSION_MATRIX.md alignment
