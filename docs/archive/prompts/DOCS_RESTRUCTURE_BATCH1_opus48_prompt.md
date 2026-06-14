# Opus 4.8 Execution Prompt — Docs Restructure: Phase 2, Batch 1 (Orphan Removal)

> **For Tau — read before sending.**
> - **Target model**: `claude-opus-4-8`. Paste everything below the `---` divider as the
>   first user message in a fresh session with repo access.
> - **Phase**: Phase 2 of the plan in `docs/DOCS_RESTRUCTURE_PLAN.md` — **Batch 1 of 7**
>   ("Orphan removal," the lowest-risk / highest-confidence batch in that plan).
> - **Scope**: 4 files total (2 deletions, 2 edits), all inside `docs/` and `.claude/`.
>   No `backend/`, `frontend/`, `.github/`, or `docker-compose*` paths are touched, and
>   the change is far under CLAUDE.md's 25-file escalation threshold — so the scope
>   confirmation in C-1 is pre-granted. Opus should proceed without pausing **unless**
>   its own Step 1 re-verification turns up something this prompt didn't anticipate.
> - **Do not edit** `<constraints>`, `<task_specification>`, or `<acceptance_criteria>`
>   below — they encode decisions already verified across two prior passes (the Phase 1
>   audit itself, plus an independent follow-up spot-check).
> - **After this batch lands**: this file itself becomes a Batch-2 candidate (root
>   `*_opus48_prompt.md` → `docs/archive/prompts/`), same as
>   `DOCS_RESTRUCTURE_AUDIT_opus48_prompt.md`. No action needed now — just noting it so
>   nobody is surprised later.

---

You are executing **Batch 1** of the Soleil Hostel documentation restructure defined in
`docs/DOCS_RESTRUCTURE_PLAN.md` (Phase 1 deliverable, decision-memo format, already
accepted). Two independent passes — the Phase 1 audit itself, and a follow-up
spot-check — have confirmed both target files are safe to delete. Your job is to execute
the deletion cleanly, record it, and close out the documentation Definition of Done.

<mission>

Delete the two confirmed-orphan files named in Batch 1 of
`docs/DOCS_RESTRUCTURE_PLAN.md`, record the removals in `docs/archive/README.md`, update
`docs/COMPACT.md`'s Current Snapshot per the documentation Definition of Done, and report
results in `execution.md` format. Touch exactly 4 files. Do not begin Batch 2.

</mission>

<verified_facts>

These were established across the Phase 1 audit and an independent follow-up
spot-check — re-verify the load-bearing ones yourself in Step 1, but you do not need to
re-derive them from scratch.

1. **`docs/DEVELOPMENT_HOOKS.md`** — 3 lines total, a pure redirect stub:
   `# Development Hooks (Redirected)` plus a pointer to `docs/HOOKS.md`. `[CONFIRMED]`
2. **`docs/HOOKS.md:7`** contains `<!-- merged from DEVELOPMENT_HOOKS.md 2026-03-05 -->`
   — an HTML-comment provenance note, not a live link. Deleting
   `docs/DEVELOPMENT_HOOKS.md` does not break it; no edit to `docs/HOOKS.md` is required.
   `[CONFIRMED]`
3. **`docs/README.md`** no longer references `DEVELOPMENT_HOOKS.md` (already repointed to
   `HOOKS.md` per registry item B4-2). `[CONFIRMED]`
4. **`.claude/output-styles/audit.md`** — 618 bytes, the "Audit" output style
   (Findings / Impact / Recommendations / Residual Risk). A narrower duplicate of
   `audit-report.md`, which is the only one named in CLAUDE.md's output-style table.
   `[CONFIRMED]`
5. Three references to a bare `audit.md` exist repo-wide
   (`docs/cleanup/00-inventory.md`, `docs/cleanup/01-classification-matrix.md`,
   `docs/validation/10a-structural-results.md`) — **all three sit inside directories
   already marked `ALREADY-ARCHIVED-CORRECT`** by `docs/archive/README.md`'s
   "archived-in-classification, files stay in place" convention, so none need updating.
   `[CONFIRMED]`
6. `docs/archive/README.md`'s current structure has two sections: a table of
   archived-in-place **folders**, and a flat list of root **snapshot audits**. Neither
   shape fits "two individually deleted files with no archive copy" — Step 4 below adds a
   small new section for this category. `[INFERRED]` — use your judgment on heading
   wording, but keep it short and in the doc's existing terse tone.
7. `docs/COMPACT.md` §1 "Current Snapshot" must stay **under 12 lines** (Lifecycle
   Policy). **`docs/COMPACT.md` currently has pre-existing, unrelated uncommitted
   changes already in the working tree before this batch starts** — this is out-of-scope
   drift, already flagged separately. Edit §1 *additively* only; do not touch, "clean
   up," or revert anything else in this file. `[CONFIRMED]`
8. `.claude/memory/subagents/docs-sync.md` (read in Step 0) governs how docs edits in
   this repo are made: line-level edits only, no full-document rewrites, never invent
   facts.

</verified_facts>

<task_specification>

**Step 0 — Load canon.** Read, in order: `CLAUDE.md`, `.claude/memory/global-invariants.md`,
`.claude/memory/repo-truth.md`, `.claude/memory/subagents/docs-sync.md`,
`docs/agents/CONTRACT.md` (specifically the "DoD: Documentation Changes" checklist), and
`docs/DOCS_RESTRUCTURE_PLAN.md` (specifically the Batch 1 section and the two Migration
Table rows for `docs/DEVELOPMENT_HOOKS.md` and `.claude/output-styles/audit.md`).

**Step 1 — Re-verify, don't just trust this prompt.** Independently search the repo
(excluding `.claude/worktrees/**` and `backend/vendor/**`) for any live Markdown link or
import-style reference to `DEVELOPMENT_HOOKS.md` or `output-styles/audit.md` /
`output-styles/audit` that is **not** inside an already-archived directory
(`docs/cleanup/`, `docs/gates/`, `docs/decisions/`, `docs/governance/`, `docs/audit/`,
`docs/validation/`) and **not** a self-reference from `docs/DOCS_RESTRUCTURE_PLAN.md` or
this prompt itself. If you find one, **STOP** — do not delete that file — and report it
under Residual Risk instead.

**Step 2 — Delete `docs/DEVELOPMENT_HOOKS.md`.**

**Step 3 — Delete `.claude/output-styles/audit.md`.**

**Step 4 — Record both removals in `docs/archive/README.md`.** Add one new short
section (e.g. "## Deleted (superseded, no archive copy)") listing both files with: path,
one-line reason, and today's date. Do not modify the two existing sections beyond adding
this one.

**Step 5 — Update `docs/COMPACT.md` §1 Current Snapshot.** Per the Lifecycle Policy
(≤12 lines), add/update an entry noting today's date and a one-line summary: Batch 1 of
the docs restructure executed — two orphaned files removed (`DEVELOPMENT_HOOKS.md`,
`output-styles/audit.md`). Touch only §1. Leave the rest of the file exactly as found
(see `<verified_facts>` item 7 — its pre-existing diff is not yours to fix).

**Step 6 — Run the DoD: Documentation Changes checklist** from `docs/agents/CONTRACT.md`
against this batch and record the result of each item.

**Step 7 — Stop.** Exactly 4 files should be changed (2 deleted, 2 edited). Do not start
Batch 2 (`docs/archive/prompts/` convention) even if it looks like a natural next step —
that is a separate prompt.

</task_specification>

<constraints>

- **C-1 (scope pre-granted)**: 4 files, all under `docs/` / `.claude/`, none in
  `backend/`, `frontend/`, `.github/`, `docker-compose*` — below every CLAUDE.md
  escalation threshold. Proceed without pausing for confirmation **unless Step 1's
  re-verification finds something new**, in which case stop and report.
- **C-2**: Do not touch any file other than the 4 named (`docs/DEVELOPMENT_HOOKS.md`,
  `.claude/output-styles/audit.md`, `docs/archive/README.md`, `docs/COMPACT.md`). In
  particular, do not "fix" the pre-existing unrelated diff already present in
  `docs/COMPACT.md`.
- **C-3**: Every claim in the chat report must carry `[CONFIRMED]`, `[INFERRED]`,
  `[UNPROVEN]`, or `[ACTION]`.
- **C-4**: Follow `.claude/memory/subagents/docs-sync.md` — line-level edits only, no
  full-document rewrites, no invented facts.
- **C-5**: Markdown-lint clean; any tables you add must be valid.
- **C-6**: `.claude/worktrees/**` is not a source and must not be cited or edited.
- **C-7**: No history-altering git commands. Do not `git add`, `git commit`, or stage
  anything — leave all changes in the working tree for human review before merge to
  `main`, per CLAUDE.md.
- **C-8**: This is Batch 1 of 7 only. Do not begin Batch 2+ work.
- **C-9**: Chat response uses `.claude/output-styles/execution.md` structure. For the
  "Validation" table, use the **DoD: Documentation Changes** checklist items as gates
  (not the code-gate list in `execution.md`'s generic description — no application code
  is touched in this batch).

</constraints>

<acceptance_criteria>

- [ ] Step 0 canon + Batch 1 spec + `docs-sync.md` memory read
- [ ] Step 1 re-verification performed; either confirms no new references, or triggers
      STOP with the finding reported
- [ ] `docs/DEVELOPMENT_HOOKS.md` deleted
- [ ] `.claude/output-styles/audit.md` deleted
- [ ] `docs/archive/README.md` has a new section recording both deletions (path, reason,
      date), existing sections unchanged
- [ ] `docs/COMPACT.md` §1 updated with today's date + 1-line summary, stays ≤12 lines,
      rest of file untouched
- [ ] Exactly 4 files changed, confirmed via `git status --porcelain` on just these paths
- [ ] DoD: Documentation Changes checklist run and reported per-item
- [ ] No git add/commit/stage performed
- [ ] Chat response follows `execution.md` structure with evidence tags throughout

</acceptance_criteria>

<thinking>

Before writing anything, think through:
1. Step 1 is the highest-value step — if it finds a live reference this prompt missed,
   everything downstream changes. Don't skip it because the prompt sounds confident.
2. `docs/COMPACT.md` is a trap: it already has unrelated pending changes in the working
   tree. Touch only §1, additively. If you're unsure whether a line is "yours" or
   pre-existing, leave it alone.
3. `docs/archive/README.md`'s new section should match the doc's existing terse,
   table-or-list tone — don't introduce a heavyweight new format for two lines of
   content.
4. The Validation table in your `execution.md` response should map to the
   Documentation-Changes DoD, not the code-gate template — there's no backend/frontend
   code in this batch.
5. Stop at Step 7. Batch 2 is a different prompt with its own precondition (open each
   prompt file to confirm it's an executed one-off before moving).

</thinking>

<output_instructions>

Respond in `.claude/output-styles/execution.md` format:

- **Summary**: 1-2 sentences — what was deleted/edited and why (Batch 1 of the docs
  restructure plan).
- **Files Changed**: table `| File | Change |` — exactly 4 rows.
- **Validation**: table `| Gate | Result |` — one row per DoD: Documentation Changes
  checklist item, with evidence tags.
- **Residual Risk**: bullet list. Should be empty/"None identified" unless Step 1 found
  something — in which case explain what, and that Batch 1 was paused as a result.

</output_instructions>
