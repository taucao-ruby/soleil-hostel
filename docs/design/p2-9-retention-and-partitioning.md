# P2-9 — Retention & Partitioning Design (revised per Correction C-4) (F-88 / F-91)

**Type**: Design documentation only (no implementation, no migrations, no code diffs).
**Status**: DRAFT — for Principal Engineer review.
**Date**: 2026-06-13
**Backlog items**: `docs/FINDINGS_BACKLOG.md` **F-88** ("Unbounded ledger growth", Medium, Open) and
**F-91** ("partition/dedupe conflict", Medium, Open — confirmed against live catalog 2026-06-11).
**Governing correction**: **C-4** (see §1).
**Reviewer**: Principal Engineer.

> **Scope guardrail (binding).** Docs-only. Nothing here authorizes touching the backend tree.
> Every retention job, migration, partition, or trigger change described below is *design intent*
> that must be raised as its own implementation ticket with stop-and-confirm. A collision point with
> the append-only triggers is called out inline as a STOP-AND-CONFIRM block in §2.

> **Evidence tags** as in P2-8: `[CONFIRMED]` / `[INFERRED]` / `[UNPROVEN]` / `[ACTION]` / `TBD`.

---

## 1. Governing constraint — Correction C-4

### 1.1 Source note (read first)

`[CONFIRMED]` **The verbatim text of C-4 was not supplied in this prompt's `<INPUTS>` block, and no
standalone C-4 document exists in the repo.** C-4 is one of corrections C-1–C-4 from the "2026-06-11
DB P0 Hardening Pass" (`docs/FINDINGS_BACKLOG.md`). The same situation was already recorded for C-2:
*"C-2 has no standalone document in the repo. Its content exists in … the task prompt's CONTEXT
block and the digest in `docs/FINDINGS_BACKLOG.md`"* (`docs/DECISION_LEDGER_IMMUTABILITY_FK.md`).
C-4 is identical in this respect.

Therefore the two faithful sources for C-4's *content* are reproduced below, clearly attributed. No
"verbatim" wording is invented; what is quoted is the task author's CONTEXT statement of C-4 and the
repo's F-88/F-91 digest of the C-4-revised plan.

### 1.2 C-4 as stated in this task's CONTEXT (authoritative statement of the governing rule)

> Correction C-4 supersedes any prior partitioning-first plan. New order of operations: (1) retention
> policy + payload-NULLing for expired records FIRST, (2) partitioning considered ONLY for pure
> append-only ledger tables (tables with no UPDATE/DELETE in normal operation).
>
> `stripe_webhook_events` is explicitly OUT of scope for partitioning: it either (a) stays
> unpartitioned as-is, or (b) has its dedupe responsibility moved to a separate slim table — but in
> either case, the existing global UNIQUE constraint on `stripe_event_id` is CONFIRMED as the
> idempotency surface and must NOT be altered, dropped, or replaced by this design.

`[CONFIRMED — reproduced from this task's CONTEXT block; this is the governing constraint for the
revision]`

### 1.3 C-4 as digested in the repo backlog (corroborating, same content)

`[CONFIRMED — `docs/FINDINGS_BACKLOG.md`]`

- **F-88 (revised per C-4):** *"payload-NULLing retention job first (keep dedupe key + status);
  partition only the pure append ledgers; `stripe_webhook_events` stays unpartitioned (or dedupe is
  split to a slim unpartitioned table) to preserve global idempotency."*
- **F-91:** the `stripe_webhook_events.stripe_event_id` **global UNIQUE** (live catalog name
  `stripe_webhook_events_stripe_event_id_unique`) is the webhook idempotency surface (INSERT-first
  claim). *"PostgreSQL requires the partition key inside every unique constraint, so partitioning by
  `created_at` … demotes dedupe to per-partition — a Stripe retry landing across a month boundary
  would process twice."* → *"dedupe stays global; never partition this table as-is."*

### 1.4 The two ordering rules this document obeys

1. **Retention/NULL-first, partition-second.** Phase 1 (§2) before Phase 2 (§3). `[CONFIRMED — C-4]`
2. **Partition append-only-only.** Only *pure* append-only ledgers (no UPDATE/DELETE in normal
   operation) are partition candidates; idempotency-bearing tables are excluded. `[CONFIRMED — C-4]`

---

## 2. Phase 1 — Retention & payload-NULLing (FIRST)

### 2.1 Retention policy per table/category

`[INFERRED — proposed; legal/business basis is `TBD` and must not be invented]`

| Table / category | Data | Proposed action on expiry | Retention period | Legal/business basis |
|---|---|---|---|---|
| `stripe_webhook_events.payload` | full Stripe JSONB body | **NULL the `payload`**, keep `stripe_event_id` + `status` + `processed_at` (dedupe key + outcome) | `TBD` | `TBD` — finance/audit/Stripe-dispute window |
| `contact_messages` | sender name/email/body | NULL/redact body + contact fields after window (or crypto-shred per P2-8) | `TBD` | `TBD` — support SLA |
| `ai_proposals.proposed_params` / `risk_assessment` | proposal JSON mirror | NULL after the proposal is terminal + window | `TBD` | `TBD` — short, post-decision |
| `stripe_refund_events` | refund event facts | retain (financial); minimal PII — likely retain longer | `TBD` | `TBD` — financial record |
| `deposit_events`, `admin_audit_logs`, `ai_proposal_events` | append-only ledgers | **NOT payload-NULLed** — see §2.3; bulk retention handled by Phase-2 partition-drop; PII handled by crypto-shred (P2-8) | partition-drop window `TBD` | `TBD` — audit retention |

`[CONFIRMED]` All period and legal-basis cells are `TBD` rather than invented; F-88 itself records no
concrete numbers, and CLAUDE.md forbids guessing missing contracts.

### 2.2 Payload-NULLing mechanism

`[INFERRED]`

- **Which columns get nulled:** only the *fat / sensitive* payload columns (e.g.
  `stripe_webhook_events.payload`), never the **idempotency / dedupe key** (`stripe_event_id`) and
  never the **status/outcome** columns. The row is retained.
- **"Expired but retained" state:** represented intrinsically — `payload IS NULL` **and**
  `status = 'processed'` (or the table's terminal status) **and** an age past the retention window.
  No new lifecycle flag is required for `stripe_webhook_events` because a processed row with a NULL
  payload is unambiguously "retained-but-reaped." `[INFERRED]` If a table needs an explicit marker, a
  nullable `payload_purged_at` timestamp is the design-preferred representation `[INFERRED]` (a
  backend change → its own ticket).
- **Idempotency invariant preserved:** because `stripe_event_id` and its global UNIQUE are kept, a
  replayed webhook still collides on INSERT exactly as today — NULLing the payload does **not** weaken
  dedupe. `[CONFIRMED — the UNIQUE is the idempotency surface; payload is not part of it]`

### 2.3 Why the three trigger-protected ledgers are excluded from payload-NULLing

> ### ⛔ STOP-AND-CONFIRM (triggered here — design boundary, no action taken)
>
> **What would be needed.** Payload-NULLing `deposit_events`, `admin_audit_logs`, or
> `ai_proposal_events` is an **UPDATE on a non-actor column**, which the F-83 append-only triggers
> (migration id `2026_06_12_000002`) reject with SQLSTATE `P0001` — only the sanctioned
> `actor_col → NULL` UPDATE is permitted (`docs/DECISION_LEDGER_IMMUTABILITY_FK.md`, D1)
> `[CONFIRMED]`. Applying §2.1's NULLing to these tables would require widening the trigger allowlist
> or a gated bypass — a change to the trigger function and an amendment to the Accepted ticket-B memo.
>
> **Why it touches the backend.** Any such allowlist change is a trigger/migration change under the
> backend tree.
>
> **Action:** none taken. **Design decision recorded:** these three ledgers are **excluded from
> Phase-1 payload-NULLing**. Their bulk-size retention is handled by **Phase-2 partition-drop** of
> whole aged partitions (a DROP of a partition is not a row UPDATE/DELETE and so does not trip the
> per-row trigger `[INFERRED — verify against trigger scope before implementation]`), and their
> embedded PII is handled by **crypto-shredding (P2-8)**. This keeps the append-only contract
> untouched. `[INFERRED — recommendation]` `[ACTION — PE to ratify the exclusion]`

---

## 3. Phase 2 — Partitioning (append-only ledgers ONLY) (SECOND)

### 3.1 Criteria for "pure append-only ledger"

`[INFERRED — derived from C-4's "no UPDATE/DELETE in normal operation"]` A table qualifies only if
**all** hold:

1. **No UPDATE/DELETE in normal app flow** — writes are INSERT-only; the only tolerated UPDATE is the
   rare referential `actor_col → NULL` from user deletion (not a normal-flow mutation).
2. **No global UNIQUE that must span all rows** — i.e., the table does **not** own an idempotency
   constraint that would be demoted to per-partition (this is the F-91 disqualifier).
3. **A monotonic time/range key** (`created_at`) suitable as the partition key.

### 3.2 Candidate tables

`[CONFIRMED — append-only status & trigger from `docs/DB_FACTS.md` / ticket-B doc; partition
suitability is `[INFERRED]`]`

| Table | Append-only? | Global UNIQUE that must stay whole? | Partition candidate? |
|---|---|---|---|
| `admin_audit_logs` | yes (trigger-enforced) | none | **Yes** — RANGE by `created_at` `[INFERRED]` |
| `deposit_events` | yes (trigger-enforced, no `updated_at`) | none (PK only) | **Yes** — RANGE by `created_at` `[INFERRED]` |
| `ai_proposal_events` | yes (trigger-enforced; note: has `updated_at`) | none (`proposal_hash` is indexed, **not** unique here) | **Yes** — RANGE by `created_at` `[INFERRED]` |
| `stripe_webhook_events` | ingestion ledger | **YES — `stripe_event_id` UNIQUE (idempotency)** | **NO** — excluded by C-4 / F-91 (see §4) |
| `stripe_refund_events` | refund replay fence | **YES — `stripe_refund_id` UNIQUE ("the canonical idempotency authority", `docs/DB_FACTS.md`)** | **NO** — same disqualifier as `stripe_webhook_events` `[INFERRED — see note]` |

> `[INFERRED — flagged for PE]` **`stripe_refund_events` carries the same hazard as
> `stripe_webhook_events`.** Its `stripe_refund_id` UNIQUE is documented as *"the canonical
> idempotency authority"* (`docs/DB_FACTS.md`). The F-91/C-4 reasoning ("PostgreSQL requires the
> partition key inside every unique constraint → partitioning demotes dedupe to per-partition")
> applies to it verbatim. **C-4's literal text scopes only `stripe_webhook_events`**, so this is
> raised as an `[INFERRED]` extension, **not** as part of C-4: recommend the PE treat
> `stripe_refund_events` identically (do not partition as-is; keep its UNIQUE global). `[ACTION — PE
> decision]`

### 3.3 Partitioning strategy for qualifying tables

`[INFERRED — design-level, no DDL]` For `admin_audit_logs`, `deposit_events`, `ai_proposal_events`:

- **RANGE partitioning by `created_at`**, monthly partitions (granularity `TBD` — weekly/monthly per
  volume).
- **Primary-key consequence:** PostgreSQL requires the partition key to be part of the table's
  primary key / unique constraints. Folding `created_at` into the PK (e.g. `(id, created_at)`) is a
  schema change — design intent only, its own migration ticket. `[INFERRED]`
- **Retention by partition-drop:** aged partitions are detached/dropped wholesale — fast, no row-level
  churn, and (per the §2.3 note, to be verified) does not trip the per-row append-only trigger.
  `[INFERRED — verify trigger scope is row-level only]`
- **Append-only trigger coexistence:** the per-row triggers attach to each partition; INSERT-only
  flow is unaffected. `[INFERRED — confirm trigger propagation to partitions during implementation]`

---

## 4. `stripe_webhook_events` handling (no decision made here)

`[CONFIRMED — C-4 / F-91]` **The global UNIQUE on `stripe_event_id`
(`stripe_webhook_events_stripe_event_id_unique`) is the confirmed idempotency surface and is NOT to
be altered, dropped, or replaced by this design.** This table is **excluded from partitioning**
because partitioning by `created_at` would demote the UNIQUE to per-partition and let a
cross-month-boundary Stripe retry process twice.

Two allowed options, presented as alternatives with trade-offs. **This document does not pick one —
it is a decision reserved for the Principal Engineer.**

### Option A — Stays unpartitioned as-is (retention via payload-NULLing only)

- **Pros:** simplest; the `stripe_event_id` UNIQUE is **untouched in every sense** (same table, same
  constraint) — fully satisfies C-4's "must NOT be altered/dropped/replaced" with zero ambiguity
  `[CONFIRMED]`; payload-NULLing (§2.2) reclaims the largest cost (the fat JSONB) while keeping
  dedupe `[INFERRED]`.
- **Cons:** **row count still grows unbounded** — only payload bytes are reclaimed, not rows, so the
  `stripe_event_id` index and table heap keep growing; no partition-drop fast path; reliance on
  VACUUM/bloat management over time `[INFERRED]`.

### Option B — Move dedupe to a separate slim, unpartitioned claim table

- **Shape (design-level):** a slim claim table holds just `stripe_event_id` (the global UNIQUE) +
  status/timestamps and stays **unpartitioned**; the fat payload/body lives in a separate,
  partitionable (by `created_at`) table linked by id. `[INFERRED]`
- **Pros:** idempotency stays a **single global UNIQUE** on a small, slow-growing table; the fat
  payload table becomes partition-droppable, solving unbounded growth `[INFERRED]`.
- **Cons / open question for PE:** a write now touches two tables (claim + payload), more migration
  and operational complexity, and a back-population of existing rows `[INFERRED]`. **Crucially:**
  Option B *relocates* which table physically holds the `stripe_event_id` UNIQUE. The idempotency
  *guarantee* is preserved as a single global UNIQUE (not weakened, not per-partition), but whether
  physically moving the constraint to a new table counts as "altering/replacing" the **CONFIRMED**
  surface under C-4's wording is **exactly the judgment call reserved for the PE**. Under Option A the
  constraint is untouched; Option B preserves the guarantee but on a different table. `[ACTION — PE
  decision; flagged, not resolved]`

---

## 5. Sequencing note (per C-4)

`[CONFIRMED — C-4 ordering rule]` **Phase 1 (retention + payload-NULLing, §2) must complete and be
validated before Phase 2 (partitioning, §3) begins.** Rationale: retention shrinks the working set
and de-risks the schema (PK) changes partitioning requires; C-4 explicitly supersedes the prior
partitioning-first plan. Partitioning is considered **only after** retention is in place and **only**
for the §3.1-qualifying append-only ledgers. `stripe_webhook_events` (and, by the §3.2 `[INFERRED]`
extension, `stripe_refund_events`) are never partitioned as-is.

---

## 6. Dependencies & open items (P2-9 local summary)

- (a) **Ticket B FK decision status:** not supplied in prompt `<INPUTS>`; in-repo Accepted candidate
  (`docs/DECISION_LEDGER_IMMUTABILITY_FK.md`) supplies the append-only trigger facts this doc relies
  on in §2.3/§3. The trigger behavior is `[CONFIRMED]`; finalizing the §2.3 ledger-exclusion still
  wants PE ratification.
- (b) **TBD retention values:** every retention period and legal/business basis in §2.1 is `TBD`
  (not invented) — owner: PE + finance/legal.
- (c) **Unresolved `stripe_webhook_events` alternative requiring PE decision:** Option A vs Option B
  in §4 — left open per C-4; the `stripe_event_id` UNIQUE is not altered/dropped/replaced by this
  design under either reading.
- (d) **`[INFERRED]` extension flagged for PE:** treat `stripe_refund_events` like
  `stripe_webhook_events` (same global-UNIQUE/dedupe hazard) — outside C-4's literal scope, raised for
  a ruling (§3.2).
- (e) **Trigger-vs-partition-drop verification** (§2.3/§3.3): confirm the append-only trigger is
  strictly row-level and a partition DROP does not trip it, before any partitioning is scoped.
  `[UNPROVEN — verify during implementation]`
