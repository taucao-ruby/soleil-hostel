# Full Harness Audit — Execution Prompt (Dev-Agent Governance + Runtime AI Harness)

> **Purpose:** Paste the fenced block below into **Sonnet 5 (agentic / Claude Code mode, repo mounted)** to run a full-surface audit of both harness layers in this monorepo: (A) the dev-agent governance harness that constrains AI coding agents (`CLAUDE.md` → `.claude/`, `.agent/`, `skills/`, `docs/agents/`), and (B) the runtime AI harness shipped as product code (`backend/app/AiHarness/**`, the 7-layer pipeline documented in `docs/HARNESS_ENGINEERING.md`). This is a meta-audit: it audits the audit system.
> **Author of this prompt:** generated for Tau (Principal Engineer, ex-Booking.com). **Generated:** 2026-07-02.
> **Snapshot at generation:** branch `dev`, HEAD `a5ace6a`. Working tree showed ~560 files with pending changes at generation time, with symmetric insertion/deletion counts consistent with line-ending normalization rather than semantic edits — **not verified either way, re-derive live in Phase 0.** Do not trust any specific number, date, or "still open" claim below past Phase 0 — this repo moves fast (77 commits landed between the last two documentation reconciliation passes alone) and every fact here is a *lead*, not a conclusion.
> **Posture:** audit-only · read-only over existing files · zero application/doc edits · zero commits · exactly **one** new output file (the audit report). Findings about concrete code defects are *listed as candidates* in the report, not written into `docs/FINDINGS_BACKLOG.md` directly — a human promotes them afterward, mirroring the PROPOSED-entry pattern already established in `docs/agents/AGENT_LEARNINGS.md`.
> **Not a substitute for:** `/audit-security` (OWASP + business-logic security review — already owned by `.claude/commands/audit-security.md` / `.claude/agents/security-reviewer.md`) or `/sync-docs` (product-doc-vs-code reconciliation — already owned by `.claude/commands/sync-docs.md` / the `docs/DOCS_SYNC_EXECUTION_PROMPT.md` pattern). This prompt is narrower and stranger: it checks whether the *governance system itself* is internally consistent, actually enforced, not stale, and not a security hole in its own right — and whether the in-product AI agent's safety boundary holds up against its own written threat model.

---

```text
<role>
You are a Distinguished Engineer panel of one: a Google-style staff engineer's insistence on facts
traceable to source and line number; an Anthropic harness engineer's fluency in agent scaffolding —
policy layers, tool classification, evidence tagging, fail-closed defaults, kill switches; and an
Apple-style security reviewer's paranoia about what a "safe by design" claim actually covers versus
what it merely asserts. You are auditing FOR a Principal Engineer with 15+ years at Booking.com —
skip 101-level explanations of overlap intervals or OTA booking mechanics, go straight to evidence,
mechanism, and blast radius. Write nothing you have not verified by reading the cited file yourself
in THIS session. Do not carry forward any fact from this prompt without re-checking it — everything
below is a snapshot, not ground truth.
</role>

<mission>
This repository has TWO distinct harness layers. Audit both, and the seam between them:

  LAYER A — Dev-Agent Governance Harness: the instruction/hook/memory/skill system that constrains
  AI coding agents (you, and any other agent) operating on this codebase. Rooted at `CLAUDE.md`.

  LAYER B — Runtime AI Harness: production code at `backend/app/AiHarness/**` that mediates a
  live in-product AI assistant's access to booking/room/guest data and tools, per the 7-layer
  pipeline in `docs/HARNESS_ENGINEERING.md` (L1 normalizer → L2 context assembly → L3 model
  execution → L4 policy enforcement → L5 tool orchestration → L6 observability → L7 eval gate).

For each layer, determine: does the documentation match the code? Does the enforcement mechanism
(hook, gate, constraint, guard script) actually implement what its own docs claim? Is anything
stale, contradictory, unowned, or quietly no longer true? Then find the SEAM: does Layer A's
governance actually cover Layer B correctly (skills, bundles, review ownership, DoD checklists),
and does Layer B's safety boundary hold where it touches Layer A's three declared business-critical
invariants — double-booking prevention, token/session security, cancellation/refund integrity
(see `CLAUDE.md` → Mission)?

Produce ONE audit report. Do not fix anything. Do not edit any existing file. Do not commit.
</mission>

<authoritative_context>
Read `CLAUDE.md` first, in full, before anything else — its Decision Order and Document Map are
canonical and this prompt does not attempt to restate them. What follows is orientation + a seed
list of leads found while preparing this prompt. Every lead is unverified by design — your job in
the matching phase is to confirm, refute, or upgrade each to a tagged finding.

LAYER A — file surface (confirmed to exist at prompt-generation time; re-confirm, do not assume):
  - Root: `CLAUDE.md`, `AGENTS.md`
  - `docs/agents/`: README, ARCHITECTURE_FACTS, CONTRACT, COMMANDS, CONTROL_PLANE_OWNERSHIP,
    TASK_BUNDLES, AGENT_LEARNINGS(+OPERATING_RULES+REFERENCE), api-handoff-protocol
  - `docs/`: PERMISSION_MATRIX, DB_FACTS, DOMAIN_LAYERS, COMMANDS_AND_GATES, HOOKS, MCP,
    AI_GOVERNANCE, COMPACT, FINDINGS_BACKLOG (read-only — never edit this one)
  - `.agent/`: `ARCHITECTURE.md` (root-level, CRLF, ~3.7KB — see Lead L-2), `rules/*.md` (8),
    `workflows/*.md` (3), `scripts/*.sh` (2: check-locking-coverage.sh, check-migration-safety.sh)
  - `.claude/`: `agents/*.md` (4), `commands/*.md` (6), `hooks/*.sh` (3), `memory/*.md` (4) +
    `memory/subagents/*.md` (4), `output-styles/*.md` (7), `settings.json`, `settings.local.json`
    (gitignored, individual-developer-owned per CONTROL_PLANE_OWNERSHIP.md — see Lead L-4)
  - `skills/`: README + `laravel/*.md` (7), `react/*.md` (7), `ops/*.md` (3) = 17 skill files
  - `tools/hooks/`: hook-policy.json, pre-commit.mjs, commit-msg.mjs, pre-push.mjs, lib/
  - `ci/gates/`: 12 gate scripts + `manifest.tsv` (single source for CI↔local gate mirroring,
    SHA-pinned via `.ship-hashes.sha256`); `.github/workflows/*.yml` (9 workflows)
  - `scripts/verify-control-plane.sh` (7-section control-plane self-check), `scripts/ship.sh`,
    `scripts/update-ship-hashes.sh`
  - `mcp/soleil-mcp/` (read-only + allowlisted-verify MCP server; `policy.json` is its contract)
  - `LOCKING-GUARD.md` + `booking-write-services.yaml` + `.locking-guard.sha256` +
    `.github/workflows/locking-guard.yml` — a SEPARATE, tamper-sealed, fail-closed CI gate proving
    every declared booking-write service has a lock primitive
  - `.gitleaks.toml`, `.trivyignore`, `.spectral.yaml` — scanner configs (audit for over-broad
    allowlist entries, not just presence)
  - soleil-ai-review-engine index at `.soleil-ai-review-engine/meta.json` — `CLAUDE.md`/`AGENTS.md`
    currently cite "7497 symbols, 22034 relationships, 300 execution flows"; `docs/COMPACT.md`
    (dated 2026-06-14, HEAD `edadbf5`) cites "7345 / 21657 / 300" — different commits, plausibly
    both correct at the time, but confirm the CURRENT numbers match what's live in `meta.json` now
    (there is precedent for this drifting silently — see `git log --oneline | grep -i "restamp.*soleil"`)

LAYER B — file surface (confirmed to exist; re-confirm):
  - `backend/app/AiHarness/`: DTOs/ (5), Enums/ (4), Evaluation/, Exceptions/ (7), Middleware/ (3),
    Providers/ (3 + interface), Services/ (7), `PromptRegistry.php`, `ToolRegistry.php`
  - Controllers/models referenced in prior sessions (confirm current existence and names):
    `AiController.php`, `ProposalConfirmationController.php`, `ProposalDecisionRequest.php`,
    `AiProposalEvent.php` — grep for them, do not assume the exact path
  - `backend/tests/AiEval/golden/*.json` (4 datasets per `docs/EVAL_STRATEGY.md`: faq_lookup=10,
    room_discovery=8, admin_draft=12, action_proposals=10 — verify actual counts match)
  - `backend/tests/Feature/AiHarness/**`, `backend/tests/Unit/AiHarness/**`
  - `docs/HARNESS_ENGINEERING.md`, `docs/ADR-AI-BOUNDARY.md`, `docs/THREAT_MODEL_AI.md`,
    `docs/EVAL_STRATEGY.md`, `docs/RUNBOOK_AI_INCIDENT.md`, `docs/agents/ARCHITECTURE_FACTS.md`
    § "AI Harness Domain"
  - `eval_runs/` (structure/recency only — do not do a full historical analysis)

SEED LEADS (unverified — resolve each in the phase noted; do not accept or reject without evidence):

  L-1 [→ Phase 2]  ALREADY PARTIALLY RESOLVED WHILE PREPARING THIS PROMPT — do not re-litigate,
      but DO independently re-confirm before citing it, and finish the second half of the finding.
      `.claude/memory/unresolved-risks.md` and `.claude/memory/recurring-failures.md` (both last
      touched ~2026-04-07) describe F-33 (`CancellationService::finalizeCancellation()`, cited
      there at `CancellationService.php:268-282`, missing `lockForUpdate()`, refund/status race)
      as an OPEN, unresolved risk. `docs/FINDINGS_BACKLOG.md` line ~53 and ~219 mark F-33
      **Fixed** (commit `bd3cff9`): `finalizeCancellation()` now re-acquires the lock and re-reads
      fresh — relocated to `CancellationService.php:373-385` — with an idempotent terminal-state
      guard. The line numbers in the memory files are therefore also stale (code moved when the
      fix landed). `docs/COMPACT.md` (2026-06-14)'s "Open findings" list correctly omits F-33 —
      it was already fixed before that snapshot. THE REAL FINDING: `.claude/memory/*.md` is
      citing a fixed issue as an open risk with stale line numbers, four-plus months after the
      fix. (1) Re-read `CancellationService.php:373-385` yourself and confirm the fix is real and
      matches the backlog's description. (2) Confirm this is the shape of finding this audit
      should surface — the memory layer's staleness, not the code's — and check whether other
      entries in `unresolved-risks.md` / `recurring-failures.md` have the same problem (several
      reference other F-IDs; re-verify each against current `FINDINGS_BACKLOG.md` status).

  L-2 [→ Phase 2]  `.agent/ARCHITECTURE.md` (root of `.agent/`, ~3.7KB) and
      `docs/agents/ARCHITECTURE_FACTS.md` (~28KB) have near-identical implied purpose by filename.
      Determine whether `.agent/ARCHITECTURE.md` is a live second source of truth, a stale orphan
      that predates the `docs/agents/` restructure, or something else entirely (read both in full).
      If it is an unreconciled duplicate, this is itself a governance-integrity finding regardless
      of which one is "more correct."

  L-3 [→ Phase 3]  `docs/agents/TASK_BUNDLES.md` defines 6 bundles (backend-safe-fix,
      frontend-contract-fix, migration-audit, auth-review, docs-sync-only, full-release-gate).
      `docs/agents/CONTRACT.md` has a dedicated "DoD: AI Harness Changes" section with ~10
      AI-harness-specific checklist items. No bundle in TASK_BUNDLES.md appears to map to AI
      Harness work, and no `skills/**/*ai-harness*` or `skills/**/*prompt*` file exists in the
      17-file skill inventory. Confirm this gap: an agent picking up an AI-harness task today has
      no bundle to reference for which skills/rules to load, unlike every other task type.

  L-4 [→ Phase 6]  `.claude/settings.local.json` is gitignored, individual-developer-owned, and
      NOT covered by: gitleaks (never committed → never scanned), `tools/hooks/pre-commit.mjs`
      secret-pattern scan (same reason), or `.claude/hooks/guard-sensitive-files.sh` (which blocks
      EDITS by file-path glob — `.env*`/`.key`/`.pem`/`.secret`/`id_rsa*` — and `settings.local.json`
      matches none of those globs). Its `permissions.allow` array is a growing list of literal
      shell-command strings from past ad hoc debugging sessions. On inspection while preparing
      this prompt, entries were observed containing what read as literal credential material
      (an auth bearer token, a `PGPASSWORD=`-style inline password, a login payload with a
      plaintext password). CONFIRM OR REFUTE with fresh inspection; if confirmed, **do not
      reproduce the secret value anywhere in your output** — cite file + approximate line only,
      classify by pattern family (matches which `tools/hooks/hook-policy.json` `blocked_patterns`
      regex, if any, or "credential-shaped, no existing pattern covers it"), and recommend
      rotation of any real (non-placeholder, non-test-fixture) credential found.

  L-5 [→ Phase 3]  `.claude/hooks/block-dangerous-bash.sh` logs a 200-char snippet of any blocked
      *destructive* command (`rm -rf`, `git push --force`, `git reset --hard`,
      `git checkout -- .`) verbatim to `.claude/hook-audit.log`, but only redacts the snippet for
      the separate *credential-reference* branch (`APP_KEY`/`DB_PASSWORD`/etc. → logs
      `[REDACTED-CREDENTIAL-REF]`). Check whether a blocked command can match the destructive
      branch while ALSO carrying a credential — e.g. `git push --force https://user:TOKEN@host/...`
      — in which case the verbatim 200-char log would leak the token into `.claude/hook-audit.log`.
      Read the script's `case` statement precisely; do not guess from this description.

  L-6 [→ Phase 3]  `tools/hooks/hook-policy.json` defines `blocked_patterns` (AWS keys, Stripe
      keys, OpenAI keys, JWTs, private-key blocks) as CONTENT regexes. Confirm where in the
      pipeline these content patterns actually run: `tools/hooks/pre-commit.mjs` (git-commit time)
      is the likely enforcement point. Confirm `.claude/hooks/guard-sensitive-files.sh` (Claude
      Code edit-time) does NOT also apply these content regexes — it currently appears to be
      PATH-glob-only. If so, this is the precise mechanism behind Lead L-4: nothing stops an
      agent from writing real secret-shaped content into an edit-time file that isn't
      git-committed and doesn't match a blocked path glob.

  L-7 [→ Phase 1]  `scripts/verify-control-plane.sh` § "[5/7] Rule file freshness" checks
      `verified-against`/`last-verified` frontmatter and flags files >90 days stale — but it
      globs only `.agent/rules/*.md`. It does NOT check `.claude/memory/*.md` or
      `.claude/memory/subagents/*.md`, even though those files carry their own prose
      "Verified <date>" claims (e.g. `global-invariants.md`, `repo-truth.md`,
      `unresolved-risks.md` all say "Verified 2026-04-07" in prose, with no machine-checkable
      frontmatter). Confirm this coverage gap and compute current staleness for every file in
      both sets against today's actual date (Phase 0) — at prompt-generation time,
      `auth-token-safety.md` (2026-03-16), `booking-integrity.md` (2026-03-17), and
      `migration-safety.md` (2026-03-16) were already past the 90-day line; recompute, don't reuse
      these numbers.

  L-8 [→ Phase 3]  `LOCKING-GUARD.md` / `booking-write-services.yaml`: the guard only proves lock
      coverage for services DECLARED in the manifest. Grep `backend/app/Services/**` (and
      `backend/app/AiHarness/Services/**`) for `lockForUpdate(` / `withLock(` / write-paths that
      mutate `bookings`, and diff that set against the manifest's declared services (including
      `lockedVia` delegations). Flag any undeclared booking-mutating service — that is a real
      coverage hole in a fail-closed gate that believes it has zero holes.

  L-9 [→ Phase 4]  Confirm `backend/app/AiHarness/**`'s proposal-confirmation write path
      (Phase 4 / T-13,T-14 in `docs/THREAT_MODEL_AI.md`, F-67 proposer-binding) delegates to the
      SAME locked, exclusion-constraint-protected path audited under Lead L-8 — per
      `docs/ADR-AI-BOUNDARY.md`'s consequence claim ("no autonomous writes... downstream
      delegates to existing service layer") — rather than a parallel write path that bypasses it.
      This is the highest-value Layer A/B seam check in this audit: an AI-initiated booking
      mutation that skipped the lock/constraint path would be a CRITICAL finding.

  L-10 [→ Phase 5] `.claude/agents/security-reviewer.md`'s "Owned Scope" enumerates Auth, Locking,
      Authorization, Input Sanitization, Payment/Refund — no explicit AI-harness / prompt-injection
      / policy-enforcement ownership, despite Layer B being one of the highest-blast-radius
      subsystems with its own dedicated threat model. Confirm whether AI-harness review is owned
      elsewhere (search `.claude/agents/`, `.claude/commands/` for any explicit binding) or is
      genuinely unowned per `docs/agents/CONTROL_PLANE_OWNERSHIP.md`'s own "Unowned Component
      Policy."

  L-11 [→ Phase 0] Working tree showed ~560 modified files at generation time, concentrated across
      `skills/`, `tools/hooks/`, `tests/performance/`, `stitch-output/`, and (per an initial
      `git status --short`) a first batch of `.agent/`+`.claude/` governance files, with symmetric
      insertion/deletion line counts in `git diff --stat` — a classic line-ending-normalization
      signature, NOT necessarily semantic drift. A stale `.git/index.lock` was also observed
      (permission-denied on unlink), which may be an artifact of the sandboxed exploration
      environment rather than the user's real machine. Do not draw ANY conclusion from working-tree
      state without first classifying it (Phase 0): run `git diff -- <file>` on 2-3 sample files
      from each cluster and determine semantic-vs-whitespace before treating "modified" as meaningful.
</authoritative_context>

<governance_you_must_obey>
Read these before writing any finding, in this order — higher layer wins on conflict:
1. `CLAUDE.md` — Decision Order, Document Map, Escalation Rules, Output Style Policy.
2. `docs/agents/CONTRACT.md` — this task most resembles "DoD: Documentation Changes" (no code
   touched) but is even narrower: it produces exactly one new file and edits nothing existing.
3. `.claude/output-styles/audit-report.md` — MANDATORY output style. Every finding gets exactly
   one tag: `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]`, or `[ACTION]`. An untagged factual claim in
   your output is itself a defect in the audit, not a stylistic nit.
4. `docs/agents/AGENT_LEARNINGS_OPERATING_RULES.md` §1 (READ RULES) — you are not writing a
   learning entry, but the same evidence bar applies to every claim you make here.

Hard rules:
- ZERO EDITS to any existing file. This includes `docs/FINDINGS_BACKLOG.md`,
  `docs/COMPACT.md`, and every file listed in `<authoritative_context>`. You may create exactly
  one new file: the audit report (path specified in `<output_format>`).
- ZERO COMMITS. Do not run `git add`, `git commit`, or anything that mutates repo state.
- Do not run destructive or state-mutating commands. Read-only `git` (`status`, `diff`, `log`,
  `show`, `branch --show-current`, `rev-parse`), `cat`/`Read`, `grep`/`Grep`, `sha256sum -c` for
  the two tamper-seal checks, and the specific verification scripts named in `<workflow>` Phase 0
  are all in scope. `php artisan test`, `npx vitest run`, `npx tsc --noEmit`, and the full E2E
  suite are OPTIONAL and explicitly non-blocking for this audit — see `<scope>`.
- If a file named in `<authoritative_context>` does not exist where expected: do not guess its
  contents or assume it moved. Say so as a finding and move on.
- If evidence conflicts and you cannot resolve which source is authoritative even after checking
  `CLAUDE.md`'s Decision Order: mark it `UNRESOLVED`, do not invent a resolution.
- Out-of-scope code defects discovered incidentally (i.e., not about the harness itself) are
  listed as report candidates only — per the header above, do not write them into
  `docs/FINDINGS_BACKLOG.md` yourself.
- Where you use soleil-ai-review-engine MCP tools, use them read-only: `query`/`context` to trace
  call chains (e.g., confirm the L1→L7 pipeline wiring, confirm proposal-confirmation delegates
  into the locked booking path). `detect_changes({scope:"compare", base_ref:"main"})` may help
  Phase 0 establish what's genuinely different on `dev` vs `main`. Do not use `rename` — nothing
  is being refactored.
</governance_you_must_obey>

<scope>
IN SCOPE: everything enumerated under LAYER A and LAYER B in `<authoritative_context>`, plus the
11 seed leads and the 9 phases in `<workflow>`.

OUT OF SCOPE (explicitly — do not wander):
- General OWASP/business-logic security review of application code outside the harness surfaces
  (owned by `/audit-security`). You MAY cite specific harness-adjacent code (e.g. lock call sites
  for Lead L-8/L-9) but do not perform a free-ranging vulnerability sweep of controllers/services
  unrelated to governance or AI-harness enforcement.
- Product documentation ↔ code reconciliation for business features (MoMo payments, room listings,
  reviews, etc.) — owned by the `/sync-docs` / `docs/DOCS_SYNC_EXECUTION_PROMPT.md` pattern.
- Frontend UX, performance/Core Web Vitals, i18n completeness, accessibility.
- Full correctness review of `.github/workflows/*.yml` internals — cross-check names/existence
  against `ci/gates/manifest.tsv`'s `source` column only; do not re-audit each workflow's YAML
  logic line by line.
- Running the full backend/frontend/E2E test suites to completion. Optional targeted test reads
  (e.g., "does a test exist asserting X") are fine; running `php artisan test` in full is not
  required and should not block report delivery if it's slow or the environment can't run it —
  mark anything you couldn't runtime-verify as `[UNPROVEN]` instead.
- Fixing, refactoring, or renaming anything.
</scope>

<workflow>
Work phase by phase, in order. Each phase produces findings for the report — do not silently skip
a phase; if it doesn't apply or you can't complete it, say so explicitly with a reason.

PHASE 0 — Environment & Baseline (read-only)
  - `date -u +%Y-%m-%dT%H:%M:%SZ` — anchor "today" for every staleness computation that follows.
  - `git rev-parse HEAD`, `git branch --show-current`, `git status --short`, `git diff --stat`.
    Resolve Lead L-11: sample `git diff -- <file>` on 2-3 files per modified cluster and classify
    semantic vs whitespace/line-ending before treating any "modified" file as meaningful for later
    phases. If `git status` cannot run cleanly (e.g. lock file, permissions), say so and note the
    limitation rather than silently working around it.
  - Run `bash scripts/verify-control-plane.sh` and capture its verdict verbatim. `docs/COMPACT.md`
    (§5, Known warnings) claims this script "is currently blocked in this Windows environment" —
    if it runs cleanly for you, that COMPACT note is itself stale; say so.
  - Check `.soleil-ai-review-engine/meta.json` (or equivalent freshness resource) against the
    symbol/relationship/flow counts currently stated in `CLAUDE.md` and `AGENTS.md`.
  - Confirm `jq`, `node`, `php`, `docker` availability (mirrors verify-control-plane.sh §1) — note
    any missing tool, since several hooks fail OPEN (not closed) when `jq` is absent per their own
    source comments.

PHASE 1 — Structural Integrity of the Instruction Hierarchy (Layer A)
  - For every file in `CLAUDE.md`'s Decision Order (1–10) and Document Map: confirm it exists,
    is non-empty, and is reachable at the path cited.
  - Confirm `docs/agents/README.md`'s file table matches what's actually in `docs/agents/`.
  - Walk `docs/agents/CONTROL_PLANE_OWNERSHIP.md`'s ownership matrix: does every listed component
    still exist? Is anything currently `UNOWNED` per its own policy but not flagged as such?
  - Resolve Lead L-7: compute current staleness (against Phase 0's date) for every
    `verified-against`/`last-verified` file in `.agent/rules/*.md`, AND for the prose-dated claims
    in `.claude/memory/*.md` + `.claude/memory/subagents/*.md`. Produce one table covering both
    sets, since `verify-control-plane.sh` only mechanically covers the first.
  - Confirm `docs/agents/TASK_BUNDLES.md` bundle definitions reference skills/rules that actually
    exist by filename (cross-check all 17 skill files + 8 rule files are correctly named).

PHASE 2 — Internal Consistency & Drift (Layer A)
  - Resolve Lead L-1 (F-33 status) — check `docs/FINDINGS_BACKLOG.md`'s current entry for F-33 (or
    its absence), the actual current state of `CancellationService.php` around the cited lines,
    and `git log --oneline -- backend/app/Services/CancellationService.php` since 2026-04-07 for
    any fix commit. State definitively: open, fixed-but-memory-stale, or renumbered.
  - Resolve Lead L-2 (`.agent/ARCHITECTURE.md` vs `docs/agents/ARCHITECTURE_FACTS.md`) — read both
    in full, compare scope and content, determine live-duplicate vs stale-orphan vs distinct-purpose.
  - Spot-check `docs/PERMISSION_MATRIX.md`'s five open Follow-ups (FU-1..FU-5) — still open?
  - Check `docs/agents/AGENT_LEARNINGS.md`: confirm "zero active entries" is still accurate, and
    check the PROPOSED section (e.g. any `SL-NNN` entries) for entries past their `stale_after`
    date or overdue for the human-review step described in
    `AGENT_LEARNINGS_OPERATING_RULES.md` §7.
  - For each of the four domain-truth clusters (booking overlap, auth/token, RBAC, migrations),
    diff the claim as stated in `CLAUDE.md` → `ARCHITECTURE_FACTS.md` → the matching
    `.agent/rules/*.md` → the matching `.claude/memory/*.md` file. They should all agree; flag any
    divergence, however small, as a Drift & Contract Mismatch finding.

PHASE 3 — Enforcement Reality (Layer A: does the harness actually do what it claims?)
  - Cross-check `.claude/settings.json` `permissions.deny` glob patterns against each
    `.claude/hooks/*.sh` script's actual runtime `case` matching. Identify any asymmetry — either
    layer catching something the other doesn't (fine, defense-in-depth — say so) or a real gap.
  - Resolve Lead L-6 (does any edit-time hook apply `tools/hooks/hook-policy.json`'s CONTENT
    `blocked_patterns`, or only `pre-commit.mjs` at commit time?).
  - Resolve Lead L-5 (destructive-command audit-log redaction gap).
  - Resolve Lead L-3 (AI Harness task-bundle gap).
  - Verify `.locking-guard.sha256` and `.ship-hashes.sha256` actually validate against current
    file contents (`sha256sum -c`) — these are tamper-seals; a mismatch is itself a finding
    (either intentional un-repinned change, or integrity concern).
  - Resolve Lead L-8 (booking-write-services.yaml manifest completeness vs actual lock call sites).
  - Spot-check `ci/gates/manifest.tsv` rows: script file exists, `source` workflow:job exists in
    `.github/workflows/`.

PHASE 4 — Runtime AI Harness Architecture & Safety Boundary (Layer B)
  - Trace the L1→L7 pipeline in `docs/HARNESS_ENGINEERING.md` against real classes/methods in
    `backend/app/AiHarness/**` (soleil-ai-review-engine `context`/`query` on
    `AiOrchestrationService` is the fastest path in). Confirm the documented call order is the
    real call order.
  - Verify `docs/ADR-AI-BOUNDARY.md`'s "no autonomous writes" claim: confirm `ToolRegistry`
    statically classifies every mutation tool `BLOCKED`, and that unknown tools default to
    `BLOCKED` (read the actual classification logic, don't infer from the docs table).
  - Verify the kill switch: read `AiHarnessEnabled` middleware — does `AI_HARNESS_ENABLED=false`
    actually short-circuit to a 404 as `docs/RUNBOOK_AI_INCIDENT.md` claims? If you cannot run a
    live server to confirm the HTTP behavior, mark it `[UNPROVEN]` with "confirmed in source,
    not runtime-tested" rather than asserting it works.
  - Verify `docs/THREAT_MODEL_AI.md` T-13/T-14 (proposer-binding, F-67): confirm
    `proposer_user_id` binding, the 404-on-mismatch behavior, `throttle:5,1`, and
    `Cache::forget()` post-decide are all actually present at the cited controller.
  - Walk `docs/agents/CONTRACT.md`'s "DoD: AI Harness Changes" checklist item by item; for each,
    find the test(s) that would actually catch a regression (e.g., is there a test asserting
    admin-only context sources are blocked for non-admin users at context-assembly time?). List
    any checklist item with no corresponding test as a gap.
  - Compare `docs/EVAL_STRATEGY.md`'s golden-dataset scenario counts against the actual JSON files
    in `backend/tests/AiEval/golden/`.

PHASE 5 — Cross-Layer Coverage (does Layer A's governance actually cover Layer B?)
  - Resolve Lead L-3 again from the review-ownership angle and Lead L-10 together: is there ANY
    explicit binding (in `.claude/agents/*.md` or `.claude/commands/*.md`) for reviewing changes
    to `backend/app/AiHarness/**`? If not, state plainly that AI-harness changes currently have a
    DoD checklist (CONTRACT.md) but no assigned reviewer persona and no task bundle — a structural
    coverage gap, not a hypothetical one.
  - Check whether `docs/agents/CONTROL_PLANE_OWNERSHIP.md`'s matrix includes any Layer-B files
    (it appeared, at generation time, to cover only Layer-A control-plane files) — if Layer B has
    no ownership row at all, say so.

PHASE 6 — Security & Secret-Hygiene Sweep of the Governance Surface Itself
  - Resolve Lead L-4. Do not reproduce any discovered secret value — cite file + approximate line
    + pattern family only, and recommend rotation if it looks like a real, non-placeholder
    credential (e.g., matches a live-looking email/password pair, a token with real entropy, a
    non-"example"/non-"test"/non-"placeholder" DB password).
  - Extend the same check to any other gitignored-but-live local config you find referenced by
    `docs/agents/CONTROL_PLANE_OWNERSHIP.md` as "Individual Developer" owned.
  - Review `.gitleaks.toml` and `.trivyignore` for allowlist/ignore entries broader than their
    likely intended scope (a common way real findings get silently suppressed).
  - Confirm `.claude/hook-audit.log` (if present) doesn't itself contain leaked material per
    Lead L-5, and isn't tracked in git if it's meant to be local-only (check `.gitignore`).

PHASE 7 — Business-Critical Invariant Cross-Check (the Layer A/B seam)
  - Resolve Lead L-9 — this is the single highest-value check in this audit. Confirm, by reading
    the actual proposal-confirmation controller/service code, that AI-initiated booking mutations
    delegate to the exact same `lockForUpdate()` + PostgreSQL exclusion-constraint-protected path
    used by direct API bookings, with no parallel or short-circuit write path. Treat any deviation
    as CRITICAL.
  - Re-confirm token/session security (dual-mode Sanctum chain) is unaffected by anything in
    Layer B — the AI harness should never touch auth token issuance/validation directly; confirm
    it doesn't.
  - Tie back to Lead L-1: F-33 itself is fixed (`CancellationService.php:373-385`), but confirm a
    confirmed AI cancellation proposal reaches `finalizeCancellation()` through the SAME fixed,
    re-locked path — not a route that predates or bypasses the `bd3cff9` fix.

PHASE 8 — Report Assembly & Delivery
  - Write the report per `.claude/output-styles/audit-report.md` exactly: Scope / Sources of Truth
    / Confirmed Findings table / Unproven-Needs-Runtime-Validation / Drift & Contract Mismatch /
    Priority Stack (Critical / High / Debt) / Go-No-Go / Residual Risk.
  - Save to `docs/AUDIT_<YYYY_MM_DD>_HARNESS.md` using TODAY's actual date from Phase 0 (matching
    the existing naming convention seen in `docs/AUDIT_2026_02_21.md` and
    `docs/AUDIT_2026_03_12_STRUCTURE.md` — do not invent a different path or filename shape).
  - In the Priority Stack, list any newly-discovered code-level defect candidates in a format
    close enough to `docs/FINDINGS_BACKLOG.md`'s existing entry format (read a few real entries
    first to match the shape) that a human can promote them directly — but do not write them into
    that file yourself.
  - End your final chat message with a short pointer to the report file and the one-line
    Go/No-Go headline — do not re-paste the full report into chat.
</workflow>

<evidence_discipline>
Restating `.claude/output-styles/audit-report.md` because this is the part most likely to be
skipped under time pressure — don't skip it:
  - `[CONFIRMED]` — you opened the file and line yourself, this session. Cite `path:line`.
  - `[INFERRED]` — a reasonable conclusion from what you read, but not itself directly evidenced
    (e.g., "no fix commit since 2026-04-07 in `git log`" → "likely still open").
  - `[UNPROVEN]` — would require running code / a live server / production logs to confirm; you
    did not do that. This is not a weaker finding, it's an honestly-scoped one — use it freely.
  - `[ACTION]` — a recommended next step with an owner (role, not a person's name, unless
    `docs/agents/CONTROL_PLANE_OWNERSHIP.md` names one) and a priority.
Untagged claims are a defect in your own output, per the same standard this repo already holds
its agents to (`.claude/memory/recurring-failures.md` § "Agent Overclaiming" is the exact failure
mode to avoid — repo evidence proves code exists, not that it behaves correctly at runtime).
</evidence_discipline>

<output_format>
- One new file: `docs/AUDIT_<YYYY_MM_DD>_HARNESS.md`, following `.claude/output-styles/audit-report.md`.
- No other file is created or modified. No commit is made.
- Final chat message: report location + Go/No-Go headline + count of Critical/High findings.
  Nothing else — the report itself carries the detail.
</output_format>

<definition_of_done>
- [ ] Phases 0–8 each addressed; any skipped phase has an explicit stated reason, not silence.
- [ ] All 11 seed leads (L-1 through L-11) explicitly resolved (confirmed / refuted / upgraded)
      in the report, not just referenced.
- [ ] Every finding carries exactly one evidence tag.
- [ ] Report follows the audit-report.md structure exactly, including a non-empty Residual Risk
      section (or an explicit "None identified" with justification).
- [ ] Zero existing files edited; zero commits made; report saved to the correct path with today's
      real date.
- [ ] No secret value is reproduced anywhere in the report, even redacted-looking ones — describe,
      don't quote.
- [ ] Go/No-Go verdict is explicit and reasoned, addressed to a Principal Engineer audience (no
      101-level explanation of concepts CLAUDE.md already assumes are known).
</definition_of_done>
```

---

**How to use this:** paste the fenced block into a fresh Sonnet 5 Claude Code session with this repo mounted. Expect a long run — nine phases across two harness layers plus eleven seed leads is a genuine deep audit, not a five-minute pass. When it's done, read the Go/No-Go and Priority Stack sections of `docs/AUDIT_<date>_HARNESS.md` first; the rest is supporting evidence. Leads L-1 (`.claude/memory/*.md` citing already-fixed F-33 as open, with stale line numbers — a memory-refresh task), L-4 (`settings.local.json` credential hygiene), and L-9 (AI-proposal booking writes reusing the locked path) are the three most likely to resolve to something you'll want to act on immediately rather than queue.
