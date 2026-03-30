# Agent Self-Learning Memory — Operating Rules

This file is the agent behavior contract for the AGENT LEARNINGS system.
It governs when to read entries, when to write entries, how to reject bad entries,
and how to maintain the system over time.

Cross-references:
- Entry schema: `AGENT_LEARNINGS_SCHEMA.md`
- Active entries: `AGENT_LEARNINGS.md`
- Format examples: `AGENT_LEARNINGS_EXAMPLES.md` (illustrative only — do NOT cite as real failures)

---

## SECTION 1: READ RULES

**R-01** Before any booking-domain write task, read ALL entries where tags intersect with:
`[booking, locking, overlap, transaction]`. Stop after reading matching entries.
Do not read the full file.

> SOLEIL example: Before implementing `BookingService::createBooking()`, query AGENT_LEARNINGS.md
> for entries tagged `[booking]` or `[locking]`. If SL-004 (pessimistic locking pattern) matches,
> read it before writing any transaction block. Do not read the full file.

---

**R-02** Before any RBAC change or middleware modification, read ALL entries where tags intersect
with: `[rbac, authorization, middleware]`.

> SOLEIL example: Before adding a new route to `routes/v1.php`, search AGENT_LEARNINGS.md for
> entries tagged `[rbac]` or `[middleware]`. An entry about verifying backend middleware separately
> from React route guards must be read before the route is written.

---

**R-03** Before authoring or reviewing any migration, read ALL entries where tags intersect with:
`[migrations, postgresql, schema]`.

> SOLEIL example: Before writing a new migration that adds a column to `bookings`, search
> AGENT_LEARNINGS.md for entries tagged `[migrations]` or `[postgresql]`. An entry about
> PG-only syntax (`EXCLUDE USING gist`) requiring SQLite guards must be read before authoring.

---

**R-04** Before any frontend API contract change or TypeScript type edit, read ALL entries where
tags intersect with: `[api_contract, frontend, type-safety]`.

> SOLEIL example: Before updating `BookingResource` in `booking.api.ts`, search
> AGENT_LEARNINGS.md for entries tagged `[api_contract]`. An entry about the Laravel Resource
> class being authoritative over TypeScript interfaces must be read before making type changes.

---

**R-05** FORBIDDEN: reading the full AGENT_LEARNINGS.md at task start.
Full-file reads are prohibited. Read only tag-matched entries.
If context pressure exists, skip ARCHIVED entries first.

> SOLEIL example: At the start of a migration authoring task, do NOT read all entries.
> Use R-03 to scope the read to `[migrations, postgresql, schema]` tags only.

---

**R-06** Entries with `status: ARCHIVED` must be skipped in all normal reads.
They exist for audit history only.

> SOLEIL example: An entry about a deprecated locking pattern that has been superseded shows
> `status: ARCHIVED`. Do not read it during a new task. The corrected pattern is now in the
> active entry that replaced it.

---

**R-07** Entries with `review_status: NEEDS_REVIEW` or `review_status: SELF_RECORDED` must be
treated as `confidence: INFERENCE` regardless of their recorded confidence field.
Do not treat SELF_RECORDED as CONFIRMED.

> SOLEIL example: An agent wrote an entry with `confidence: CONFIRMED` but
> `review_status: SELF_RECORDED`. Per R-07, treat this as INFERENCE. Do not rely on it as a
> confirmed pattern. Flag it for human review before acting on it in a critical booking path.

---

**R-08** Entries in the PROPOSED_ENTRIES staging section of AGENT_LEARNINGS.md must NOT be used
to guide agent behavior. They are pending human review.

> SOLEIL example: An agent proposed SL-007 during a previous task. It sits in the PROPOSED_ENTRIES
> section. During the current task, do not read it as an active entry. Wait until a human moves it
> to the active entries section with `review_status: PEER_REVIEWED`.

---

## SECTION 2: WRITE RULES

**W-01** An agent MAY write a proposed entry ONLY when ALL conditions hold:
- (a) A real failure occurred in the current task — not hypothetical
- (b) Evidence is available and belongs to an accepted evidence type
- (c) The failure pattern is likely to recur in a future agent task
- (d) Confidence is `CONFIRMED` or `INFERENCE` — never `HYPOTHESIS`
- (e) No existing ACTIVE entry already covers this failure pattern
      (check by area + tags + mistake fingerprint before writing)

> SOLEIL example: An agent built a booking form and incorrectly used the React `useEffect`
> pattern instead of an AbortController — but this is a one-off UX issue unlikely to recur
> in booking-critical paths. Condition (c) fails: do not write an entry. If instead the agent
> applied closed-interval logic to the overlap query and a test failed, all five conditions hold:
> write a proposed entry.

---

**W-02** An agent MUST NOT write an entry if ANY of these are true:
- (a) The failure is session-specific and has no recurrence risk
- (b) The learning is already stated in ARCHITECTURE_FACTS.md or CONTRACT.md
      — reference it there, do not duplicate
- (c) The evidence source is a SQLite test being used to make a claim about
      PostgreSQL production behavior
- (d) The confidence level would be `HYPOTHESIS`
- (e) An active entry already covers this failure (merge instead)

> SOLEIL example: An agent wants to write an entry saying "booking overlap uses half-open interval."
> W-02(b) applies: this is already in ARCHITECTURE_FACTS.md line 11. Do not write the entry.
> Point to ARCHITECTURE_FACTS.md in the task summary instead.

---

**W-03** When writing a proposed entry, the agent must:
- (a) Write the entry to the PROPOSED_ENTRIES section only — never to active entries
- (b) Set `review_status: SELF_RECORDED`
- (c) Set `confidence: INFERENCE` unless runtime-proven
- (d) Set `stale_after` to 90 days from current date by default
- (e) Read the current highest `SL-NNN` id and use the next integer
- (f) Not modify any existing entry without explicit instruction
- (g) Add comment: `# PROPOSED — awaiting human review before activation`

> SOLEIL example: After a task where the agent accidentally skipped pessimistic locking in a
> booking cancellation path, the agent writes a new proposed entry to PROPOSED_ENTRIES with
> `id: SL-005` (next after SL-004), `review_status: SELF_RECORDED`, `confidence: INFERENCE`,
> and `stale_after: 2026-06-28`. The entry does NOT appear in the Active Entries section.

---

**W-04** An agent must NEVER promote a proposed entry to active status.
That action requires human review and an explicit commit.

> SOLEIL example: The agent wrote SL-005 in PROPOSED_ENTRIES. The agent must not move it to
> Active Entries — even if it is certain the entry is correct. The human engineer executes
> Step 5 of the staging workflow and commits with the required message format.

---

**W-05** SUCCESS METRICS — the agent must report these on each task completion where a new entry
was proposed:
- (a) Entry id proposed: `SL-NNN`
- (b) Failure class: `[area + tags]`
- (c) Whether this class appeared in a previous entry: YES/NO
      If YES: cite the `SL-NNN` of the prior entry

> SOLEIL example: At task end, report: "Proposed SL-007 — failure class: [booking, locking,
> pessimistic-lock]. Prior entry for this class: SL-004 (YES). SL-007 records a new code path
> where the same pattern failed." This makes the proposed entry visible to the reviewer.

---

## SECTION 3: REJECTION RULES

**REJ-01** Reject any entry where evidence is absent, empty, or contains these phrases:
`"assumed"`, `"inferred"`, `"based on prior context"`, `"seemed likely"`, `"probably"`, `"should be"`

> SOLEIL example: Entry draft states `evidence: "assumed the overlap constraint was present based
> on prior context"` → REJECTED. The agent must provide a file path + line or a failing test name.

---

**REJ-02** Reject any entry that contradicts a system invariant (INV-XX) without citing the exact
repo file path + line or migration name that overrides it.

> SOLEIL example: An entry states `"cancelled bookings can block overlap"` — this contradicts
> INV-02 (`pending`, `confirmed` only). Unless the agent cites a migration or schema change that
> adds `cancelled` to the exclusion constraint's status filter, REJECT the entry.

---

**REJ-03** Reject any entry whose `corrected_pattern` encodes a shortcut that bypasses any of:
- transaction discipline in booking writes
- RBAC enforcement at Laravel middleware level
- migration review before schema changes
- test coverage for booking correctness
- PostgreSQL exclusion constraint for overlap

> SOLEIL example: A proposed `corrected_pattern` shows `Booking::create($data)` without a
> wrapping `DB::transaction()` block → REJECTED. The corrected pattern must preserve the
> transaction even if the agent believes it is "safe to omit in this case."

---

**REJ-04** Reject any entry written to retroactively justify a mistake without a genuine corrected
pattern.

> SOLEIL example: An agent proposes an entry with `mistake: "Agent skipped migration review"`
> but the `corrected_pattern` is `"Migration review is optional for small changes"` → REJECTED.
> The corrected pattern must state the right behavior, not rationalize the shortcut.

---

**REJ-05** Reject duplicate entries. Before writing, verify by:
area match + ≥2 tag overlap + similar mistake fingerprint.
If duplicate found: update the existing entry's `notes` field to record the recurrence.
Do not create a new entry.

> SOLEIL example: SL-003 already records "agent used closed interval in overlap query."
> A new task produces the same failure. Instead of writing SL-008 with the same pattern,
> update SL-003's `notes` field: "Recurred on 2026-05-12 during booking refactor task."
> Increment the recurrence count if tracked.

---

**REJ-06** Reject any entry where `confidence` is `HYPOTHESIS`.
Entries at this confidence level provide no operational value and increase hallucination risk.

> SOLEIL example: An agent writes `confidence: HYPOTHESIS` on an entry speculating that
> "the booking service might not handle DST transitions correctly." → REJECTED. Either
> provide real evidence and downgrade to `INFERENCE`, or do not write the entry.

---

## SECTION 4: STALENESS AND MAINTENANCE RULES

**S-01** Entries past their `stale_after` date must be flagged `status: UNDER_REVIEW`
before being used to guide agent behavior in a new task.

> SOLEIL example: SL-002 has `stale_after: 2026-04-30` and today is 2026-05-15.
> Before citing SL-002 to guide a booking task, change its status to `UNDER_REVIEW`
> and flag for human review. Do not use it as `ACTIVE` guidance until reviewed.

---

**S-02** At 30-entry threshold OR quarterly (whichever comes first), a full maintenance sweep
is required:
- Entries past `stale_after` → `ARCHIVED` (unless still recurring)
- Entries confirmed in 3+ tasks → candidate for `PROMOTED`
- Duplicate entries → merged, older one `ARCHIVED`

> SOLEIL example: After 90 days, SL-001 through SL-030 exist. Entries SL-001 through SL-008
> have passed their `stale_after` dates. Run the sweep: SL-001 through SL-005 archived (no
> recurrence), SL-006 promoted to ARCHITECTURE_FACTS.md (confirmed 4 times), SL-007 and SL-008
> left active (still recurring).

---

**S-03** When an entry is `PROMOTED`:
- Update `status` to `PROMOTED`
- Add to `promotion_rule` field: `"Promoted to [target file] on [date]"`
- Do not delete the entry — retain for audit history
- Skip during all active reads

> SOLEIL example: SL-006 (pessimistic locking in booking writes, confirmed 4 times) is promoted.
> Its `promotion_rule` is updated: `"Promoted to ARCHITECTURE_FACTS.md — Concurrency Control
> section on 2026-06-01"`. Status set to `PROMOTED`. The entry remains in the file for audit.

---

**S-04** ARCHIVED entries must never be deleted.
Retain for governance and audit history.
`status: ARCHIVED` means: skip during reads, keep for history.

> SOLEIL example: SL-002 is archived after its failure pattern no longer applies (schema changed).
> It remains in the file with `status: ARCHIVED`. A future audit can trace why the pattern was
> once relevant and when it was resolved.

---

## SECTION 5: ANTI-CORRUPTION GUARDRAILS

**G-01** AGENT_LEARNINGS.md is NOT a source of architectural truth.
When AGENT_LEARNINGS.md conflicts with ARCHITECTURE_FACTS.md:
the architecture doc wins, unconditionally, unless a committed migration or schema file proves
the architecture doc is outdated.

> SOLEIL example: AGENT_LEARNINGS.md entry SL-003 states "bookings.location_id should be
> normalized." ARCHITECTURE_FACTS.md states it is intentional denormalization (INV-06).
> G-01 applies: ARCHITECTURE_FACTS.md wins. SL-003 must be flagged for human review and
> corrected or archived.

---

**G-02** The agent must not cite AGENT_LEARNINGS.md as proof of system behavior.
It is evidence of agent failure patterns only. Citing it as "the system works this way"
is a G-02 violation.

> SOLEIL example: An agent states "according to SL-005, the booking service uses pessimistic
> locking." This is a G-02 violation. The agent must instead cite
> `CancellationService.php:118` or `ARCHITECTURE_FACTS.md` as the source of truth about
> what the system does.

---

**G-03** No entry may encode any of the following as acceptable behavior:
- skipping overlap checks
- bypassing pessimistic locking in booking writes
- weakening RBAC to UI-only enforcement
- omitting migration review
- using SQLite behavior as proxy for PostgreSQL production

> SOLEIL example: A proposed `corrected_pattern` shows `// overlap check skipped for
> same-location bookings`. → REJECTED under G-03. No entry may suggest overlap checks
> can be omitted under any circumstance.

---

**G-04** An agent may not change its own SELF_RECORDED entry to CONFIRMED.
Confidence upgrades to CONFIRMED require human peer review and a status change to
PEER_REVIEWED.

> SOLEIL example: The agent writes SL-007 with `review_status: SELF_RECORDED` and
> `confidence: INFERENCE`. In the next task, the agent encounters the same pattern and
> thinks "this confirms it." The agent MUST NOT update SL-007 to `confidence: CONFIRMED`
> or `review_status: PEER_REVIEWED` autonomously.

---

**G-05** "It worked before" is not evidence.
Only current repo state, passing tests against PostgreSQL, and runtime output from the
target environment count as evidence.

> SOLEIL example: An agent states `evidence: "this pattern worked in the previous booking
> task"` → REJ-01 applies (vague evidence). The agent must provide a current file path
> + line, a passing test command, or runtime output from the PostgreSQL environment.

---

**G-06** AGENT_LEARNINGS_EXAMPLES.md must never be read as operational learnings.
Agents must not cite example entries as historical failures.
The examples file is for schema training only.

> SOLEIL example: An agent reads SL-EX-03 from AGENT_LEARNINGS_EXAMPLES.md and cites it
> as "a prior case where RBAC was verified only at the React layer." → G-06 violation.
> The examples file header states these are NOT historical facts. The agent must not
> treat any SL-EX-NNN entry as a real failure.

---

## SECTION 6: SCOPE BOUNDARIES

The learning system applies to ONLY these four task domains initially.
Do not expand scope until Phase 3 review confirms the system is working.

| Scope ID | Domain |
|----------|--------|
| `SCOPE-01` | Booking mutations (any write to the bookings table) |
| `SCOPE-02` | Migrations and schema changes |
| `SCOPE-03` | RBAC / authorization middleware changes |
| `SCOPE-04` | Frontend ↔ backend API contract changes (types, resources) |

For all other task types: agents operate without mandatory learning reads.
They may still write proposed entries if W-01 conditions are met.

> SOLEIL example: A task that only updates Vietnamese UI copy strings in React does not
> fall within SCOPE-01 through SCOPE-04. Read rules R-01 through R-04 are not mandatory.
> However, if a failure occurs that meets W-01 conditions, a proposed entry may still be written.

---

## SECTION 7: PROPOSED ENTRY STAGING WORKFLOW

```
Step 1  Agent detects qualifying failure (W-01 conditions met)

Step 2  Agent writes entry to PROPOSED_ENTRIES section of AGENT_LEARNINGS.md
        with review_status: SELF_RECORDED
        — NEVER to the active entries section

Step 3  Agent reports the proposed entry id in task summary
        (see W-05 for required reporting format)

Step 4  Human engineer reviews the proposed entry:
        - Verifies evidence is real and correctly typed
        - Verifies corrected_pattern is architecturally sound
        - Verifies no invariant contradiction without override evidence
        - Verifies confidence level is appropriate

Step 5  Human moves entry from PROPOSED_ENTRIES to active entries
        Updates review_status to PEER_REVIEWED if confidence ≥ INFERENCE
        — Agent must NEVER execute this step autonomously (see W-04)

Step 6  Human commits with message:
        "learning(SL-NNN): promote proposed entry — [area] [mistake-summary]"
```

---

## SECTION 8: SUCCESS METRICS

After 2 weeks of Phase 2 operation, measure:

**METRIC-01 — Recurring failure rate**
Definition: Count of agent tasks where the failure class appeared in an existing ACTIVE entry
vs. tasks where the failure was new.
Target: recurring failures trending downward after 10 entries.

**METRIC-02 — Rejection hit rate**
Definition: Count of times an agent attempted to write an entry that was rejected by
REJ-01 through REJ-06.
Target: rejection rate under 20% by end of Phase 2.
High rejection rate signals: write rules are too loose OR agent is generating too many
speculative entries.

**METRIC-03 — Promotion and archive velocity**
Definition: Ratio of entries reaching PROMOTED or ARCHIVED status vs. total entries after
90 days.
Target: ≥ 30% of entries resolved (promoted or archived) by first quarterly sweep.
Unresolved entries accumulate governance debt.

If METRIC-01 shows no improvement after 20 active entries:
Do not add more entries. Audit the read rules. The problem is retrieval failure, not
insufficient learning content.

---

## SECTION 9: ROLLOUT PLAN

**PHASE 1 — Scaffolding** (complete in one session)
- ✓ Create AGENT_LEARNINGS.md (empty entries, staging section)
- ✓ Create AGENT_LEARNINGS_EXAMPLES.md (8 illustrative entries)
- ✓ Create AGENT_LEARNINGS_SCHEMA.md (full schema definition)
- ✓ Create AGENT_LEARNINGS_OPERATING_RULES.md (this file)
- ✓ Create docs/agents/README.md if no index exists
- ✓ Add 3–5 line pointer to CLAUDE.md
- ✗ Do NOT seed real entries. Do NOT fake evidence.

Commit: `"feat(agents): AGENT_LEARNINGS scaffold — Phase 1"`

**PHASE 2 — Seeding** (next 2 weeks, during real agent tasks)
- Apply to SCOPE-01 through SCOPE-04 task domains only
- Write proposed entries only after real failures with real evidence
- Target: 5–10 quality entries. Zero padding.
- Human review required before any entry goes active
- Gate to Phase 3: ≥ 5 peer-reviewed ACTIVE entries

**PHASE 3 — Operational** (after Phase 2 gate)
- Enforce R-01 through R-08 as mandatory pre-task reads for SCOPE-01 through SCOPE-04
- Schedule first quarterly review
- Evaluate promotion candidates
- Gate to Phase 4: ≥ 10 active entries + first quarterly review done

**PHASE 4 — Governance** (after 30 entries or 90 days)
- Run full maintenance sweep (S-02 rules)
- Measure METRIC-01 through METRIC-03
- If metrics show no improvement: pause new entries, audit read rules
- If metrics show improvement: consider expanding scope
- Never expand scope without evidence of effectiveness in current scope

---

## SECTION 10: RISK REGISTER

| Risk | Description | Controls |
|------|-------------|----------|
| RISK-01 | **Hallucination persistence** — agent writes speculative content; future agents treat it as truth | REJ-01 (no evidence → reject); REJ-06 (no hypothesis); R-07 (SELF_RECORDED treated as INFERENCE); W-04 (staging prevents auto-activation) |
| RISK-02 | **Stale truth drift** — entries become incorrect as codebase evolves | `stale_after` field; quarterly sweep (S-02); ARCHIVED status skipped in reads (R-06) |
| RISK-03 | **Ownership blur** — AGENT_LEARNINGS.md accumulates architecture facts and becomes a competing truth source | G-01 (architecture doc wins); W-02(b) (no duplication from ARCHITECTURE_FACTS.md); PROMOTED entries point to canonical target |
| RISK-04 | **Context bloat** — file grows large; agent reads all entries; context window consumed | R-05 forbids full-file reads; R-01–R-04 scope reads by tags; 30-entry sweep trigger (S-02) |
| RISK-05 | **Bad learning accumulation** — a wrong corrected_pattern gets peer-reviewed as correct and is followed by multiple agents | REJ-03 blocks safety-degrading shortcuts; human review required before activation; G-01–G-05 block invariant violations |
| RISK-06 | **Governance abandonment** — team stops reviewing proposed entries; staging section grows; system becomes theater | W-05 requires agent to report proposed entry in task summary; Phase 4 assessment pauses writes if metrics show no improvement |
| RISK-07 | **Scope creep** — system expands beyond SCOPE-01 through SCOPE-04 before effectiveness is proven | Scope expansion gated by Phase 4 metrics; explicit rule: never expand without evidence |
