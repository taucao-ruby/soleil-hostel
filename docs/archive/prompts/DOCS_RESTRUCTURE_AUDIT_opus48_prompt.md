# Opus 4.8 Execution Prompt — Documentation Estate Audit & Restructure Plan (Phase 1: Audit + Proposal, Zero Edits)

> **Target model**: `claude-opus-4-8`
> **Send as**: single user message, from the `soleil-hostel` repo root, with `.claude/` and the soleil-ai-review-engine MCP available.
> **Do NOT edit** the `<constraints>`, `<task_specification>`, or `<acceptance_criteria>` sections before sending.
> **Scope confirmation**: pre-granted by this prompt (see C-1) — Phase 1 makes **zero edits to existing files** (it creates exactly one new document), so CONTRACT.md's "max 25 files changed" cap and CLAUDE.md's ">25 files" escalation gate do not trigger, and `.claude/commands/sync-docs.md`'s scope-confirmation requirement is satisfied by this prompt itself.
> **This is Phase 1 of N.** The deliverable is an audit + a batched migration plan. Phase 2+ (the actual moves/merges/archives/deletes) are separate, smaller, human-confirmed prompts generated *from* this prompt's output — do not attempt them in this pass.

---

## PROMPT (copy everything below this line)

---

You are convening as a three-discipline review board for the Soleil Hostel repository's documentation and AI-agent-instruction estate:

1. **A distinguished engineer** doing an information-architecture review of `docs/`, `skills/`, and the root-level `*.md` sprawl — looking for duplication, superseded versions, and missing single sources of truth.
2. **A harness/agent-instruction engineer** reviewing how `CLAUDE.md`, `AGENTS.md`, `.claude/`, `.agent/`, and `skills/` compose into the agent operating system — looking for conflicting or duplicated instructions, broken decision-order chains, and stale cross-references.
3. **A security engineer** verifying that no booking-integrity, auth/token, or RBAC invariant gets lost, silently forked, or demoted in authority during any proposed restructuring.

The repository's documentation estate has grown to roughly **254 markdown files** across `docs/` (155 files, ~45,065 lines), repo-root `*.md` (16 files, ~4,057 lines), `.claude/` (~54 files), `.agent/` (12 files), and `skills/` (17 files) — on the order of 55,000+ lines of markdown. A prior "2026-Q1 doc cleanup" wave (batches B1–B9B, "RC1 remediation", "FFP-S3 closure") already ran once and left its own process artifacts behind as additional bloat. The repo owner (a principal engineer, 15+ years, ex-Booking.com) judges the estate "bloated and chaotic" (*phình to và lộn xộn*) and wants a professional restructure. Your job in this pass is **audit + propose**, not execute.

---

<mission>

Produce two deliverables:

1. A structured audit (in your chat response) classifying every file in the documentation/agent-instruction estate.
2. One new file, `docs/DOCS_RESTRUCTURE_PLAN.md`, containing a target structure proposal and a file-by-file migration table, batched into ≤25-file execution units for Phase 2+.

Make **zero edits to any existing file** in this pass.

</mission>

---

<repo_inventory_facts>

Treat everything below as a **Phase-0 hypothesis** captured on 2026-06-15 — re-verify via your own `Glob`/`Grep`/`Read` before relying on it (step 1).

**Estate size** (file count / line count):
- `docs/` — 155 files, 45,065 lines, 2.5 MB
- repo root `*.md` — 16 files, 4,057 lines
- `.claude/**/*.md` — ~54 files (agents/, commands/, memory/, output-styles/, plugins/, skills/generated/ ×16, skills/soleil-ai-review-engine/ ×6, SKILL_MAP.md)
- `.agent/**/*.md` — 12 files (ARCHITECTURE.md, rules/ ×8, workflows/ ×3)
- `skills/**/*.md` — 17 files (laravel/, react/, ops/, README.md)

**Known redundancy families** (verify each — do not trust the label, open the files):

1. **`SUBAGENT_ARCHITECTURE` family** — `docs/SUBAGENT_ARCHITECTURE.md` (1,694 lines), `docs/SUBAGENT_ARCHITECTURE_V3.md` (1,888), `docs/SUBAGENT_ARCHITECTURE_R3_CONTRACTS.md` (1,184), `docs/SUBAGENT_ARCHITECTURE_DELTA_R2.md` (955). 5,721 lines across 4 files. Naming implies a version lineage (base → ...R2 delta... → V3 / R3 contracts) but no file is marked superseded. Determine the current-canonical file and what (if anything) in the other three is not yet folded in.

2. **`AGENT_LEARNINGS` family** (`docs/agents/`) — `AGENT_LEARNINGS.md`, `AGENT_LEARNINGS_EXAMPLES.md` (449 lines, "illustrative entries only — do not cite as historical facts (G-06)" per `docs/agents/README.md`), `AGENT_LEARNINGS_OPERATING_RULES.md` (461), `AGENT_LEARNINGS_SCHEMA.md`. Reads of `AGENT_LEARNINGS.md` are tag-scoped per R-01–R-04 in `AGENT_LEARNINGS_OPERATING_RULES.md`. Determine whether 4 separate files is the intended steady state or whether `_SCHEMA`/`_EXAMPLES` could fold into one reference appendix.

3. **Prior cleanup-wave artifacts (2026-Q1, batches "FFP-S3"/"RC1")** — `docs/cleanup/` (11 files incl. `00-inventory.md` through `08-agent-responsibility-matrix.md`, `unresolved-registry.md`, `ARCHIVED.md`), `docs/gates/` (5: gate-a/b/c/rc1-result + ARCHIVED.md), `docs/decisions/` (2: wave-0-decision-lock + ARCHIVED.md), `docs/governance/` (4), `docs/audit/` (4), `docs/validation/` (8 incl. `fixtures/`) = **34 files**. Each directory carries an `ARCHIVED.md` marker and is indexed in `docs/archive/README.md`, but the files **physically remain at their original paths** "so internal cross-references resolve." Critically, `docs/cleanup/unresolved-registry.md` still lists **2 items as `status: open`**:
   - `UNRESOLVED-B3-1` — risk that `npx soleil-engine-cli analyze` re-injects the soleil-ai-review-engine block into `AGENTS.md` (and/or `CLAUDE.md`), causing duplication if both files carry un-marked copies.
   - `UNRESOLVED-REM-1` — requires a human to formally acknowledge that Phase D artifacts were accepted despite a control-plane gap, and that gate countersigns are required before any future phase advance.
   Re-check both against current `HEAD` — they may now be resolved by later commits, or may still be live blockers.

4. **Audit snapshots** — `docs/AUDIT_2026_02_21.md`, `docs/AUDIT_2026_03_12_STRUCTURE.md` (both flagged in `docs/archive/README.md` as "dated, remain at root for direct citation; do not overwrite"), root `AUDIT_REPORT.md` (101 lines), root `AI_Engineering_Capability_Assessment_Soleil_Hostel.md` (663 lines, 72 KB). Determine what, if anything, in the largest file (`AI_Engineering_Capability_Assessment...`) is still load-bearing vs. a one-time assessment.

5. **Root one-off execution prompts** — `T1_opus4_prompt.md` (246 lines), `SYNC_DOCS_opus48_prompt.md` (178, most recently modified), `PROMPT_AUDIT_FIX.md` (279), `REVIEW_PROMPT_booking-system.md` (78), `PROMPT_concurrent_booking_stress_failure.md` (56). No archive convention currently covers *executed prompts* — they accumulate at repo root indefinitely. This very prompt will join that pile after it runs; your plan should say where these belong.

6. **Competing "facts/architecture" sources** — `docs/agents/ARCHITECTURE_FACTS.md` (419 lines, canonical per CLAUDE.md decision-order #2), `docs/DB_FACTS.md`, `docs/DOMAIN_LAYERS.md`, `.agent/ARCHITECTURE.md` (a *different* file from `ARCHITECTURE_FACTS.md`), and `docs/backend/architecture/` (11 files: API, BOOKING_CANCELLATION_REFUND_ARCHITECTURE, EVENTS, FOLDER_REFERENCE, JOBS, MIDDLEWARE, POLICIES, README, REPOSITORIES, SERVICES, TRAITS_EXCEPTIONS). Map which of these are genuinely distinct layers (e.g., "verified invariants" vs. "implementation reference") vs. overlapping restatements.

7. **`.claude/output-styles/` pair** — both `audit.md` and `audit-report.md` exist, but `CLAUDE.md`'s output-style table only names `audit-report.md` (for "Repo / domain / code / contract / pre-release audit"). Determine whether `audit.md` serves a distinct, still-referenced purpose or is a stale duplicate.

8. **Duplicated soleil-ai-review-engine instruction block** — a ~90-line "Code Intelligence" block (the `<!-- soleil-ai-review-engine:start/end -->` markers) appears to be carried verbatim in both `CLAUDE.md` and `AGENTS.md`. This is the exact duplication risk `UNRESOLVED-B3-1` (item 3 above) flagged — confirm whether it's still duplicated and whether that item was ever actually decided.

**Environment note**: `git status` currently shows ~28 modified-but-uncommitted files under `.agent/` and `.claude/`. Do not act on this diff or try to explain it — read working-tree file content as it stands, note the fact if it's relevant to a finding, and move on.

</repo_inventory_facts>

---

<task_specification>

**Step 0 — Load canon.** Per CLAUDE.md's agent memory policy, read before classifying anything: `CLAUDE.md`, `AGENTS.md`, `docs/agents/ARCHITECTURE_FACTS.md`, `docs/agents/CONTRACT.md`, `.claude/memory/global-invariants.md`, `.claude/memory/repo-truth.md`, and `docs/agents/AGENT_LEARNINGS_OPERATING_RULES.md` (for the R-01–R-04 tag-scoping rules that gate any read of `AGENT_LEARNINGS.md`). Also read `.claude/memory/subagents/docs-sync.md` — it's the closest-matching existing role to this task and its "Known Drift Areas" notes (FU-1, FU-5, `rooms.status` deprecation, etc.) may overlap with files you're about to classify.

**Step 1 — Re-verify the inventory.** Run your own `Glob` over `docs/**/*.md`, `.claude/**/*.md`, `.agent/**/*.md`, `skills/**/*.md`, and `./*.md`. Confirm or correct every count and line number in `<repo_inventory_facts>`; call out deltas explicitly. Treat that section as a starting hypothesis, not ground truth.

**Step 2 — Partition into clusters and classify in parallel.** 254 files is too large for sequential single-pass reading. If subagent/Task tooling is available, dispatch one classification pass per cluster (each returns rows in the Step 3 schema, which you then merge):

| Cluster | Scope |
|---|---|
| A | `docs/` root canon (`ADR*`, `*_FACTS`, `DOMAIN_LAYERS`, `SUBAGENT_ARCHITECTURE*`, `CORE_FEATURES_PROMPT`, `COMPACT`, `WORKLOG`, `FINDINGS_BACKLOG`, etc.) + `docs/agents/**` |
| B | `docs/backend/**` |
| C | `docs/frontend/**` |
| D | "Archived-in-classification" set: `docs/cleanup/**`, `docs/gates/**`, `docs/decisions/**`, `docs/governance/**`, `docs/audit/**`, `docs/validation/**`, `docs/archive/**`, `docs/AUDIT_2026_*.md` |
| E | `.claude/**` (agents, commands, hooks, memory, output-styles, plugins, `skills/generated/**`, `skills/soleil-ai-review-engine/**`) |
| F | `.agent/**` + `skills/{laravel,react,ops}/**` |
| G | repo-root `./*.md` (16 files) |
| H | `docs/` misc not in A–D (`ai/`, `api/`, `design/`, `mcp/`, `tooling/`, `imports/`, `MIGRATION_GUIDE`, `OPERATIONAL_PLAYBOOK`, `THREAT_MODEL_AI`, etc.) |

**Step 3 — Classify every file** into exactly one bucket, with evidence:

- `ACTIVE-CANON` — the authoritative, current source for its topic
- `ACTIVE-REFERENCE` — useful and current, but not authoritative (guides, skills, playbooks)
- `SUPERSEDED` — fully covered by a newer/canonical doc; name the successor
- `MERGE-CANDIDATE` — partial overlap with another doc; name the target and what content is unique
- `STALE-ARCHIVE-CANDIDATE` — historically accurate, no longer live, belongs under an archive convention
- `ALREADY-ARCHIVED-CORRECT` — already under an `ARCHIVED.md` marker and correctly classified; leave as-is
- `ONE-OFF-ARTIFACT` — an executed task prompt or one-time audit output with no ongoing role
- `UNCLEAR` — cannot classify without a human decision; state the exact question

**Step 4 — Resolve the 8 named redundancy families** from `<repo_inventory_facts>` specifically. For each, state which file(s) are canonical, what unique content (if any) in the others must survive a merge before they're archived/deleted, and — for family 3 — re-check `UNRESOLVED-B3-1` and `UNRESOLVED-REM-1` against current `HEAD`.

**Step 5 — Propose a target structure.** A revised tree for `docs/`, `.claude/`, `.agent/`, `skills/`, and root `*.md` such that: (a) every topic has exactly one canonical home, consistent with CLAUDE.md's decision order and document map; (b) it **extends** the existing `docs/archive/README.md` convention rather than inventing a parallel one; (c) it defines where *executed one-off prompts* go (e.g., a new `docs/archive/prompts/` — your call, justify it); (d) no path that `CLAUDE.md`, `AGENTS.md`, or the document map cites by name goes missing unless the migration table (Step 6) also updates that citation.

**Step 6 — Produce the migration table.** One row per file that is not staying exactly as-is:

`| Current Path | Classification | Action (KEEP / MERGE-INTO / ARCHIVE / DELETE / MOVE / RENAME) | Target Path | Rationale | Updates Required Elsewhere | Risk | Confidence |`

"Updates Required Elsewhere" must enumerate every other file (e.g., `CLAUDE.md`, `AGENTS.md`, `docs/README.md`, `docs/archive/README.md`, the document map) whose cross-references would break under this action.

**Step 7 — Batch into Phase 2+ execution units** of **≤25 files each**, ordered lowest-risk/highest-clarity first (e.g., "update an index entry for an already-archived file" before "merge 4 architecture-doc variants into 1"). Each batch must be independently revertable and must never leave a cited path dangling.

**Step 8 — Stop.** Do not move, edit, merge, delete, or rewrite any existing file. The only file you create is `docs/DOCS_RESTRUCTURE_PLAN.md`. Your chat response is the audit report.

</task_specification>

---

<constraints>

- **C-1 — Scope pre-granted.** See header. This prompt is the human-in-the-loop confirmation `.claude/commands/sync-docs.md` would otherwise require.
- **C-2 — Zero edits, one new file.** No existing file is moved, edited, merged, or deleted. Exactly one new file is created: `docs/DOCS_RESTRUCTURE_PLAN.md`. No file under `backend/`, `frontend/`, `.github/`, or `docker-compose*` is touched (trivially true for a docs-only, read-mostly pass).
- **C-3 — Evidence tags mandatory.** Every classification and migration-table row carries exactly one of `[CONFIRMED]` / `[INFERRED]` / `[UNPROVEN]` / `[ACTION]`. A `SUPERSEDED` or `MERGE-CANDIDATE` verdict that recommends archiving/deleting content must be `[CONFIRMED]` — i.e., you opened and compared both files. `[INFERRED]` alone is not sufficient grounds to recommend removing content.
- **C-4 — Inspect, don't invent.** Do not classify a file from its name or path alone — open it. Exception: files over ~800 lines may be sampled (front matter, table of contents, first/last ~100 lines, targeted grep for key terms) if you state the sampling method used.
- **C-5 — `AGENT_LEARNINGS` tag-scoping.** Reads of `docs/agents/AGENT_LEARNINGS.md` stay scoped per R-01–R-04 in `AGENT_LEARNINGS_OPERATING_RULES.md`. Never cite `AGENT_LEARNINGS_EXAMPLES.md` as a real historical fact (G-06) — it may be classified, but its *content* isn't evidence for anything else.
- **C-6 — `.claude/worktrees/**` is not a source.** Any hit there is a duplicate of the main tree (CLAUDE.md retrieval hygiene) — cite the main-tree path instead.
- **C-7 — Don't action the uncommitted diff.** Read working-tree content as-is for the ~28 modified files under `.agent/`/`.claude/`; do not diagnose, stage, or comment on the diff itself beyond noting it if relevant to a finding.
- **C-8 — Unresolved stays unresolved.** Any conflict between two docs at the same CLAUDE.md decision-order rank, or any file a classification depends on but which doesn't exist at its documented path, is `UNRESOLVED` — list it under Open Questions, do not guess or invent a resolution.
- **C-9 — No history-altering commands.** No `git commit`, `git push`, `git reset`, etc.
- **C-10 — Output format.** `docs/DOCS_RESTRUCTURE_PLAN.md` is markdown-lint clean and follows `.claude/output-styles/decision-memo.md` for its narrative sections (read that file first); append the migration table and Phase 2+ batch plan as final sections if `decision-memo.md`'s structure doesn't natively accommodate a long table.

</constraints>

---

<acceptance_criteria>

- [ ] Step 0 canon files read before any classification
- [ ] Inventory re-verified via `Glob`; deltas from `<repo_inventory_facts>` called out explicitly
- [ ] Every file in the ~254-file estate appears in exactly one Step 3 bucket
- [ ] All 8 named redundancy families resolved with `[CONFIRMED]` evidence (or moved to Open Questions if genuinely blocked)
- [ ] `UNRESOLVED-B3-1` and `UNRESOLVED-REM-1` re-checked against current `HEAD`
- [ ] Target structure proposed; consistent with CLAUDE.md decision order + document map; extends (not replaces) `docs/archive/README.md` convention
- [ ] Migration table covers every non-`KEEP` file, including a populated "Updates Required Elsewhere" column
- [ ] Migration table batched into ≤25-file Phase 2+ units, risk-ordered, each independently revertable, no dangling cited paths
- [ ] Zero existing files edited; exactly one new file (`docs/DOCS_RESTRUCTURE_PLAN.md`) created
- [ ] Every claim tagged `[CONFIRMED]` / `[INFERRED]` / `[UNPROVEN]` / `[ACTION]`
- [ ] Chat response follows `.claude/output-styles/audit-report.md` structure
- [ ] `docs/DOCS_RESTRUCTURE_PLAN.md` follows `.claude/output-styles/decision-memo.md` (+ migration table/batch plan), markdown-lint clean

</acceptance_criteria>

---

<thinking>

Before producing output, work through:

1. **Canon first, then inventory.** Reading `ARCHITECTURE_FACTS.md`, `CONTRACT.md`, and the document map in `CLAUDE.md` gives you the "should be" picture (decision order, which files are supposed to be canonical) before you look at the "is" picture (254 actual files). Discrepancies between the two are your highest-value findings.

2. **The 8 named families are the 80/20.** They likely account for 60+ of the 254 files. Resolving them with `[CONFIRMED]` evidence and a clean migration plan is worth more than achieving 100% coverage on long-tail single-purpose docs (`docs/backend/features/*.md`, `skills/*.md`) — those are mostly `ACTIVE-REFERENCE`/`KEEP` by default unless something jumps out.

3. **`docs/archive/README.md` already encodes a convention and a rationale** ("files remain at original paths so cross-references resolve"). Your target structure should explain why it either keeps, extends, or overrides that rationale — don't silently propose physically moving files that convention deliberately left in place without addressing the cross-reference concern it was protecting against.

4. **The SUBAGENT_ARCHITECTURE and AGENT_LEARNINGS families are the highest-risk merges** (largest line counts, most likely to contain agent-instruction content that's actually load-bearing). Budget more reading time here than on the cleanup-wave artifacts (family 3), which are largely self-describing via their own `ARCHIVED.md`/`unresolved-registry.md`.

5. **Batch ordering should front-load confidence, not just risk.** A batch that's "rename 3 already-archived files' index entries" is both low-risk and high-confidence — put it first so Phase 2 has an easy, clean win. A batch that merges 4 architecture docs into 1 is high-value but needs the most human review — it can be later, but should still be fully specified now.

6. **Final pass:** does every row in the migration table have a non-empty "Updates Required Elsewhere" (even if it's "none")? Does every `UNCLEAR`/`UNRESOLVED` item have a *specific* question a human can answer in one sentence, rather than "needs review"?

</thinking>

---

<output_instructions>

Produce exactly two deliverables.

**1. Chat response** — follow `.claude/output-styles/audit-report.md` exactly. In particular:
- **Scope**: list the clusters (A–H) and confirm all ~254 files were classified.
- **Sources of Truth**: the Step 0 canon files, plus `docs/archive/README.md` as the existing archive convention.
- **Confirmed Findings**: the Step 4 resolution of the 8 redundancy families (table format per the style guide), each tagged.
- **Unproven / Needs Runtime Validation**: anything that needed more context than available (e.g., "is this skill still referenced by a command" where the answer depends on a grep you couldn't complete).
- **Drift & Contract Mismatch**: the duplicated soleil-ai-review-engine block, the `ARCHITECTURE_FACTS.md` vs `.agent/ARCHITECTURE.md` split, and any other CLAUDE.md-document-map-vs-reality gaps found in Step 1.
- **Priority Stack**: map directly to the Phase 2+ batches from Step 7.
- **Go / No-Go**: whether the estate is safe to begin Phase 2 batch 1, and any precondition.
- **Residual Risk**: the `UNCLEAR`/`UNRESOLVED` items from Steps 3–4.

**2. `docs/DOCS_RESTRUCTURE_PLAN.md`** — `.claude/output-styles/decision-memo.md` structure, containing: Context (current state, cite the inventory), Target Structure (tree diagram + rationale), full Migration Table (Step 6), Phase 2+ Batch Plan (Step 7, one subsection per batch with its own file list and a one-line description suitable for pasting into a future batch-execution prompt), and Open Questions (every `UNCLEAR` item + `UNRESOLVED-B3-1`/`UNRESOLVED-REM-1`).

Do not restate this prompt back. Do not begin Phase 2 work.

</output_instructions>
