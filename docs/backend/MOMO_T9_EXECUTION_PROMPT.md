# MoMo T9 — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. Self-contained, grounded in the
> current Soleil Hostel tree, scoped to **T9 only** (verify → scope-check → commit). T9 writes NO feature
> code: it runs the full gate, proves the change set matches the planned manifest, and lands ONE commit.
> It must NOT push or merge. Read `<human_in_the_loop>` as binding.

````text
<role>
You are a senior release engineer executing inside the Soleil Hostel monorepo. You run the repository's
real quality gates, you prove scope before committing, you respect the commit-message contract and the
hook policy, and you stop at the human-review boundary. You treat CLAUDE.md + its decision order as binding.
</role>

<context>
You are executing task **T9** of `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` — the gate + commit close-out
for the additive MoMo sandbox payment path. T1–T8 are implemented on branch `feature/momo-sandbox-payment`
and are currently UNCOMMITTED. T9 verifies the whole feature, confirms the diff is exactly the planned files,
and creates a single Conventional Commit. No push. No merge.

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → docs/HOOKS.md → the execution plan →
this prompt. Unresolvable conflict → stop and surface as `UNRESOLVED`.

Quality gates and commit rules are repo-defined: `docs/COMMANDS_AND_GATES.md`, `docs/agents/COMMANDS.md`,
`docs/HOOKS.md`. The branch flow is `feature/* → dev → main`; merges are human-reviewed (CLAUDE.md).
</context>

<task>
1. Run the full backend gate green: DB up, lint, static analysis, test suite.
2. Prove scope: the working tree contains EXACTLY the 12 planned feature files (9 new + 3 modified) and nothing
   else feature-related. Cross-check `soleil-ai-review-engine_detect_changes` against raw `git diff`.
3. Stage ONLY those 12 files and create ONE commit: `feat(backend): add MoMo sandbox payment adapter`.
4. STOP. Do not push; do not merge to `dev`. Report results and wait for my go-ahead.

Write no feature code in T9. If a gate fails because of a defect in T1–T8, surface it with the minimal fix
proposal and wait — do not silently patch to make a gate pass.
</task>

<authoritative_references>
1. `docs/HOOKS.md` — the hook contract:
   - `commit-msg` validates Conventional Commits on the first line: types `feat|fix|chore|docs|refactor|test|build|ci|perf|revert`,
     optional scopes `backend|frontend|infra|docs`. `feat(backend): …` is valid (backend is an allowed scope).
   - `pre-commit` runs: blocked-file check (`.env*` EXCEPT `.env.example`, keys, vendor/, node_modules/),
     **secret detection on added lines**, 2 MB size cap, binary check, and lint-staged ONLY for staged FRONTEND files.
   - `pre-push` runs `php artisan test` on backend changes — but you are NOT pushing, so it won't fire.
   - Bypass (`--no-verify` / `SKIP_HOOKS=1`) requires a documented reason + team-lead notice and is prohibited on main.
2. `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` §5 — the deliverable manifest (see <scope_manifest>).
3. CLAUDE.md — "MUST run detect_changes before committing"; "Stop and confirm before … using `--no-verify`",
   "> 25 files", or "proceeding past new gate failures"; soleil index re-analyze after commit.
</authoritative_references>

<scope_manifest>
The commit must contain EXACTLY these 12 files (reconciles the plan's "11 (2 edits, 9 new)" with the §5
"+1 .env.example" — total 9 new + 3 modified):

New (9):
  backend/app/Services/MoMoService.php
  backend/app/Services/Payment/MoMoPaymentStartResult.php
  backend/app/Services/Payment/MoMoIpnOutcome.php
  backend/app/Services/Payment/MoMoIpnHandler.php
  backend/app/Models/MoMoWebhookEvent.php
  backend/database/migrations/<ts>_create_momo_webhook_events_table.php
  backend/app/Http/Controllers/Payment/MoMoPaymentController.php
  backend/tests/Unit/MoMoServiceTest.php
  backend/tests/Feature/Payment/MoMoIpnTest.php
Modified (3):
  backend/config/services.php
  backend/.env.example
  backend/routes/api/v1.php

EXCLUDE from this commit (do NOT stage): the planning scaffolding under `docs/backend/` —
`MOMO_SANDBOX_EXECUTION_PLAN.md` and any `MOMO_T*_EXECUTION_PROMPT.md`. They are prompt/plan docs, a separate
concern from the feature. `git status` will show them untracked/modified; leave them out. Use explicit
`git add <path>` per file — NEVER `git add -A`/`git add .` (that would sweep in the docs and anything else).
</scope_manifest>

<known_friction>
G1 — **`.env.example` secret-scanner false positive (most likely blocker).** The pre-commit secret detector
scans added lines; the MoMo block adds non-empty `MOMO_SECRET_KEY=K951B6PE1waDMi640xX08PD3vg6EkVlz` and
`MOMO_ACCESS_KEY=F8BBA842ECF85`. These are MoMo's PUBLISHED public sandbox credentials (plan §2), NOT real
secrets — but the scanner may flag them. If it does: do NOT reflexively `--no-verify`. STOP and report to me;
the correct fix is either an allowlist entry in `tools/hooks/hook-policy.json` or a documented, signed-off
bypass (HOOKS.md bypass policy). I decide which. (CLAUDE.md: stop and confirm before using `--no-verify`.)

G2 — **Backend Pint is NOT auto-run by the hook.** lint-staged only touches frontend files, so the pre-commit
hook will not format/check the PHP you are about to commit. Run `composer lint` (Pint `--test`) yourself as a
gate; if it reports diffs, run `composer format` (Pint) and re-stage. Do not rely on the hook for PHP style.

G3 — **Post-commit soleil reindex.** Committing makes the soleil index stale. A PostToolUse hook may
re-run `npx soleil-engine-cli analyze` automatically; if your environment has no such hook, run it manually.
First check `.soleil-ai-review-engine/meta.json` → `stats.embeddings`: if > 0, you MUST pass `--embeddings`
(`npx soleil-engine-cli analyze --embeddings`) or you will delete existing embeddings.

G4 — **detect_changes over-reports.** Treat raw `git diff --stat` as ground truth for the file set; use
`soleil-ai-review-engine_detect_changes({scope:"staged", repo:"soleil-hostel"})` for symbol/flow blast-radius,
not as the file-count authority. Reconcile any delta in your report.
</known_friction>

<gate_sequence>
Run in order; stop on the first failure and report (do not proceed past a new gate failure — CLAUDE.md).

    # 0. Confirm branch + clean baseline understanding
    git rev-parse --abbrev-ref HEAD          # expect: feature/momo-sandbox-payment (if not, STOP)
    git status --porcelain                    # review the working set BEFORE staging

    # 1. Test DB up + GATE-0 preflight
    docker compose up -d db
    cd backend && php scripts/check-test-db.php

    # 2. Style + static analysis (hooks do NOT lint backend PHP — G2)
    composer lint                             # Pint --test
    vendor/bin/phpstan analyse                # Larastan/PHPStan per repo config
    # + Psalm if the gate list requires it (docs/COMMANDS_AND_GATES.md)

    # 3. Full suite (the headline gate; composer test = config:clear + artisan test)
    composer test                             # equivalently: php artisan test

    # 4. Scope proof
    git --no-pager diff --stat                # must match the 12-file manifest; docs/backend/*.md NOT staged
    #   soleil-ai-review-engine_detect_changes({scope:"unstaged", repo:"soleil-hostel"})  # blast radius cross-check

    # 5. Stage EXACTLY the 12 files (explicit paths — never `git add -A`), then re-verify
    git add backend/app/Services/MoMoService.php backend/app/Services/Payment/MoMoPaymentStartResult.php \
            backend/app/Services/Payment/MoMoIpnOutcome.php backend/app/Services/Payment/MoMoIpnHandler.php \
            backend/app/Models/MoMoWebhookEvent.php backend/database/migrations/*_create_momo_webhook_events_table.php \
            backend/app/Http/Controllers/Payment/MoMoPaymentController.php \
            backend/tests/Unit/MoMoServiceTest.php backend/tests/Feature/Payment/MoMoIpnTest.php \
            backend/config/services.php backend/.env.example backend/routes/api/v1.php
    git status --porcelain                    # confirm: 12 staged, docs/backend/*.md still untracked/unstaged

    # 6. Commit (hooks run: commit-msg + pre-commit). If the secret scanner flags .env.example → STOP (G1).
    git commit -m "feat(backend): add MoMo sandbox payment adapter"

    # 7. Post-commit
    git show --stat HEAD                       # confirm exactly the 12 files landed
    # soleil reindex per G3 (auto-hook or `npx soleil-engine-cli analyze [--embeddings]`)
</gate_sequence>

<commit_spec>
- Message (exact): `feat(backend): add MoMo sandbox payment adapter`
- NO `Co-Authored-By` trailer. NO `Generated with` / tool attribution lines. Single-line subject is sufficient;
  an optional plain body is fine but keep it factual and secret-free.
- NO `--no-verify` and NO `SKIP_HOOKS` unless I explicitly approve a documented bypass (G1).
- One commit for the whole feature (do not split), on `feature/momo-sandbox-payment`.
</commit_spec>

<acceptance_criteria>
1. `composer test` is green (full suite — Stripe and all existing tests pass), `composer lint` clean, static
   analysis reports no new errors.
2. `git show --stat HEAD` lists EXACTLY the 12 manifest files — no more, no fewer; no `docs/backend/*.md` included.
3. The commit message passes the commit-msg hook (valid `feat(backend): …`) and no hook was bypassed.
4. HEAD is one new commit ahead on `feature/momo-sandbox-payment`; nothing pushed, nothing merged.
</acceptance_criteria>

<human_in_the_loop>
After committing, STOP and report:
  - the full `php artisan test` summary (counts), `composer lint` + static-analysis results,
  - `git show --stat HEAD` (the 12 files),
  - the `detect_changes` vs `git diff` reconciliation,
  - any hook friction encountered (esp. G1).
Then WAIT. Do NOT `git push`. Do NOT merge to `dev` or `main`. Pushing/merging happens only after I review the
report and explicitly say go (CLAUDE.md: merges are human-reviewed).
</human_in_the_loop>

<stop_conditions>
Stop and confirm with me before: editing any file to make a gate pass (surface the defect first); staging
anything outside the 12-file manifest, or using `git add -A`/`git add .`; using `--no-verify`/`SKIP_HOOKS`
(esp. for the G1 secret-scan false positive); proceeding past any new gate failure; pushing; or merging.
If the working tree contains feature changes NOT in the manifest, STOP — the scope diverged from the plan and
I need to review before any commit.
</stop_conditions>
````
