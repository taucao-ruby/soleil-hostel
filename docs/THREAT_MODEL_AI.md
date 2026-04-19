# AI Threat Model — Soleil Hostel

**Last updated**: 2026-04-18  
**Scope**: AI harness Phases 1–4 (F-67 proposer-binding remediation folded in 2026-04-18; finding initially cited as "F-06 (2026-04-18)" — see `FINDINGS_BACKLOG.md` namespace note)

## Risk Matrix

| ID  | Threat                          | Feature    | Likelihood | Impact   | Mitigation                                   | Status      |
|-----|---------------------------------|------------|------------|----------|----------------------------------------------|-------------|
| T-1 | Prompt injection                | All        | HIGH       | CRITICAL | L4 injection heuristics (7 patterns)         | Mitigated   |
| T-2 | Double-booking via AI           | Room/Book  | MEDIUM     | CRITICAL | BLOCKED tools + PostgreSQL exclusion constraint | Mitigated |
| T-3 | PII leakage in model output     | All        | MEDIUM     | HIGH     | L4 pre/post PII scan, masked logging         | Mitigated   |
| T-4 | Cross-customer PII in admin drafts | Admin Draft | MEDIUM  | HIGH     | Context assembly RBAC + PII detection        | Mitigated   |
| T-5 | Autonomous action claims        | Admin Draft | MEDIUM    | HIGH     | Pattern detection + prompt prohibition       | Mitigated   |
| T-6 | Model hallucination             | FAQ/Room   | HIGH       | MEDIUM   | Grounded context, citation requirements      | Mitigated   |
| T-7 | Token cost explosion            | All        | LOW        | MEDIUM   | Token budget per task, cost alerting          | Mitigated   |
| T-8 | Provider API key exposure       | All        | LOW        | CRITICAL | config() not env(), masked logging           | Mitigated   |
| T-9 | Blocked tool execution          | All        | LOW        | CRITICAL | L5 classification + L4 post-call gate        | Mitigated   |
| T-10| Model provider unavailability   | All        | MEDIUM     | MEDIUM   | Circuit breaker, fallback responses          | Mitigated   |
| T-11| Rate limit bypass               | All        | LOW        | MEDIUM   | throttle:10,1 middleware + per-user limits   | Mitigated   |
| T-12| Draft committed without review  | Admin Draft | LOW       | HIGH     | ToolDraft struct (no DB write), UI confirms  | Mitigated   |
| T-13| Proposal ownership bypass       | Phase 4    | MEDIUM     | HIGH     | Proposer-binding (F-67, 2026-04-18): cache envelope carries `proposer_user_id`; `decide()` 404s on absence or mismatch. Hash entropy (64-char SHA-256) + per-user `throttle:5,1` + `Cache::forget()` post-decide provide layered mitigation. | Mitigated |
| T-14| Booking mutation via proposal    | Phase 4    | LOW        | CRITICAL | Downstream delegates to existing BookingService with full validation; PostgreSQL exclusion constraint preserved; service-layer `CancellationService::validateCancellation` ownership gate is the last line of defense (logs `ai` channel warnings on mismatch) | Mitigated |

## Attack Vectors

### V-1: Prompt Injection via User Input
- **Path**: User input → L1 normalizer → L4 pre-call → model
- **Controls**: 7 injection patterns in L4 screenInput(), input length limit (10000 chars)
- **Residual risk**: Novel injection patterns not covered by heuristics

### V-2: Model Proposes Blocked Tool
- **Path**: Model output → L4 post-call → L5 tool orchestration
- **Controls**: ToolRegistry classifies ALL tools statically, unknown→BLOCKED, L4 rejects before L5
- **Residual risk**: None — classification is static and pre-execution

### V-3: Cross-Customer Data Leakage in Admin Drafts
- **Path**: Admin request → context assembly → model → draft
- **Controls**: RBAC at context assembly (moderator+ only), single-customer context scope
- **Residual risk**: Model may infer info from patterns — mitigated by context isolation

### V-4: Autonomous Action in Draft
- **Path**: Model generates draft claiming to have taken action → admin sends to guest
- **Controls**: Prompt prohibition, autonomous action detection patterns, audit log
- **Residual risk**: Novel phrasing not caught by patterns — mitigated by human review step

### V-5: Proposal Hash Guessing / Cross-User Confirmation (Phase 4)
- **Path**: Attacker guesses/brute-forces 64-char SHA-256 proposal hash or obtains one via side channel → attempts to confirm another user's proposal
- **Controls**:
  1. **Proposer-binding** (F-67, 2026-04-18 — `ProposalConfirmationController.php:55-62`): cache envelope carries `proposer_user_id`; decide() 404s on absence or mismatch. Legacy cache entries without the field are also treated as unbound → 404.
  2. Hash entropy: 64 hex chars / 256-bit random.
  3. Rate limit: `throttle:5,1` per authenticated user (stricter than the `throttle:10,1` on the main AI handler).
  4. Cache TTL expires proposals; `Cache::forget()` runs after every decide (confirm or decline) so a single hash cannot be replayed.
  5. Cancellation path reuses `CancellationService::validateCancellation` service-layer ownership gate (last line of defense; logs to `ai` channel on mismatch).
- **Residual risk**: None after F-67. Proposer-binding is authoritative at the controller; service-layer ownership check catches any future regression at alternate callers.

### V-6: Downstream Booking via Proposal Confirmation (Phase 4)
- **Path**: User confirms proposal → ProposalConfirmationController → BookingService::createBooking/cancel
- **Controls**: Delegates to existing service layer with full validation, pessimistic locking, and PostgreSQL exclusion constraint
- **Residual risk**: None — proposal confirmation path uses identical booking write path as direct API

## Monitoring

| Monitor                             | Threshold   | Alert Channel | Frequency |
|-------------------------------------|-------------|---------------|-----------|
| Blocked tool attempts               | > 0         | ai (log)      | Real-time |
| PII in output                       | > 0         | ai (log)      | Real-time |
| Hallucination rate                  | > 2%        | ai (log)      | Nightly   |
| Autonomous actions                  | > 0         | ai (log)      | Nightly   |
| Third-party PII leaks               | > 0         | ai (log)      | Nightly   |
| p95 latency                         | > SLO       | ai (log)      | Nightly   |
| Cost per request                    | > $0.05     | ai (log)      | Real-time |
| Proposer-binding blocks (`decide`)  | > 0         | ai (log)      | Real-time |
| Service-layer ownership blocks (`executeCancellation`) | > 0 | ai (log) | Real-time |
