# Opus 4.8 Execution Prompt — Documentation Sync: `dev` @ `f5ffa02..edadbf5`

> **Target model**: `claude-opus-4-8`
> **Send as**: single user message, executed from the `soleil-hostel` repo root, on the `dev` branch, with `.claude/` and the soleil-ai-review-engine MCP available.
> **Do NOT edit** the `<constraints>`, `<task_specification>`, or `<acceptance_criteria>` sections before sending.
> **Scope confirmation**: pre-granted by this prompt (see C-1) — satisfies the "Scope confirmation required" gate in `.claude/commands/sync-docs.md`.

---

## PROMPT (copy everything below this line)

---

You are an expert documentation-reconciliation engineer operating the Soleil Hostel repository's own `/sync-docs` machinery: `.claude/commands/sync-docs.md` (procedure + document list), `.claude/agents/docs-sync.md` (role bindings + memory), and `.claude/output-styles/docs-sync.md` (report format). Your task is to bring the repository's status, ledger, and governance documents back into alignment with the code and commit history actually present at `HEAD`. This is a reconciliation task: read both sides of every claim before touching a file, edit only what the evidence supports, and tag every finding.

---

<mission>
Reconcile the documentation surfaces named in `<task_specification>` against:

1. The **15 commits `f5ffa02..edadbf5`** on `dev` — everything merged since `docs/WORKLOG.md`'s last entry.
2. The pre-existing staleness in `PROJECT_STATUS.md` (header reads "Last Updated: June 1, 2026" / "Latest Commit: `b7d9d28`") and `docs/COMPACT.md` (`last_verified_at: 2026-05-08`, §1 over its documented line limit).

Produce both the documentation edits and the structured report required by `.claude/output-styles/docs-sync.md`. This prompt supplies verified "code truth changed" inputs (commit list, FINDINGS_BACKLOG state, doc staleness) so you do not have to rediscover the commit history from scratch — but every edit must still be backed by your own read of the current source and current doc text. Do not carry a fact from this prompt into your report without re-verifying it.
</mission>

---

<codebase_facts>

**Repo state**
- Branch: `dev`. `HEAD` = `edadbf5` ("docs(infra): refresh soleil-ai-review-engine index counts (7345 nodes, 21657 edges)").
- `docs/WORKLOG.md`'s most recent entry is dated 2026-06-12 and documents the merge of `f5ffa02` (P1-5/P1-6/P1-7: `reconciliation_refund_drift` view, `reconciliation:check-drift` command, F-85/F-86 fixes, `decide()` DB-authoritative ordering fix). It does **not** cover anything below.

**Commits `f5ffa02..edadbf5`, oldest first** (verify each against source — do not trust the subject lines alone):

| Commit | Subject |
|---|---|
| `ee2f6a8` | fix(backend): correct nights accessor sign for Carbon 3 |
| `dd348ac` | docs(docs): log F-92 trashed-route gap and F-93 nights-sign fix |
| `e723443` | chore(infra): gitignore RBAC preview verification artifacts |
| `1541070` | Merge feature/booking-nights-sign-fix into dev |
| `0307e95` | fix(backend): default Cashier currency to VND, not USD |
| `f703452` | fix(backend): render booking money as VND, not USD |
| `6e532b6` | Merge feature/payment-currency-vnd into dev |
| `4d0c532` | Merge origin/dev (e2e flake ledger CI chore) into dev |
| `1999729` | docs(infra): refresh soleil-ai-review-engine index counts (7329 nodes, 21609 edges) |
| `7f7fd3b` | feat(frontend): add admin trashed bookings page and route |
| `862fcd9` | Merge feature/admin-trashed-bookings-route into dev |
| `37e4120` | docs(docs): reconcile trashed-view RBAC in PERMISSION_MATRIX Table F |
| `952b38a` | docs(docs): align Table B view-trashed with backend gate |
| `cfa0673` | fix(frontend): show moderators read-only trashed tab in AdminDashboard |
| `edadbf5` | docs(infra): refresh soleil-ai-review-engine index counts (7345 nodes, 21657 edges) |

**Known `docs/FINDINGS_BACKLOG.md` state (verify before editing):**
- **F-92** (logged 2026-06-14 by `dd348ac`, status currently **"Open"**): *"No `bookings/trashed` route exists; `/admin/bookings/trashed` falls through to `bookings/:id`."* Its suggested fix — add a `bookings/trashed` route in `router.tsx` wired to a new admin-only Trashed Bookings page consuming `GET /api/v1/admin/bookings/trashed`, guarded to `admin` per the matrix — appears to be exactly what `7f7fd3b`/`862fcd9` delivered, with RBAC table reconciliation in `37e4120`/`952b38a` and a moderator-tab correction in `cfa0673`. **This status may now be stale.** Read `frontend/src/app/router.tsx`, the new trashed-bookings page component, `docs/PERMISSION_MATRIX.md` Tables B/F, and (if the pattern is documented there) `docs/frontend/RBAC.md`, to determine whether F-92 should move to "Fixed" (with commit refs), "Partially Fixed" (state what remains), or stay "Open" (state why the fix doesn't close it).
- **F-93** (same commit, status **"Fixed (2026-06-14)"**): nights-accessor sign fix. `ee2f6a8` appears to match. Spot-check only — confirm `Booking.php` and `BookingApiContractTest` match the description; do not re-litigate if confirmed.
- Next available finding ID: **F-94**.

**Standing staleness (independent of the commit range above):**
- `PROJECT_STATUS.md` header reads "Last Updated: June 1, 2026" / "Latest Commit: `b7d9d28`" — `b7d9d28` predates `f5ffa02`, which itself predates this entire 15-commit range. The test-count and gate-status fields in this file are derived from that stale commit.
- `docs/COMPACT.md` front matter has `last_verified_at: 2026-05-08`. Its "Current Snapshot" (§1) currently holds 15+ dated paragraph entries spanning 2026-05-19 through 2026-06-04 — `docs/COMPACT.md`'s own lifecycle policy caps §1 at under 12 lines. Read that lifecycle policy section first; it should describe where older entries get archived to. None of the 15 commits above are reflected in §1 at all.

**Environment noise — do not action:**
`git status`/`git diff` on this checkout shows ~519 files modified across `.claude/`, `.agent/`, `tools/`, and `tests/performance/`, with exactly equal insertion/deletion counts (104,431 / 104,431). This is consistent with a line-ending (CRLF/LF) normalization sweep, not a content change, and is **unrelated to this task**. Do not inspect, fix, stage, or comment on this diff, and do not let it count toward the 25-file budget in C-4 — that budget applies only to files *you* edit for this task.

</codebase_facts>

---

<task_specification>

Follow the procedure in `.claude/commands/sync-docs.md`. Concretely:

**1. Load memory.** Read `.claude/memory/global-invariants.md`, `.claude/memory/repo-truth.md`, and `.claude/memory/subagents/docs-sync.md` before touching any doc (agent memory policy, CLAUDE.md).

**2. Build the "code truth" side.** For the commit range `f5ffa02..edadbf5`, read the actual diffs (`git show <commit>` or `git diff f5ffa02..edadbf5 -- <path>`) for at minimum:
   - `backend/app/Models/Booking.php` (nights accessor, F-93) and `BookingApiContractTest`
   - The Cashier/currency config and booking money-rendering path touched by `0307e95`/`f703452`
   - `frontend/src/app/router.tsx` and the new admin trashed-bookings page/route (F-92)
   - `docs/PERMISSION_MATRIX.md` Tables B and F (already touched by `37e4120`/`952b38a` — confirm completeness, don't duplicate)
   - The `AdminDashboard` trashed-tab visibility change for moderators (`cfa0673`)
   - Any `.soleil-ai-review-engine/meta.json` or index-count references touched by `1999729`/`edadbf5`

**3. Build the "docs" side.** For each canonical document below, read its current content:
   - `CLAUDE.md`, `AGENTS.md`
   - `docs/agents/ARCHITECTURE_FACTS.md`, `docs/agents/CONTRACT.md`, `docs/agents/COMMANDS.md`
   - `docs/COMPACT.md`
   - `docs/PERMISSION_MATRIX.md`, `docs/DB_FACTS.md`
   - `PROJECT_STATUS.md`, `docs/WORKLOG.md`, `docs/FINDINGS_BACKLOG.md`
   - The 5 canonical `.agent/rules/*.md` files named in `.claude/commands/sync-docs.md`'s "Canonical rules" section (check each file's `verified-against` frontmatter against the commits above)

**4. Diff the two sides** and classify each gap: doc is stale and should be edited / doc is already correct / claim cannot be verified (→ `UNRESOLVED` or `[UNPROVEN]`, do not guess).

**5. Resolve the specific items below** (in addition to whatever else step 4 surfaces):
   - **F-92 status** (see `<codebase_facts>`) — update `docs/FINDINGS_BACKLOG.md` to match reality, with commit refs as evidence.
   - **`docs/WORKLOG.md`** — append an entry (or entries) covering the `f5ffa02..edadbf5` range: VND currency default change, admin trashed-bookings route + RBAC reconciliation, nights-sign fix, and the two index-count refreshes. Match the existing entry format/voice.
   - **`PROJECT_STATUS.md`** — refresh "Last Updated" and "Latest Commit" to `edadbf5` (or the actual HEAD at execution time, if it has moved). If test-count/gate-status fields require a fresh run to update honestly, either run the relevant gate from `docs/COMMANDS_AND_GATES.md` or mark the field `[NEEDS RUNTIME CONFIRMATION]` per the convention in `.claude/memory/subagents/docs-sync.md` — do not invent numbers.
   - **`docs/COMPACT.md` §1** — read the lifecycle policy, then trim §1 to under 12 lines by archiving/consolidating per that policy (not by deleting information outright), bump `last_verified_at`, and add a line for this sync if the policy's format calls for one.
   - **Currency references** — grep `ARCHITECTURE_FACTS.md`, `DB_FACTS.md`, `COMMANDS.md`, and `docs/PERMISSION_MATRIX.md` for USD-specific examples/assumptions that `0307e95`/`f703452` (VND default) makes stale.
   - **Index counts** — confirm `CLAUDE.md`/`AGENTS.md` already reflect "7345 nodes / 21657 edges" from `edadbf5`; if `1999729`'s intermediate "7329/21609" was also stamped and is now superseded, confirm only the final count remains.
   - **`.claude/memory/subagents/docs-sync.md` "Known Drift Areas"** — re-check FU-1 (legacy cancellation tests), FU-5 (Room CUD tests), and the `rooms.status` → `rooms.readiness_status` deprecation note against current source; update/close entries that the `f5ffa02..edadbf5` range resolved, leave the rest untouched.

</task_specification>

---

<constraints>

- **C-1 — Scope is pre-confirmed.** This prompt is the human-in-the-loop confirmation `.claude/commands/sync-docs.md` requires before proceeding. Do not pause to ask for scope confirmation; the scope is exactly `<task_specification>` plus whatever step 4 (diffing) surfaces within the same document set. If step 4 surfaces a need to touch a document *outside* this set, stop and flag it under "Remaining Drift" rather than editing it. If any document named in `<task_specification>` does not exist at the stated path, do not proceed past that — list it under "Remaining Drift" with the path you checked, per CLAUDE.md's escalation rule for missing required files.
- **C-2 — Docs only.** No changes to `backend/`, `frontend/`, `.github/`, `docker-compose*`, or any application code (CONTRACT.md "DoD: Documentation Changes", item 1). If a doc fix seems to require a code change to be true, that's a code bug — log it to `docs/FINDINGS_BACKLOG.md` as a new finding starting at **F-94** and do not fix it.
- **C-3 — Line-level edits.** Edit only the specific stale lines/sections identified by your diff. The one sanctioned exception is `docs/COMPACT.md` §1, where its own lifecycle policy explicitly licenses consolidation/archival — follow that policy's mechanism, don't freelance a different restructuring.
- **C-4 — Max 25 files this pass**, counted only among files you edit (see `<codebase_facts>` "Environment noise"). If your diff surfaces more than 25 genuinely stale files, edit the highest-confidence/highest-impact 25 and list the rest under "Remaining Drift" with reasons.
- **C-5 — Evidence tags are mandatory.** Every claim in your report is exactly one of `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]`, or `[ACTION]`, per `.claude/output-styles/docs-sync.md`. An untagged claim is a defect in the report.
- **C-6 — Inspect, don't invent.** `docs/PERMISSION_MATRIX.md` is the sole RBAC source of truth. If a contract is missing or two layers conflict at the same decision-order rank, mark it `UNRESOLVED` (CLAUDE.md escalation rules) — do not pick a side and do not invent a rule to fill the gap.
- **C-7 — Tool surface.** Use `Read`, `Grep`, `Glob`, and `Edit` for the documentation work (matches `.claude/commands/sync-docs.md`'s `allowed-tools`); use the soleil-ai-review-engine MCP/CLI for code-side verification per CLAUDE.md. Do not run `git commit`, `git push`, or any history-altering command — leave the working tree edited but uncommitted for human review (CONTRACT.md: "Changes reviewed by human before merge to main").
- **C-8 — Memory-file scoping.** `docs/agents/AGENT_LEARNINGS.md` reads stay tag-scoped per R-01–R-04 in `docs/agents/AGENT_LEARNINGS_OPERATING_RULES.md`; do not cite `docs/agents/AGENT_LEARNINGS_EXAMPLES.md` as fact (G-06).
- **C-9 — Markdown + link hygiene.** Every changed doc passes markdown lint (valid tables, no unclosed blocks) and you spot-check at least 5 relative links across the changed docs (CONTRACT.md "DoD: Documentation Changes").
- **C-10 — No `--no-verify`, no gate bypass.** If a required gate from `docs/COMMANDS_AND_GATES.md` fails while you're refreshing PROJECT_STATUS.md's numbers, report the failure as a finding — do not suppress it and do not edit the doc to hide it.
- **C-11 — `.claude/worktrees/**` is not a source.** If any search surfaces hits under `.claude/worktrees/`, treat them as duplicates of the main tree per CLAUDE.md "Retrieval hygiene" and cite the main-tree path instead.

</constraints>

---

<acceptance_criteria>

- [ ] `.claude/memory/global-invariants.md`, `.claude/memory/repo-truth.md`, `.claude/memory/subagents/docs-sync.md` read before any edit
- [ ] `docs/FINDINGS_BACKLOG.md` F-92 status reconciled against `frontend/src/app/router.tsx` + `docs/PERMISSION_MATRIX.md` reality, with commit-ref evidence, tagged
- [ ] `docs/WORKLOG.md` has new entry/entries covering all 15 commits in `f5ffa02..edadbf5`, matching existing voice/format
- [ ] `PROJECT_STATUS.md` "Last Updated" and "Latest Commit" refreshed; any field that couldn't be honestly refreshed is marked `[NEEDS RUNTIME CONFIRMATION]`, not guessed
- [ ] `docs/COMPACT.md` §1 is under 12 lines, `last_verified_at` bumped, archived content placed per §1's own lifecycle policy
- [ ] Currency (USD→VND) and trashed-bookings-route references in `ARCHITECTURE_FACTS.md` / `DB_FACTS.md` / `COMMANDS.md` / `PERMISSION_MATRIX.md` checked; stale ones edited
- [ ] `.claude/memory/subagents/docs-sync.md` "Known Drift Areas" (FU-1, FU-5, `rooms.status`) re-checked against current source
- [ ] No file under `backend/`, `frontend/`, `.github/`, or `docker-compose*` modified
- [ ] ≤ 25 files edited (excluding the pre-existing CRLF-normalization diff)
- [ ] Every finding/claim tagged `[CONFIRMED]` / `[INFERRED]` / `[UNPROVEN]` / `[ACTION]`
- [ ] Report follows the 4-section `.claude/output-styles/docs-sync.md` structure exactly
- [ ] markdown lint clean + ≥5 relative links spot-checked on changed docs
- [ ] Any new out-of-scope finding logged to `docs/FINDINGS_BACKLOG.md` starting at F-94, not fixed inline

</acceptance_criteria>

---

<thinking>
Before editing anything, work through:

1. **Memory and rules first.** Load the three memory files in step 1, then the 5 `.agent/rules/*.md` files' `verified-against` frontmatter — does any predate the commits in `<codebase_facts>`? That alone may flag rules needing a frontmatter bump even if their body text is still accurate.
2. **F-92 is the highest-value item — resolve it first.** Read `frontend/src/app/router.tsx`'s admin `bookings` child routes as they exist now (post-`7f7fd3b`), the trashed-bookings page component, and `docs/PERMISSION_MATRIX.md` Tables B/F as they exist now (post-`37e4120`/`952b38a`). Three possible outcomes: (a) fully fixed → flip F-92 to Fixed with refs; (b) route exists but an RBAC/UX gap remains → "Partially Fixed", describe the residual; (c) something about the original F-92 description was wrong and the new route doesn't actually address it → keep Open, explain why. Whichever it is, the FINDINGS_BACKLOG edit must name the commit(s).
3. **COMPACT.md §1 — read the rule before applying it.** The lifecycle policy text (above §1) presumably says how old entries leave the snapshot. Don't invent an archival scheme; if the policy itself is silent on *where* things go, that's itself a finding (`UNRESOLVED` or `[ACTION]` to clarify the policy), and the safe interim move is the smallest edit that gets §1 under 12 lines without losing information (e.g., collapsing per-day entries into a single range summary, if the policy permits summarization).
4. **WORKLOG entries — match cadence.** Look at how the `2026-06-12` (`f5ffa02`) entry and a couple before it are structured (heading style, what's included: commit hash, finding IDs, test results). New entries for `f5ffa02..edadbf5` should read as if written by the same author at the time, not retroactively summarized in a different voice.
5. **PROJECT_STATUS numeric fields — don't guess, don't skip.** If refreshing test-count/assertion figures requires running `php artisan test`, either run it (if the environment allows) or mark the figure `[NEEDS RUNTIME CONFIRMATION]` rather than leaving the old number silently presented as current.
6. **Currency sweep — grep broadly, edit narrowly.** A grep for `USD`/`$` across the canonical docs will over-match (legitimate historical notes, unrelated `$variable` syntax in code blocks). Only edit instances that assert *current* USD behavior as fact.
7. **Last pass — re-run the diff from step 4** mentally against your own edits: did every "doc is stale" item from your diff get either edited or moved to "Remaining Drift" with a reason? Nothing should silently fall through.
</thinking>

---

<output_instructions>

Your **work** follows `.claude/commands/sync-docs.md` (procedure, document list, cross-reference sources, canonical-rules check). Your **report** follows `.claude/output-styles/docs-sync.md` exactly — four sections, in this order, each as specified there:

### Code Truth Changed
The actual code/doc changes in `f5ffa02..edadbf5` (and any others your own investigation surfaced) that motivate a doc update — with commit refs.

### Docs Requiring Update
Table `| Doc File | Section | Reason | Confidence |` — only documents you actually opened and read, each reason tagged.

### Updates Applied
Table `| Doc File | Section | Old Content (summary) | New Content (summary) |` — line-level only, one row per `Edit` you made.

### Remaining Drift
Table `| Doc File | Section | Drift Description | Reason Not Fixed |`, or the literal sentence "All identified drift resolved." if nothing remains.

Do not add narrative sections before, between, or after these four beyond what `.claude/output-styles/docs-sync.md` itself permits. Do not restate this prompt back to me.

</output_instructions>
