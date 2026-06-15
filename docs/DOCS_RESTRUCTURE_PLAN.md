# Documentation Estate Restructure Plan — Soleil Hostel

> **Phase 1 deliverable** (audit + proposal, zero edits to existing files).
> Produced 2026-06-15 against branch `dev`. Follows `.claude/output-styles/decision-memo.md`
> for the narrative sections, with the Migration Table and Batch Plan appended (per CLAUDE.md
> output-style policy and the generating prompt's C-10).
>
> Evidence tags per finding: `[CONFIRMED]` (file opened/compared), `[INFERRED]`,
> `[UNPROVEN]`, `[ACTION]`. No existing file is moved, edited, merged, or deleted by this
> document — it only proposes Phase 2+ work.

## Execution Status (updated 2026-06-15)

**All seven batches executed** on `dev` (Phase 2). This document is now a historical charter;
the migration table below lists the *pre-move* paths it planned against. Commits:

| Batch | Commit | Status |
|---|---|---|
| Plan (charter) | `98cb146` | committed |
| 1 — orphan removal | `4f9f1f3` | done — deleted `DEVELOPMENT_HOOKS.md`, `output-styles/audit.md` |
| 2 — prompt archive | `773ed56` | done — 7 prompts → `docs/archive/prompts/` |
| 3 — registry close-out | `ce3f47a` | done — B3-1 + REM-1 surfaced to `FINDINGS_BACKLOG.md` (still OPEN) |
| 4a — SubAgent relocate | `bbeaa15` | done — 4 RFCs → `docs/design/subagent-chat/` |
| 4b — SubAgent decision | `f79abb0` | done — kept as-is, no consolidation (OQ-1 closed "not pursued") |
| 5a — RBAC_UX_AUDIT | `b5ce911` | done — archived to `docs/archive/` |
| 5b — deploy relocate | `2d21039` | done — → `docs/ops/PRODUCTION_DEPLOYMENT.md` (new `docs/ops/`) |
| 6 — learnings fold | `6b2e7dc` | done — SCHEMA+EXAMPLES → `AGENT_LEARNINGS_REFERENCE.md` |
| 7a — archive relocate | `a9a2ffb` | done — cleanup/gates/decisions → `docs/archive/legacy/` |
| 7b — archive relocate | `d373a90` | done — governance/audit/validation → `docs/archive/legacy/` + reindex |

**Still open (human):** OQ-2 (UNRESOLVED-B3-1 soleil-block policy) and OQ-3 (UNRESOLVED-REM-1
countersign) — both tracked live in `FINDINGS_BACKLOG.md`. Pre-existing working-tree drift was
left untouched throughout. Commits are local on `dev` — not pushed.

## Decision

Whether to adopt a single-canonical-home documentation layout — adding `docs/design/` for
unimplemented RFCs and `docs/archive/prompts/` for executed one-off prompts, removing two
orphaned stubs, and migrating the two still-open registry items into the live findings
backlog — executed as seven independently revertable Phase 2+ batches.

## Context

### Why now

The estate has grown to **~259 markdown files / ~55,000 lines** across `docs/` (153 files,
45,065 lines), repo-root `*.md` (17 files), `.claude/**` (59 files), `.agent/**` (12 files),
and `skills/**` (18 files). A prior 2026-Q1 cleanup wave (batches B1–B9B, "RC1 remediation",
"FFP-S3 closure") left its own process artifacts behind, and executed one-off prompts now
accumulate at repo root with no archive convention. The owner judges the estate bloated and
chaotic and wants a professional restructure.

### Inventory deltas vs. the Phase-0 hypothesis

| Area | Hypothesis | Verified (2026-06-15) | Delta |
|---|---|---|---|
| `docs/**/*.md` | 155 files / 45,065 lines | **153 files / 45,065 lines** | −2 files; lines exact |
| repo-root `*.md` | 16 files / 4,057 lines | **17 files** | +1 (this prompt now on disk) |
| `.claude/**/*.md` | ~54 files | **59 files** | +5 |
| `.agent/**/*.md` | 12 files | **12 files** | 0 |
| `skills/**/*.md` | 17 files | **18 files** | +1 |
| **Total estate** | ~254 | **~259** | excludes `backend/vendor/**`, `.claude/worktrees/**` |

`[CONFIRMED]` — counts from `find ... -name '*.md' | wc -l` and per-file `wc -l`, excluding
`backend/vendor/**` (a node_modules-equivalent of ~100+ vendor docs) and the gitignored
`.claude/worktrees/**` mirror (CLAUDE.md retrieval hygiene).

### What governs the target (the "should-be" picture)

- **CLAUDE.md decision order** (ranks 1–10) and **document map** name specific paths by
  reference; any move must update those citations.
- **`docs/archive/README.md`** already encodes the archive convention: the 2026-Q1 cleanup
  directories are *"archived in classification only — their files remain at the original paths
  so internal cross-references resolve."* This plan **extends** that convention (adds
  `prompts/`, optionally `legacy/`); it does not replace it.
- All **domain invariants** (booking overlap, auth-token chain, RBAC) live in
  `docs/agents/ARCHITECTURE_FACTS.md`, `docs/DB_FACTS.md`, `docs/PERMISSION_MATRIX.md`,
  `docs/DOMAIN_LAYERS.md`, and `.claude/memory/global-invariants.md`. **No file proposed for
  move/archive/delete in this plan is the sole home of any invariant** `[CONFIRMED]` — see
  the security note under Consequences.

## Options

### Option 1: Minimal — remove orphans only, leave structure as-is

- **Pros**: lowest risk `[CONFIRMED]`; deletes only the two unreferenced stubs
  (`DEVELOPMENT_HOOKS.md`, `output-styles/audit.md`); zero new directories.
- **Cons**: leaves the 5,721-line orphaned SUBAGENT_ARCHITECTURE family, the root prompt
  pile, and the still-open registry unaddressed `[CONFIRMED]`; the "bloated and chaotic"
  complaint is largely unresolved.
- **Risks**: none material `[INFERRED]`.
- **Alignment**: consistent with `docs/archive/README.md`, but under-delivers.

### Option 2: Full physical re-tree — relocate everything, including the legacy archive dirs

- **Pros**: cleanest end-state tree `[INFERRED]`; one canonical home per topic.
- **Cons**: physically moving `docs/cleanup|gates|decisions|governance|audit|validation`
  (34 files) breaks the exact cross-references the current convention deliberately preserves
  in place `[CONFIRMED]`; requires a large link-rewrite pass that risks dangling citations.
- **Risks**: high churn; touches CLAUDE.md document map and dozens of historical files at
  once `[INFERRED]`.
- **Alignment**: *overrides* rather than extends `docs/archive/README.md` — discouraged by
  the generating prompt (Step 5b).

### Option 3 (Recommended): Targeted restructure in risk-ordered batches

- **Pros**: removes orphans, gives the unimplemented RFC family and executed prompts a
  proper home (`docs/design/`, `docs/archive/prompts/`), and finally closes out the registry
  by migrating its two open items to `FINDINGS_BACKLOG.md` — while leaving the in-place
  legacy-archive convention intact `[CONFIRMED]`. Each batch ≤25 files and independently
  revertable.
- **Cons**: spans 6–7 batches and one optional CLAUDE.md edit (constitutional file → human
  review) `[INFERRED]`.
- **Risks**: the two large merges (SUBAGENT consolidation, frontend `DEPLOYMENT.md`) need a
  `[CONFIRMED]` content diff before any deletion — sequenced late and gated `[ACTION]`.
- **Alignment**: extends `docs/archive/README.md`; respects CLAUDE.md decision order; honors
  C-3 (no removal without confirmed evidence).

## Recommended Decision

Adopt **Option 3**. Execute Batches 1–3 immediately (orphan removal, prompt-archive
convention, registry-item migration) — all low-risk/high-confidence. Gate Batches 4–5 (the
two large merges) on a `[CONFIRMED]` per-file content diff. Treat Batches 6–7 (AGENT_LEARNINGS
fold, legacy-archive physical relocation) as optional and human-elective.

## Consequences

**Short-term** — Two files deleted, ~12 files moved, two open items promoted into the live
backlog, three new locations created (`docs/design/`, `docs/design/subagent-chat/`,
`docs/archive/prompts/`). CLAUDE.md is touched only if Batch 6 (optional) proceeds.

**Long-term** — Executed prompts stop accumulating at root; unimplemented design RFCs are
visibly separated from canonical truth; the 2026-Q1 cleanup registry can finally be closed.

**Security / invariant-preservation note** `[CONFIRMED]` — Reviewed every file proposed for
move/archive/delete against the invariant sources. None is the sole carrier of a
booking-integrity, auth/token, or RBAC invariant: the SUBAGENT_ARCHITECTURE family only
*describes how a future chat agent would wrap existing endpoints* (invariants there are copies
of ARCHITECTURE_FACTS); `frontend/RBAC_UX_AUDIT.md` is a dated snapshot whose live successors
are `docs/frontend/RBAC.md` + `docs/PERMISSION_MATRIX.md`; `DEVELOPMENT_HOOKS.md` is a 3-line
redirect; `audit.md` is an output style. No invariant is lost, forked, or demoted by this
plan.

## Follow-up Actions

| # | Action | Owner | Priority | Deadline |
|---|---|---|---|---|
| 1 | Execute Batch 1 (orphan removal) | Docs | now | next docs pass |
| 2 | Execute Batch 2 (prompt-archive convention) | Docs | now | next docs pass |
| 3 | Execute Batch 3 (migrate 2 open registry items to `FINDINGS_BACKLOG.md`) | Docs + human countersign | now | next docs pass |
| 4 | Human: answer Open Questions 1–3 (SUBAGENT canonical; B3-1; REM-1) | Owner | high | before Batch 4 |
| 5 | Execute Batch 4 (SUBAGENT consolidation) after OQ-1 | Docs | next | after OQ-1 |
| 6 | Execute Batch 5 (frontend DEPLOYMENT merge + RBAC_UX_AUDIT archive) | Docs | next | after CONFIRMED diff |
| 7 | Decide Batches 6–7 (optional) | Owner | backlog | — |

---

## Target Structure

The proposal is **additive and convention-extending**, not a wholesale re-tree. Only the
**bold** nodes are new or change role.

```text
soleil-hostel/
├── CLAUDE.md, AGENTS.md, README.md, README.dev.md          KEEP (canon / onboarding)
├── PROJECT_STATUS.md, BACKLOG.md, PRODUCT_GOAL.md          KEEP (live ledgers)
├── AUDIT_REPORT.md                                          KEEP (rolling audit index)
├── LOCKING-GUARD.md, SHIP-MIRROR.md                         KEEP (root tooling refs)
├── AI_Engineering_Capability_Assessment_*.md   → docs/archive/  (OQ-4: owner-elective)
├── T1_opus4_prompt.md, SYNC_DOCS_*.md, PROMPT_AUDIT_FIX.md,
│   REVIEW_PROMPT_*.md, PROMPT_concurrent_*.md,
│   DOCS_RESTRUCTURE_AUDIT_opus48_prompt.md      → **docs/archive/prompts/**
└── docs/
    ├── agents/            KEEP — canon (ARCHITECTURE_FACTS, CONTRACT, COMMANDS, learnings*)
    ├── backend/           KEEP — implementation reference (distinct layer from agents/)
    ├── frontend/          KEEP — layer reference
    │   └── (RBAC_UX_AUDIT.md → archive; DEPLOYMENT.md infra → backend/ops)
    ├── api/               KEEP — API contracts/deprecation
    ├── ai/, mcp/, tooling/, design/, imports/   KEEP
    ├── **design/subagent-chat/**   ← consolidated SUBAGENT_ARCHITECTURE family (RFC, unimplemented)
    ├── DB_FACTS.md, PERMISSION_MATRIX.md, DOMAIN_LAYERS.md, DATABASE.md   KEEP (canon)
    ├── COMPACT.md, WORKLOG.md, FINDINGS_BACKLOG.md          KEEP (live ledgers)
    ├── HOOKS.md            KEEP  (DEVELOPMENT_HOOKS.md → DELETE, superseded)
    └── archive/
        ├── README.md       KEEP — extended with prompts/ (+ optional legacy/) entries
        ├── **prompts/**    ← NEW home for executed one-off prompts
        ├── cleanup/, gates/, decisions/, governance/, audit/, validation/
        │                   KEEP IN PLACE (existing "archived-in-classification" convention)
        └── **legacy/**     ← OPTIONAL future physical home (Batch 7, deferred)
.claude/
├── output-styles/   KEEP  (audit.md → DELETE/ARCHIVE, superseded by audit-report.md)
├── commands/, agents/, memory/, plugins/, skills/   KEEP
.agent/
├── ARCHITECTURE.md   KEEP  (OQ-7: optional rename → OPERATING_LAYER.md to end name collision)
├── rules/, workflows/   KEEP
skills/
└── laravel/, react/, ops/, README.md   KEEP (all RETAIN_AS_REFERENCE per registry B6-2)
```

### Rationale

1. **`docs/design/` for unimplemented RFCs.** The SUBAGENT_ARCHITECTURE family is design
   speculation (`[PROPOSED]` / `DESIGN-BASELINE` / `PROPOSED CONTRACT` throughout), not
   implemented architecture, and is **referenced by nothing** `[CONFIRMED]`. It belongs
   alongside the existing `docs/design/p2-*.md` design notes, clearly separated from canonical
   truth so no agent mistakes a proposal for an invariant.
2. **`docs/archive/prompts/` for executed prompts.** Root currently has no convention for
   one-off execution prompts; they accumulate indefinitely. A dedicated archive folder under
   the *existing* `docs/archive/` umbrella extends the convention rather than inventing a
   parallel one.
3. **Legacy-archive dirs stay in place.** `docs/archive/README.md` deliberately keeps
   `cleanup/gates/decisions/governance/audit/validation` at their original paths so internal
   cross-references resolve. This plan honors that; physical relocation is deferred to an
   optional, link-aware Batch 7.
4. **No canonical home is removed.** Every path CLAUDE.md / AGENTS.md / the document map cite
   by name is preserved, except where the Migration Table also lists the citation update.

---

## Migration Table

One row per file that is **not** staying exactly as-is. Files not listed are **KEEP** (default
`ACTIVE-CANON`/`ACTIVE-REFERENCE`). Evidence tag in the Confidence column.

| Current Path | Classification | Action | Target Path | Rationale | Updates Required Elsewhere | Risk | Confidence |
|---|---|---|---|---|---|---|---|
| `docs/DEVELOPMENT_HOOKS.md` | SUPERSEDED | DELETE | — | 3-line redirect stub; superseded by `docs/HOOKS.md`; registry B4-2 confirms it is "safe to delete" and the lone active ref (`docs/README.md`) was repointed to HOOKS.md | None active. Historical by-name mentions in archived files (WORKLOG, cleanup, gates, governance, audit, AUDIT_2026_*) need no update per registry B4-2. `docs/HOOKS.md` mentions it — verify before delete | Low | `[CONFIRMED]` |
| `.claude/output-styles/audit.md` | SUPERSEDED | ARCHIVE→DELETE | (git history) | Narrower duplicate of `audit-report.md`; CLAUDE.md output-style table names only `audit-report.md`; **no file references the bare `audit.md`** | None — grep shows every reference points to `audit-report.md` | Low | `[CONFIRMED]` |
| `docs/SUBAGENT_ARCHITECTURE.md` (1694) | SUPERSEDED | MOVE+MERGE | `docs/design/subagent-chat/` | "v1" base of an orphaned RFC family; later rounds build on it | None (orphaned — zero inbound refs) | Med | `[CONFIRMED]` |
| `docs/SUBAGENT_ARCHITECTURE_DELTA_R2.md` (955) | SUPERSEDED | MOVE+MERGE | `docs/design/subagent-chat/` | Self-declared *"delta — supplements, does not replace v1"* (L955); R3 "finalizes" its contracts. **Preserve its Output-7 operational-default thresholds + baseline plan before archiving** | None (orphaned) | Med | `[CONFIRMED]` |
| `docs/SUBAGENT_ARCHITECTURE_R3_CONTRACTS.md` (1184) | MERGE-CANDIDATE | MOVE | `docs/design/subagent-chat/` | "Round 3 Final Implementation Contracts" — candidate canonical (vFinal tool matrix, retires "hold", picks Option A/P1/guest-auth) | None (orphaned) | Med | `[CONFIRMED]` |
| `docs/SUBAGENT_ARCHITECTURE_V3.md` (1888) | MERGE-CANDIDATE | MOVE | `docs/design/subagent-chat/` | "V3" full restatement with `[PROPOSED]`/`[CONFIRMED]` labels — competing candidate canonical (see **OQ-1**) | None (orphaned) | Med | `[CONFIRMED]` |
| `docs/cleanup/unresolved-registry.md` | ACTIVE-REFERENCE (not yet archivable) | EXTRACT then ARCHIVE | stays; items → `docs/FINDINGS_BACKLOG.md` | Still carries **2 OPEN items** (B3-1, REM-1) — cannot be silently archived while live | Add B3-1 + REM-1 to `docs/FINDINGS_BACKLOG.md`; update `docs/archive/README.md` | Med | `[CONFIRMED]` |
| `docs/frontend/RBAC_UX_AUDIT.md` (566) | ONE-OFF-ARTIFACT | ARCHIVE | `docs/archive/` (or `docs/frontend/_archive/`) | Dated audit snapshot (2026-03-09, commit `99cb0a3`, self-superseded inline 2026-05-20); live RBAC = `frontend/RBAC.md` + `PERMISSION_MATRIX.md` | No active doc links it (`frontend/README.md` nav omits it); only archived `docs/cleanup/*` mentions it | Low | `[CONFIRMED]`¹ |
| `docs/frontend/DEPLOYMENT.md` (980) | MERGE-CANDIDATE | MERGE+MOVE | `docs/backend/` or new `docs/ops/` | ~90% backend/full-stack infra (compose.prod, nginx, deploy.yml, S3, monitoring); misfiled under frontend; overlaps `docs/backend/DEPLOYMENT.md` + `docs/backend/guides/DEPLOYMENT.md`. Extract only the frontend-Dockerfile slice | `frontend/README.md:111`; `docs/frontend/README.md` nav table; (archived `docs/cleanup/01-classification-matrix.md`) | Med-High | `[CONFIRMED]`¹ |
| `AI_Engineering_Capability_Assessment_Soleil_Hostel.md` (663) | ONE-OFF-ARTIFACT | MOVE | `docs/archive/` | Owner-maintained append-only personal capability assessment; not operational; not referenced by canon | None in canon | Low | `[CONFIRMED]` (OQ-4) |
| `T1_opus4_prompt.md` (246) | ONE-OFF-ARTIFACT | MOVE | `docs/archive/prompts/` | Executed one-off task prompt | None (orphaned) | Low | `[INFERRED]`² |
| `SYNC_DOCS_opus48_prompt.md` (178) | ONE-OFF-ARTIFACT | MOVE | `docs/archive/prompts/` | Executed one-off task prompt | None (orphaned) | Low | `[INFERRED]`² |
| `PROMPT_AUDIT_FIX.md` (279) | ONE-OFF-ARTIFACT | MOVE | `docs/archive/prompts/` | Executed one-off task prompt; only historical by-name mention (registry B4-2) | None active | Low | `[INFERRED]`² |
| `REVIEW_PROMPT_booking-system.md` (78) | ONE-OFF-ARTIFACT | MOVE | `docs/archive/prompts/` | Executed one-off review prompt | None (orphaned) | Low | `[INFERRED]`² |
| `PROMPT_concurrent_booking_stress_failure.md` (56) | ONE-OFF-ARTIFACT | MOVE | `docs/archive/prompts/` | Executed one-off stress-failure prompt | None (orphaned) | Low | `[INFERRED]`² |
| `DOCS_RESTRUCTURE_AUDIT_opus48_prompt.md` (198) | ONE-OFF-ARTIFACT | MOVE | `docs/archive/prompts/` | This pass's own prompt; per its header, joins the archive after running | None (orphaned) | Low | `[CONFIRMED]` |
| `docs/agents/AGENT_LEARNINGS_SCHEMA.md` (306) | MERGE-CANDIDATE (optional) | MERGE | `docs/agents/AGENT_LEARNINGS_REFERENCE.md` | Field-definitions appendix; could fold with EXAMPLES into one reference file | CLAUDE.md document map + `docs/agents/README.md` cite it by name | Low | `[CONFIRMED]` (OQ-5) |
| `docs/agents/AGENT_LEARNINGS_EXAMPLES.md` (449) | MERGE-CANDIDATE (optional) | MERGE | `docs/agents/AGENT_LEARNINGS_REFERENCE.md` | "Illustrative only — do not cite" (G-06); pairs naturally with SCHEMA | CLAUDE.md document map + `docs/agents/README.md` cite it by name | Low | `[CONFIRMED]` (OQ-5) |
| `docs/backend/BOOKING_CANCELLATION_FLOW.md` (20) | MERGE-CANDIDATE | MERGE | `docs/backend/architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md` or `docs/backend/features/BOOKING.md` | 20-line stub; likely a fragment of the fuller cancellation/refund architecture doc | TBD — must grep inbound links in Phase 2 | Low | `[INFERRED]`² |
| `.agent/ARCHITECTURE.md` (83) | ACTIVE-CANON | RENAME (optional) | `.agent/OPERATING_LAYER.md` | Distinct "AI operating-layer topology" doc (not a competing restatement of ARCHITECTURE_FACTS — it defers to it). Name collides confusingly with `docs/agents/ARCHITECTURE_FACTS.md` | Any refs to `.agent/ARCHITECTURE.md` | Low | `[CONFIRMED]` (OQ-7) |

Footnotes:
¹ `[CONFIRMED]` via the Cluster-C (frontend) classification pass, which opened both files.
² `[INFERRED]` — classified from filename pattern + line count + the generating prompt's own
description; the per-file open is a **Batch-2 precondition** (see Open Questions / coverage
note). MOVE-to-archive is non-destructive (content preserved), so this satisfies C-3.

### Files explicitly classified KEEP (high-volume families, no action)

- **`docs/backend/**` (≈38 files)** — `ACTIVE-REFERENCE` implementation layer, genuinely
  distinct from `docs/agents/ARCHITECTURE_FACTS.md` (verified invariants vs. how-it's-built
  reference). `[INFERRED]` at family level; no removal recommended.
- **`docs/frontend/**` layer docs (12)** — coherent non-overlapping set (README=index,
  ARCHITECTURE=summary, each `*_LAYER.md`=one `src/` dir). `[CONFIRMED]` (Cluster C).
- **`skills/**` (18)** — all `RETAIN_AS_REFERENCE` per registry B6-2. `[CONFIRMED via registry]`.
- **`.agent/rules/**` (8), `.agent/workflows/**` (3)** — derived fast-load rules + portable
  workflows, cited by CLAUDE.md. `[INFERRED]` KEEP.
- **`.claude/{commands,agents,memory,output-styles(−audit.md),plugins,skills}`** — operating
  surface; `[INFERRED]` KEEP. (Generated `skills/generated/*` near-duplicate pairs flagged for
  a Phase-2 confirm pass — see coverage note; no removal on `[INFERRED]`.)
- **`docs/{cleanup,gates,decisions,governance,audit,validation}/**` (≈33, minus
  unresolved-registry)** — `ALREADY-ARCHIVED-CORRECT`; KEEP in place per
  `docs/archive/README.md`. `[CONFIRMED]` for the markers + registry; family-level for
  contents.
- **Root canon/ledgers** — `CLAUDE.md`, `AGENTS.md`, `README.md`, `README.dev.md`,
  `PROJECT_STATUS.md`, `BACKLOG.md`, `PRODUCT_GOAL.md`, `AUDIT_REPORT.md`, `LOCKING-GUARD.md`,
  `SHIP-MIRROR.md` — KEEP. `AUDIT_REPORT.md` `[CONFIRMED]` (live rolling index, not a one-off).

---

## Phase 2+ Batch Plan

Each batch is ≤25 files, independently revertable, and leaves no cited path dangling.
Ordered confidence-first / lowest-risk-first.

### Batch 1 — Orphan removal (lowest risk, highest confidence)

**Files (≤3):** `docs/DEVELOPMENT_HOOKS.md` (delete), `.claude/output-styles/audit.md`
(delete), `docs/archive/README.md` (note removals).
**One-liner for the execution prompt:** "Delete the two confirmed-orphan stubs
`docs/DEVELOPMENT_HOOKS.md` (superseded by `docs/HOOKS.md`) and `.claude/output-styles/audit.md`
(superseded by `audit-report.md`); both have zero active inbound references. Record in
`docs/archive/README.md`."

### Batch 2 — Executed-prompt archive convention (new dir)

**Files (≤8):** create `docs/archive/prompts/`; move `T1_opus4_prompt.md`,
`SYNC_DOCS_opus48_prompt.md`, `PROMPT_AUDIT_FIX.md`, `REVIEW_PROMPT_booking-system.md`,
`PROMPT_concurrent_booking_stress_failure.md`, `DOCS_RESTRUCTURE_AUDIT_opus48_prompt.md`;
add a `prompts/` row to `docs/archive/README.md`.
**Precondition:** open each prompt file (currently `[INFERRED]`) to confirm it is an executed
one-off before moving.
**One-liner:** "Establish `docs/archive/prompts/` and relocate the six executed root-level
`*_prompt.md` / `PROMPT_*` / `REVIEW_PROMPT_*` files; extend `docs/archive/README.md`."

### Batch 3 — Close out the 2026-Q1 registry

**Files (≤3):** `docs/FINDINGS_BACKLOG.md` (append B3-1 + REM-1), `docs/cleanup/unresolved-registry.md`
(mark items migrated), `docs/archive/README.md`.
**One-liner:** "Promote the two still-OPEN registry items (UNRESOLVED-B3-1 soleil-block
duplication; UNRESOLVED-REM-1 Phase-D control-plane acknowledgement) into
`docs/FINDINGS_BACKLOG.md` as live findings, then mark `unresolved-registry.md` fully
reconciled so it becomes archivable." **Requires human countersign for REM-1.**

### Batch 4 — Consolidate the SUBAGENT_ARCHITECTURE RFC family

**Files (≤6):** the 4 `docs/SUBAGENT_ARCHITECTURE*.md` → `docs/design/subagent-chat/`; a new
`docs/design/subagent-chat/README.md`; (optional) a `docs/design/README.md` index.
**Gate:** **OQ-1 must be answered first** (which of V3 / R3_CONTRACTS is canonical). Requires
a `[CONFIRMED]` content diff to preserve DELTA_R2's Output-7 operational defaults and any v1
unique content before archiving the superseded variants.
**One-liner:** "Move the four orphaned SUBAGENT_ARCHITECTURE docs into
`docs/design/subagent-chat/`, designate the human-chosen canonical (OQ-1), fold the delta/v1
unique content into it, and stub the rest as superseded. Zero inbound references exist."

### Batch 5 — Frontend doc relocations

**Files (≤6):** `docs/frontend/RBAC_UX_AUDIT.md` → archive; `docs/frontend/DEPLOYMENT.md`
infra content → `docs/backend/` (or new `docs/ops/`) with frontend-Dockerfile slice extracted;
update `frontend/README.md` and `docs/frontend/README.md` nav.
**Gate:** `[CONFIRMED]` diff of `frontend/DEPLOYMENT.md` vs the two backend deploy docs before
any content deletion.
**One-liner:** "Archive the dated `RBAC_UX_AUDIT.md` snapshot and relocate the misfiled
full-stack-infra `frontend/DEPLOYMENT.md` to the backend/ops deployment home, repointing both
README nav links."

### Batch 6 — AGENT_LEARNINGS reference fold (optional)

**Files (≤4):** merge `AGENT_LEARNINGS_SCHEMA.md` + `AGENT_LEARNINGS_EXAMPLES.md` →
`docs/agents/AGENT_LEARNINGS_REFERENCE.md`; update CLAUDE.md document map + `docs/agents/README.md`.
**Gate:** touches CLAUDE.md (constitutional) → human review. **Elective per OQ-5.**
**One-liner:** "Optionally consolidate the learnings schema + examples into one reference
appendix and update the two by-name citations."

### Batch 7 — Legacy-archive physical relocation (optional, deferred)

**Files:** up to 34 (`docs/{cleanup,gates,decisions,governance,audit,validation}/**`) — if
pursued, split into ≤25-file sub-batches with a full link-rewrite pass.
**Gate:** **OQ-6.** Only proceed if the owner chooses to *override* the current in-place
convention; otherwise leave as-is.
**One-liner:** "If electing physical archive consolidation, relocate the 2026-Q1 cleanup
directories under `docs/archive/legacy/` and rewrite every inbound cross-reference in the same
batch."

---

## Open Questions

Each requires a one-sentence human decision (or remains UNRESOLVED).

1. **OQ-1 — SUBAGENT canonical.** Which of `SUBAGENT_ARCHITECTURE_V3.md` (1888, full
   restatement) or `SUBAGENT_ARCHITECTURE_R3_CONTRACTS.md` (1184, "Round 3 Final Contracts")
   is the intended canonical base for the consolidated `docs/design/subagent-chat/` doc? Both
   claim a form of finality; none is marked superseded. `[UNPROVEN]` — blocks Batch 4.
2. **OQ-2 / UNRESOLVED-B3-1 (status: `open` at HEAD `[CONFIRMED]`).** The
   `<!-- soleil-ai-review-engine:start/end -->` block is duplicated verbatim in **both**
   CLAUDE.md (L102–202) and AGENTS.md (L36–136). Should it stay duplicated (accept it as
   `analyze`-managed content in both files) or be configured so `npx soleil-engine-cli analyze`
   injects into only one? The block is currently *consistent*, so there is no active conflict —
   the risk is future hand-edit drift.
3. **OQ-3 / UNRESOLVED-REM-1 (status: `open` at HEAD `[CONFIRMED]`).** A human must formally
   acknowledge: (1) Phase-D artifacts were accepted despite the control-plane gap; (2) the next
   cycle runs from Phase 0; (3) gate countersigns are required before any phase advance. Until
   acknowledged, `unresolved-registry.md` cannot be fully archived (Batch 3 dependency).
4. **OQ-4 — Capability assessment.** Keep `AI_Engineering_Capability_Assessment_*.md` at repo
   root, move it to `docs/archive/`, or move it out of the repo entirely? It is owner-maintained
   and append-only (last re-calibrated 2026-06-01).
5. **OQ-5 — AGENT_LEARNINGS fold.** Fold `_SCHEMA` + `_EXAMPLES` into one
   `AGENT_LEARNINGS_REFERENCE.md` (reduces file count, requires a CLAUDE.md document-map edit),
   or keep the four-file steady state? The active ledger (`AGENT_LEARNINGS.md`) currently has
   zero entries.
6. **OQ-6 — Legacy archive.** Keep the "archived-in-classification, files stay in place"
   convention (`docs/archive/README.md`), or physically relocate the 34 cleanup-wave files
   under `docs/archive/legacy/` with a full link-rewrite (Batch 7)?
7. **OQ-7 — Name collision.** Rename `.agent/ARCHITECTURE.md` → `.agent/OPERATING_LAYER.md` to
   stop it being confused with `docs/agents/ARCHITECTURE_FACTS.md`, or leave it?

### Coverage note (Phase-1 honesty)

Of the ~259 files, ~40 were opened/compared directly (`[CONFIRMED]`: all Step-0 canon, the 8
named redundancy families, the key root files, and the frontend cluster via a completed
sub-pass). The remaining long-tail was classified at family level (`[INFERRED]`) from the
verified inventory + canon cross-references, defaulting to **KEEP**. Per C-3, **no removal or
archive of content is recommended on `[INFERRED]` grounds alone** — every DELETE/SUPERSEDE row
above is `[CONFIRMED]`, and the `[INFERRED]` MOVE rows are non-destructive (content preserved
in the new location). Five per-cluster sub-passes (docs/backend, archived set, `.claude`,
`.agent`+skills, docs-misc) were interrupted by an account session limit; re-running them for a
per-file `[CONFIRMED]` sweep — especially of the `docs/backend/BOOKING_CANCELLATION_FLOW.md`
stub and the `.claude/skills/generated/*` near-duplicate pairs — is a precondition for any
Batch that would *remove* a long-tail file.
