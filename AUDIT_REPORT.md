# Audit Report — Soleil Hostel

> **Rolling audit-state summary**
> Last updated: 2026-06-01 | HEAD: `b7d9d28` | Branch: `dev`
>
> This is the current-state audit index. Each historical audit pass is preserved in its dated file under `docs/`, and every code-level finding lives canonically in [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md). When a finding is resolved, FINDINGS_BACKLOG is the source of truth — this file is the rolled-up view.

---

## 1. Audit Cycle Index

| Cycle | Date | Scope | Findings | Resolved | Open | Detail |
|-------|------|-------|----------|----------|------|--------|
| v1    | 2026-02-09 | Full repo (P0/P1) | 61 | 61 | 0 | superseded by v2 |
| v2    | 2026-02-11 | Full repo (P2 + drift) | 98 | 98 | 0 | superseded by v3 |
| v3    | 2026-02-21 | Docs governance | 14 | 14 | 0 | [`docs/AUDIT_2026_02_21.md`](./docs/AUDIT_2026_02_21.md) |
| v4    | 2026-02-23 | Code/CI/security drift (F-01..F-22) | 6 (new) + 14 (cross-ref) | 22 | 0 | preserved in git history at commit `61f430a`; F-01..F-22 status mirror in FINDINGS_BACKLOG |
| v5    | 2026-03-12 | Repository structure | — | — | — | [`docs/AUDIT_2026_03_12_STRUCTURE.md`](./docs/AUDIT_2026_03_12_STRUCTURE.md) (snapshot — historical) |
| v6    | 2026-03-20 | Full repo (post v3.4 stay-domain) | 37 (F-26..F-62) | 2 (F-32, F-48) | 35 | tracked inline in [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md) |
| v7    | 2026-04-05 | Targeted (post AI-Harness Phases 0–4) | 4 (F-63..F-66) | 0 | 4 | tracked inline in [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md) |
| v8    | 2026-04-18 | AI-Harness security (post-F-67 promotion) | 1 (F-67 proposer-binding) | 1 (Mitigated) | 0 | tracked inline in [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md); see also [`docs/THREAT_MODEL_AI.md`](./docs/THREAT_MODEL_AI.md) (T-13 Accepted→Mitigated) |
| v9    | 2026-04-19 | Test-infra deadlock | 1 (F-68) | 0 | 1 | tracked inline in [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md) |

**Aggregate:** v1–v8 closed 179 findings. Net open after v9: F-23, F-25, 35 from v6 (F-26..F-62 excluding F-32 and F-48), F-63..F-66, F-68 — total 41 open. See §3 below.

---

## 2. Current Verification Gates

Verified at the documentation layer 2026-05-08; runtime gate re-verification required after the May 9 → May 31 wave (126 commits since `6372d7f`, HEAD `b7d9d28`). Single source of truth: [`PROJECT_STATUS.md`](./PROJECT_STATUS.md).

| Gate | Cmd | Last verified status | Owner |
|------|-----|----------------------|-------|
| G1 Backend tests | `cd backend && php artisan test` | Re-verification required (Mar 31 baseline 1047/2875; May 3 intermediate 1414/4110 at `b69a7a0`) | Backend |
| G2 Pint style | `cd backend && vendor/bin/pint --test` | Re-verification required | Backend |
| G3 Frontend typecheck | `cd frontend && npx tsc --noEmit` | PASS (TS5103 fixed Apr 4) | Frontend |
| G4 Frontend tests | `cd frontend && npx vitest run` | Re-verification required (Mar 31 baseline 261/25 files; May 3 intermediate 418 tests; current 39 test files) | Frontend |
| G5 Frontend build | `cd frontend && pnpm run build` | PASS | Frontend |
| G6 Compose config | `docker compose config` | PASS — host-env shadowing hardened (`093f5ae`); REDIS_PASSWORD placeholder injected (`fd796cf`); Redis auth enforced in non-local (`1737970`) | DevSecOps |
| G7 PHPStan Level 5 | `cd backend && vendor/bin/phpstan` | Re-verification required (TransactionExceptions hierarchy refactored `746a5bf`) | Backend |
| G8 Psalm | `cd backend && vendor/bin/psalm` | Re-verification required (cancellationActorSnapshot type contracts hardened `e68f40f`/`842e64a`/`98fbe93`) | Backend |
| G9 AI eval gate | `cd backend && php artisan ai:eval --all-phases` | Nightly CI at 03:00 — blocks deploy on failure | AI Harness |
| G10 OpenAPI contract lint | `.github/workflows/contract-lint.yml` (Spectral) | PASS — gate added 2026-04-17 (`4a33755`); blocks on `docs/api/openapi.yaml` or `.spectral.yaml` changes | API |
| G11 E2E smoke gate | `.github/workflows/e2e.yml` | Added by batch-8 (`c5a37dc`); workflow_dispatch-gated until full suite stabilises | Frontend |

---

## 3. Open Findings (post v9)

Authoritative detail: [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md). Summary:

| ID | Severity | Area | Summary | Cycle |
|----|----------|------|---------|-------|
| F-23 | Low | Docs | MD lint (MD022/MD031/MD032) in `docs/COMPACT.md` static sections | v3 |
| F-25 | Low | Frontend | `frontend/src/shared/lib/api.ts` 401 refresh interceptor reads `refreshResponse.data.csrf_token` (wrong level) — non-critical because `check_httponly_token` routes do not validate CSRF | v3 |
| F-26..F-62 (excl. F-32, F-48) | mixed | Code | 35 open code findings from 2026-03-20 audit (post v3.4 stay-domain). F-32 resolved Apr 27 (Sanctum `findToken()` Bearer lookup, `4ab9cfd`); F-48 resolved earlier | v6 |
| F-63..F-66 | mixed | Code | 4 open findings from 2026-04-05 audit (post AI-Harness Phases 0–4) | v7 |
| F-68 | Medium | Test infra | `2026_02_09_000005_assign_rooms_to_locations.php:50` — `->change()` doctrine-routed connection races primary connection for `rooms` locks → intermittent `SQLSTATE[40P01]` during `RefreshDatabase`. Not a production-code defect | v9 |

**Resolved this cycle (Apr 22 → May 8):** F-32 (Sanctum `findToken()` Bearer lookup, `4ab9cfd`), F-67 (proposer-binding via cache envelope `proposer_user_id`, `5a295c0`), AI-001 (policy-document prompt-injection defense, `347649a`), AI-002/AI-003 (Unicode normalization + output PII block + HMAC audit), RBAC-001 (contact messages admin-only via `ContactMessagePolicy`, `04c7d63`), OBS-001 + OBS-002 (admin-gated detail health probes, `58da55e`), PII redaction across log channels and Sentry (`cb7911a`), Stripe webhook idempotency moved to durable `stripe_refund_events` UNIQUE — TOCTOU window eliminated (`abc3959`), AUTH-004 OTP resend race-hardened (`1079946`), OPS-004 stay cancellation propagation (`7027adb`), CONC-005/006 deposit FSM (`b69a7a0`), no-overlap constraint pre-deploy assertion (`92f1ad1`), immutable actor snapshots on bookings + admin_audit_logs (`048e40b`), AI Harness kill-switch contract finalized (`2ab45ae`/`6372d7f`).

---

## 4. Threat Model Status

Authoritative detail: [`docs/THREAT_MODEL_AI.md`](./docs/THREAT_MODEL_AI.md).

| ID | Title | Prior status | Current status | Mitigation |
|----|-------|--------------|----------------|-----------|
| T-13 | AI proposal hijacking via 256-bit hash + rate limit only | Accepted (2026-04-12) | **Mitigated** (2026-04-18) | Cache-envelope `proposer_user_id`; `ProposalConfirmationController::decide` 404s on mismatch (`5a295c0`); defense-in-depth at `CancellationService::validateCancellation` |
| T-14 | Cancellation ownership bypass | Accepted | **Mitigated** | Service-layer ownership gate at `CancellationService::validateCancellation` |
| V-5 | Residual proposal hijacking risk | Open | **None after F-67** | superseded by T-13 mitigation |

---

## 5. Blocked / Won't-Do

| ID | Item | Reason | Owner |
|----|------|--------|-------|
| M-11 | Migration squash | Needs human-approved `php artisan schema:dump --prune` process | Infra |
| FU-3 | Verify `config('booking.cancellation.allow_after_checkin')` source + production value | RBAC follow-up — config-only verification, not a code change | Backend |

---

## 6. Cross-References

- Detailed v3 findings (Feb 21): [`docs/AUDIT_2026_02_21.md`](./docs/AUDIT_2026_02_21.md)
- Detailed v5 structure snapshot (Mar 12): [`docs/AUDIT_2026_03_12_STRUCTURE.md`](./docs/AUDIT_2026_03_12_STRUCTURE.md)
- All open code findings: [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md)
- Live project status (test counts, gate state): [`PROJECT_STATUS.md`](./PROJECT_STATUS.md)
- Backlog (Done, In-Progress, Next): [`BACKLOG.md`](./BACKLOG.md)
- Append-only change log: [`docs/WORKLOG.md`](./docs/WORKLOG.md)
- Volatile session state: [`docs/COMPACT.md`](./docs/COMPACT.md)
- AI threat model: [`docs/THREAT_MODEL_AI.md`](./docs/THREAT_MODEL_AI.md)
- RBAC source of truth: [`docs/PERMISSION_MATRIX.md`](./docs/PERMISSION_MATRIX.md)

---

## 7. History

The previous version of this file (frozen at audit v4 with detail on AUDIT-001..AUDIT-006, prior-issue cross-reference for F-01..F-14, 20 search-trace appendix entries) is preserved in git history at commit `61f430a`. All v4 findings have since been resolved — see FINDINGS_BACKLOG status column. The historical content was rolled up here on 2026-05-08 because the `AUDIT_REPORT.md` file is the rolling current-state index, not a per-cycle artifact.
