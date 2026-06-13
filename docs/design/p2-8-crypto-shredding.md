# P2-8 — Per-Subject Crypto-Shredding Design (F-87)

**Type**: Design documentation only (no implementation, no migrations, no code diffs).
**Status**: DRAFT — for Principal Engineer review.
**Date**: 2026-06-13
**Backlog item**: `docs/FINDINGS_BACKLOG.md` **F-87** ("PII sprawl with no erasure path", High, Open).
**Direct predecessor**: `docs/DECISION_LEDGER_IMMUTABILITY_FK.md` (ticket-B FK decision; see §7).
**Reviewer**: Principal Engineer.

> **Scope guardrail (binding).** This is a docs-only artifact. Nothing here is an instruction to
> change anything under the backend tree. Every backend change implied below is *design intent*
> only and must be raised as its own implementation ticket with explicit stop-and-confirm before
> any code, migration, model, config, or trigger is touched. One such collision point is called
> out inline as a STOP-AND-CONFIRM block in §3.

> **Evidence tags.** Every load-bearing claim is tagged `[CONFIRMED]` (verified against repo
> source / `docs/DB_FACTS.md` / the ticket-B decision doc), `[INFERRED]` (reasoning or extension),
> `[UNPROVEN]` (needs verification not done here), `[ACTION]` (follow-up), or `PLACEHOLDER`
> (cannot be finalized until a named dependency lands). Untagged design claims are defects.

---

## 1. Threat model & goal

### 1.1 What "erasure" means here

`[CONFIRMED]` Today the system has **no field-level encryption and no erasure path** for personal
data, and the append-only ledger design directly conflicts with row deletion: a single application
encryption key cannot erase one individual, and the ledgers cannot be row-deleted at all (see §7).
Source: F-87; `docs/DECISION_LEDGER_IMMUTABILITY_FK.md` ("GDPR erasure debt is now explicit …
until F-87 crypto-shredding lands (PII leaves via key destruction, rows stay)").

**Crypto-shredding goal.** Erasure of a data subject is achieved by **destroying that subject's
encryption key**, not by deleting or mutating rows. Once the key is gone, every ciphertext written
under it becomes permanently undecryptable — the row physically remains (preserving append-only and
referential integrity), but the personal data it carried is unrecoverable. `[INFERRED — standard
crypto-shredding semantics]`

| Property | Physical row deletion | Crypto-shredding (this design) |
|---|---|---|
| Ledger immutability (F-83 triggers) | Violated (DELETE rejected `P0001`) `[CONFIRMED]` | Preserved — no row UPDATE/DELETE needed at shred time `[INFERRED]` |
| Referential integrity (FKs) | Cascade/restrict hazards (F-89) `[CONFIRMED]` | Untouched — rows stay `[INFERRED]` |
| Audit-trail completeness | Destroyed | Preserved (structure stays, PII becomes opaque) `[INFERRED]` |
| Erasure unit | Whole row | One subject's PII across all tables `[INFERRED]` |

### 1.2 Threat model (who we defend against, what we promise)

- **Adversary A — DB-at-rest disclosure** (backup leak, stolen replica, SQLi read, decommissioned
  disk). Plaintext PII in a leaked dump is the present exposure. `[CONFIRMED — this is the F-82 /
  F-87 class of concern already established in the backlog]` Goal: ciphertext-only at rest; a leaked
  dump of a shredded subject yields nothing.
- **Adversary B — over-broad internal access.** An operator or code path with table read access can
  read PII today. Goal: PII readable only by paths that can unwrap the subject DEK via the KMS/KEK.
  `[INFERRED]`
- **Compliance driver — right to erasure.** A verified erasure request must render the subject's PII
  unrecoverable within a bounded, auditable SLA, *without* deleting immutable ledger/audit rows.
  `[INFERRED]`

**Explicit non-goals.** This design does **not** defend against an attacker who has live KMS/KEK
unwrap privileges (that is a key-management / access-control problem), and does **not** retroactively
scrub plaintext that already leaked to logs/caches/backups *before* this design ships — those are
listed as residual risks in §6. `[INFERRED]`

---

## 2. Per-subject DEK lifecycle

Envelope encryption with two key tiers:

- **DEK (Data Encryption Key)** — one per *subject*. Symmetric (AEAD, e.g. AES-256-GCM or
  XChaCha20-Poly1305). Encrypts that subject's PII fields wherever they appear. `[INFERRED —
  design recommendation]`
- **KEK (Key-Encryption Key)** — small set, lives only inside a KMS/HSM/Vault (§5). Wraps (encrypts)
  DEKs. The KEK never leaves the boundary; the application only ever sees a *wrapped* DEK and asks
  the KMS to unwrap it on demand. `[INFERRED]`

"Subject" = the natural person whose data we hold: a guest (identified by guest identity, not
necessarily a `users` row), an account holder (`users`), or a contact-message sender. Exact subject
keying depends on the FK structure in §3, which is `PLACEHOLDER` pending ticket B.

### 2.1 Generation
`[INFERRED]` On first write of any PII for a subject, generate a fresh random DEK from a CSPRNG, use
it to encrypt the field, immediately wrap it under the current KEK, and persist only the wrapped
form plus a key version/identifier. The plaintext DEK is held in memory only for the duration of the
operation and then discarded; it is never persisted in plaintext and never logged.

### 2.2 Storage (key envelope / KEK relationship)
`[INFERRED — design recommendation, schema is PLACEHOLDER pending ticket B §3/§7]` A dedicated
subject-key registry maps `subject_ref → { wrapped_dek, kek_version, dek_version, created_at,
destroyed_at (nullable) }`. The registry stores **only wrapped DEKs**. Ciphertext columns carry a
self-describing header (algorithm id, kek_version, dek_version, IV/nonce, auth tag) so the system
stays crypto-agile and can identify which DEK is required to decrypt a given value. The plaintext
KEK exists only inside the KMS.

### 2.3 Rotation
- **DEK rotation** `[INFERRED]`: re-encrypt a subject's live PII under a new DEK; old DEK can then be
  destroyed. Optional and infrequent; not required for erasure.
- **KEK rotation** `[INFERRED]`: re-wrap all DEKs under the new KEK (cheap — DEK plaintext never
  needs to touch app code if the KMS supports re-wrap). Ciphertext payloads are untouched. Recommend
  scheduled KEK rotation independent of erasure.

### 2.4 Destruction (the shred)
`[INFERRED]` Destroy the subject's DEK such that it can never be unwrapped again: remove/zero the
wrapped DEK in the registry, record `destroyed_at`, **and** destroy the KMS-side material that any
copy of that wrapped DEK depends on (KEK version retirement or KMS key-version destruction, per §5).
No PII row is touched. See §4 for the full procedure.

---

## 3. In-scope fields/tables — `PLACEHOLDER` (pending ticket B FK decision)

> **`PLACEHOLDER` — blocking dependency.** Ticket B's foreign-key decision was **not supplied in
> this prompt's `<INPUTS>` block**, so per the task contract the subject↔table FK mapping that
> finalizes this section is marked PLACEHOLDER and must not be asserted as fact. During inspection
> an Accepted in-repo decision (`docs/DECISION_LEDGER_IMMUTABILITY_FK.md`) was located that names
> F-87 crypto-shredding as its direct successor and almost certainly *is* ticket B; its FK facts are
> reproduced below as `[CONFIRMED]` schema, but the *linkage* "this doc is ticket B" itself remains
> `PLACEHOLDER` until the reviewer confirms it. Do not finalize §3 scope on the assumption alone.

### 3.1 Candidate PII surface (from F-87, to be confirmed against ticket B)

`[CONFIRMED — F-87 enumerates these]` The PII surface named by F-87:

| Table | Field(s) | Subject | Notes |
|---|---|---|---|
| `bookings` | `guest_name`, `guest_email` | guest | F-87's named highest-risk booking PII `[CONFIRMED]` |
| `bookings` | `cancelled_by_email` (cancelled-actor snapshot) | actor | snapshot column `[CONFIRMED]` |
| `contact_messages` | sender name / email / message body | sender | F-87 names `contact_messages` as a phase-1 highest-risk target `[CONFIRMED]` |
| `deposit_events` | `actor_email` (+ `actor_role`) | actor | denormalized snapshot, **trigger-protected ledger** `[CONFIRMED]` |
| `admin_audit_logs` | `actor_email`, `actor_display_name` | actor | denormalized snapshot, **trigger-protected ledger** `[CONFIRMED]` |
| `ai_proposal_events` | `actor_email`, `actor_display_name` | actor | denormalized snapshot, **trigger-protected ledger** `[CONFIRMED]` |
| `users` | account email / name | account holder | the subject's own identity row `[INFERRED]` |
| `stripe_webhook_events` | `payload` (JSONB; may embed email/name/address from Stripe) | guest | overlaps P2-9 payload-NULLing; encryption-vs-NULL choice is a PE call `[INFERRED]` |

`PLACEHOLDER` The precise *subject key* for each row (which subject's DEK encrypts which field) is
governed by the FK structure ticket B defines. Example open question: a `deposit_events.actor_email`
belongs to the **actor** subject, while a co-located `booking_id` points at a booking whose
`guest_email` belongs to a **different** (guest) subject — so a single ledger row may carry
ciphertext under two different subject DEKs. The exact mapping is `PLACEHOLDER` until §7 clears.

### 3.2 Critical interaction with ticket B's append-only triggers

`[CONFIRMED — `docs/DECISION_LEDGER_IMMUTABILITY_FK.md`, D1]` The three ledgers
`deposit_events`, `admin_audit_logs`, `ai_proposal_events` each carry a `BEFORE UPDATE OR DELETE`
trigger (migration id `2026_06_12_000002`) that **rejects every UPDATE and every DELETE** with
SQLSTATE `P0001`, *except* the single sanctioned mutation "the actor-reference column changed, to
NULL, and nothing else."

`[INFERRED]` This has two consequences for crypto-shredding:

1. **Forward writes are clean.** New ledger rows can be written with PII already encrypted under the
   subject DEK. Shredding then needs **no row mutation** — destroying the DEK makes the in-place
   ciphertext opaque. This is exactly why crypto-shredding (not row deletion) is the sanctioned GDPR
   path for immutable ledgers. ✅
2. **Backfilling encryption onto existing plaintext ledger rows is blocked.** Existing rows already
   hold *plaintext* `actor_email` snapshots. Converting them to ciphertext is an UPDATE that touches
   a non-actor column — which the append-only trigger **rejects (`P0001`)**. This cannot be done
   without a backend change to the trigger and/or a controlled backfill path.

> ### ⛔ STOP-AND-CONFIRM (triggered here — design boundary, no action taken)
>
> **What would be needed.** To bring *pre-existing* ledger rows (`deposit_events`,
> `admin_audit_logs`, `ai_proposal_events`) under crypto-shredding, the plaintext snapshot columns
> (`actor_email`, `actor_role`, `actor_display_name`) of historical rows must be replaced with
> ciphertext. That is an UPDATE on a non-actor column, which the F-83 append-only triggers reject
> with SQLSTATE `P0001`.
>
> **Why it touches the backend.** Resolving it requires one of: (a) a one-time, explicitly-gated
> backfill path that the trigger temporarily allows for the encrypt-in-place migration; (b) widening
> the trigger allowlist to admit an "encrypt snapshot column" mutation shape; or (c) accepting that
> only *forward* (post-deploy) ledger rows are crypto-shred-capable and historical plaintext
> snapshots are handled by a different control (e.g. P2-9 retention/partition-drop of old
> partitions). Each of (a)/(b)/(c) is a change under the backend tree (trigger function, migration,
> and/or the ticket-B decision memo) and an amendment to an Accepted decision.
>
> **Action:** none taken. This is recorded as a decision the Principal Engineer must make before any
> backfill is scoped. `[ACTION — PE decision; do not implement from this doc]` The recommended
> default for *this* design is **(c)**: crypto-shredding covers forward writes; historical ledger
> plaintext is retired by P2-9 partition-drop, keeping the append-only contract untouched.
> `[INFERRED — recommendation]`

---

## 4. Operational procedure — "shred"

`[INFERRED — design-level runbook]`

1. **Intake & authorization.** Verified erasure request resolves to a `subject_ref`. Authenticated,
   authorized, and audit-logged (the audit row records *that* an erasure occurred and its subject
   reference — never the erased PII itself).
2. **Enumerate dependents.** Resolve every table/field encrypted under the subject's DEK (the §3
   map, finalized post-ticket-B). No row reads of plaintext are required.
3. **Destroy the DEK.** Remove/zero the wrapped DEK in the registry, set `destroyed_at`, and destroy
   the KMS-side dependency (KEK-version retirement or KMS key-version destruction per §5). After
   this step, no path — including a future restore of the DB *without* the KMS material — can unwrap
   the DEK. `[INFERRED]`
4. **Tombstone, don't delete.** Mark the registry entry `destroyed` (retain the tombstone for
   audit/SLA proof). Ledger/audit rows are **not** touched — their ciphertext is now permanently
   opaque. `[INFERRED]`
5. **Verification (must-pass).**
   - Attempt to decrypt a known ciphertext for the subject → **must fail / be unrecoverable**.
   - Confirm no live cache entry holds the subject's plaintext or unwrapped DEK (cache sweep / TTL
     expiry within the documented window).
   - Confirm the erasure event is recorded with subject ref + timestamp + operator, and **no PII**.
   - Record the **erasure SLA clock**: true unrecoverability is only guaranteed once all backups /
     replicas / WAL archives that still contain the wrapped DEK *or* the KMS material have aged out
     of retention (see §6). The certificate of erasure should state this bounded window. `[INFERRED]`
6. **Idempotency.** A repeat shred for an already-destroyed subject is a no-op that still returns a
   success/erasure-proof. `[INFERRED]`

---

## 5. Key storage recommendation (design-level only)

`[INFERRED — design recommendation; no credentials, no key material, no endpoints here]`

- **Pattern:** envelope encryption with a managed KMS or Vault Transit engine holding the KEK.
  Candidates: a cloud KMS (HSM-backed) or HashiCorp Vault Transit. The **KEK never leaves** the
  KMS/HSM boundary; the app calls *wrap*/*unwrap*/*destroy* operations and only ever holds wrapped
  DEKs at rest. `[INFERRED]`
- **Shred primitive:** prefer a KMS that supports per-version or per-key **destroy** so that "destroy
  the DEK" maps to an irreversible KMS operation, not merely deleting an app-side row (an app-side
  delete alone is reversible from a backup). `[INFERRED]`
- **Separation of duties:** the role that can *unwrap* DEKs (serve PII) should be distinct from the
  role that can *destroy* keys (perform erasure). `[INFERRED]`
- **Config, not env, in app code:** any future KMS wiring must be read via `config()` (never `env()`
  in runtime code) per the repo's non-negotiable constraints. `[CONFIRMED — CLAUDE.md constraint]`
  Stated here only as a guardrail for the eventual implementation ticket; **no config is written by
  this doc.**
- **Secrets hygiene:** no key material, credentials, or live endpoints appear in this design or any
  derived doc. `[CONFIRMED — CLAUDE.md: never commit secrets]`

---

## 6. Open risks

| # | Risk | Tag | Mitigation / note |
|---|---|---|---|
| R1 | **Backup / replication lag.** A destroyed DEK may persist in DB backups, read replicas, WAL/PITR archives, and KMS backups until they age out — true erasure SLA is bounded by the *longest* such retention. | `[INFERRED]` | Document the bounded window; align backup retention with the erasure SLA; ensure KMS destroy propagates to KMS backups. `[ACTION]` |
| R2 | **Cached plaintext / unwrapped DEK.** Decrypted PII or an unwrapped DEK living in Redis/object caches or app memory survives a shred until evicted. | `[INFERRED]` | Bound cache TTLs; never cache unwrapped DEKs beyond a request; sweep on shred. `[ACTION]` |
| R3 | **Log leakage of pre-encryption plaintext.** PII logged *before* the encrypt step (request logs, exception context, and notably the full Stripe `payload` JSONB persisted on `stripe_webhook_events`) is outside the crypto boundary. | `[INFERRED]` | Encrypt-before-log discipline; redact PII in logs; treat `stripe_webhook_events.payload` jointly with P2-9 (NULL/encrypt). `[ACTION]` |
| R4 | **Historical ledger plaintext vs append-only trigger** (the §3 STOP-AND-CONFIRM). | `[CONFIRMED — trigger behavior]` | PE chooses backfill strategy (a/b/c); default recommendation (c). |
| R5 | **KMS availability / accidental key destruction.** KMS outage blocks PII decryption (availability); an erroneous KEK destruction is mass, irreversible data loss. | `[INFERRED]` | HA KMS; guarded destroy with approval + dry-run; monitored. `[ACTION]` |
| R6 | **Dual-subject ledger rows.** One ledger row may carry ciphertext under two subjects' DEKs (actor vs guest); shredding one must not corrupt the other's recoverability or the row's integrity. | `[INFERRED]` | Per-field key headers (§2.2) so each ciphertext names its own DEK. `[ACTION — verify against ticket B map]` |
| R7 | **Crypto-agility drift.** Algorithm/key-size must be upgradeable without rewriting every payload. | `[INFERRED]` | Self-describing ciphertext header (alg id + versions) from day one. |

---

## 7. Dependencies

`[CONFIRMED]` **Ticket B FK decision is a blocker for finalizing §3.**

- **What it is:** the foreign-key + immutability decision for subject-linked tables. In-repo this is
  `docs/DECISION_LEDGER_IMMUTABILITY_FK.md` (Accepted 2026-06-12), covering `deposit_events`,
  `ai_proposal_events`, `admin_audit_logs`. Confirmed FK facts from it:
  - `deposit_events.booking_id → bookings(id)` **RESTRICT** (swapped from CASCADE, migration id
    `2026_06_12_000001`). `[CONFIRMED]`
  - `deposit_events.actor_id`, `admin_audit_logs.actor_id`, `ai_proposal_events.user_id` →
    `users(id)` **SET NULL** (nullable). `[CONFIRMED]`
  - All three ledgers: append-only trigger (migration id `2026_06_12_000002`); only the sanctioned
    `actor_col → NULL` UPDATE is allowed. `[CONFIRMED]`
  - That decision doc **explicitly names F-87 crypto-shredding as its successor** ("true GDPR erasure
    … blocked until F-87 crypto-shredding lands"). `[CONFIRMED]`
- **Why it blocks §3:** the subject↔field key mapping (which DEK encrypts which column on which row)
  is defined by these FKs and by the actor-vs-guest distinction. Until the reviewer **confirms that
  the in-repo decision doc is the "ticket B" this design consumes**, §3's mapping stays `PLACEHOLDER`
  and must not be asserted as final scope. `[CONFIRMED — per task contract]`
- **Secondary dependency:** the §3 STOP-AND-CONFIRM (historical-ledger backfill strategy) is itself
  gated on a PE decision and would amend the Accepted ticket-B memo. `[ACTION]`

### Dependencies & open items (P2-8 local summary)

- (a) **Ticket B FK decision status:** NOT supplied in prompt `<INPUTS>`; in-repo Accepted candidate
  located (`docs/DECISION_LEDGER_IMMUTABILITY_FK.md`); **linkage requires PE confirmation** → §3 is
  `PLACEHOLDER`.
- (b) **Historical-ledger backfill** (§3 STOP-AND-CONFIRM): PE must choose strategy (a)/(b)/(c);
  default recommendation (c) — forward-only crypto-shred + P2-9 partition-drop for legacy rows.
- (c) **KMS/Vault selection & destroy primitive** (§5): unresolved; needs a key-management decision.
- (d) **Residual-risk owners** R1–R7 (§6): each needs an owner before implementation is scoped.
