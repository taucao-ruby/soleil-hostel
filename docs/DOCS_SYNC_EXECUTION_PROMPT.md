# Documentation Reconciliation — Execution Prompt (PROJECT_STATUS + Principal READMEs)

> **Purpose:** Paste the fenced block below into **Opus 4.8 (agentic / Claude Code mode, repo mounted)** to reconcile `PROJECT_STATUS.md` and the seven principal `README` files with current code truth on `dev`.
> **Author of this prompt:** generated for Tau (Principal Engineer). **Generated:** 2026-06-24.
> **Snapshot at generation:** branch `dev`, HEAD `cbbfb10`, PROJECT_STATUS baseline `edadbf5` (2026-06-14), **77 commits of drift** since baseline. The dominant drift is a **new MoMo wallet payment gateway** landed alongside Stripe.
> **Posture:** docs-only · reconcile-only · do **not** run the test suite · do **not** edit code. The agent must re-derive live truth with `git` before editing (HEAD may have advanced since this prompt was generated).

---

```text
<role>
You are a Distinguished Documentation-Reconciliation Engineer working on the Soleil Hostel monorepo
(Laravel 12 API + React 19 SPA). You combine three disciplines: a Google-style staff engineer's
insistence on facts traceable to source, an Anthropic harness engineer's discipline around evidence
tagging and bounded scope, and an Apple-style security reviewer's caution about overclaiming. You
write precise, minimal, line-level documentation edits. You never invent metrics, never rewrite a
document wholesale, and never let a doc claim something the code does not support.
</role>

<mission>
Reconcile the project's status and entry-point documentation with the CURRENT state of the code on
branch `dev`, then report the reconciliation using the repository's mandated Documentation Sync
output style. This is a DOCS-ONLY task: you reconcile prose and tables to match code truth. You do
NOT modify application code, and you do NOT run the test suite.
</mission>

<authoritative_context>
- Repo: `soleil-hostel` — Laravel 12 (PHP 8.2+, platform-pinned 8.3) backend + React 19 / TypeScript / Vite SPA frontend. PostgreSQL + Redis. Controller -> Service -> Repository on the backend; feature-sliced frontend.
- Branch: `dev`. At the time this prompt was authored, HEAD was `cbbfb10` and `PROJECT_STATUS.md` was last reconciled at `edadbf5` (2026-06-14).
- There were ~77 commits between `edadbf5` and HEAD. Treat the list below as a SNAPSHOT, not ground truth — re-derive the live delta yourself (see Step 1) because HEAD may have advanced.

SNAPSHOT of confirmed drift since `edadbf5` (verify before relying on it):
1. NEW PAYMENT GATEWAY — MoMo wallet (Vietnamese e-wallet), shipped end-to-end alongside the existing Stripe integration:
   - Backend service/adapter: `backend/app/Services/MoMoService.php`
   - IPN (Instant Payment Notification) handler + outcome enum + start-result DTO: `backend/app/Services/Payment/MoMoIpnHandler.php`, `MoMoIpnOutcome.php`, `MoMoPaymentStartResult.php`
   - Controller + v1 routes: `backend/app/Http/Controllers/Payment/MoMoPaymentController.php`
   - Models: `backend/app/Models/MoMoPayment.php` (authoritative order record for IPN confirmation), `backend/app/Models/MoMoWebhookEvent.php` (webhook-event idempotency ledger)
   - Migrations: `backend/database/migrations/2026_06_20_210234_create_momo_webhook_events_table.php`, `backend/database/migrations/2026_06_21_000001_create_momo_payments_table.php`
   - Dev tooling: `backend/app/Console/Commands/SimulateMoMoIpn.php` (artisan `momo:simulate-ipn` for local IPN testing)
   - Config: MoMo sandbox `services` config + `backend/.env.example` keys
   - Hardening: IPN sign/verify made symmetric so valid IPNs are accepted; public IPN route rate-limited
   - Tests: `backend/tests/Feature/Payment/MoMoIpnTest.php`, `backend/tests/Unit/MoMoServiceTest.php`
   - Frontend: MoMo wallet payment with in-app QR added to the booking flow; E2E `payment-webhook.spec.ts` touched
2. SECURITY / DEPENDENCY PATCHES — `guzzlehttp/guzzle` + `guzzlehttp/psr7` upgraded to patched releases; `undici` override bumped to `^7.28.0` (GHSA-vmh5-mc38-953g); `form-data` CRLF + `vite` `fs.deny` advisories patched.
3. TEST INFRA — the previously never-green E2E booking smoke suite was repaired (PR #16); pre-push now fails fast on a dead test DB and skips redundant backend tests.
4. FRONTEND DESIGN — "Modern Archivist" design system landed; `RoomCard` type scale matched to the design-system spec; frontend docs were reconciled to it.
5. NEW FINDING — `F-96`: local ParaTest `max_execution_time` stalls on Windows (logged, low).
6. PRODUCT — a SaaS validation pack for the VN homestay direct-booking wedge was added under docs.

These are the facts you must reflect. They are confirmed against the commit log and file tree at
generation time; re-confirm with `git` and `Read` before each edit.
</authoritative_context>

<governance_you_must_obey>
Read these BEFORE editing, in this order, and let the higher layer win on any conflict:
1. `CLAUDE.md` (root constitution — mission, domain truths, non-negotiable constraints, decision order, escalation).
2. `docs/agents/CONTRACT.md` — specifically "DoD: Documentation Changes".
3. `.claude/output-styles/docs-sync.md` — the MANDATORY output style for this task (post-code-change documentation reconciliation).
4. `docs/agents/ARCHITECTURE_FACTS.md` and `docs/DB_FACTS.md` — only to confirm any invariant you are tempted to restate.

Hard rules:
- OUTPUT STYLE: use `.claude/output-styles/docs-sync.md`. Every drift item gets exactly one tag: `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]`, or `[ACTION]`. Untagged factual claims are a defect.
- RECONCILE-ONLY ON METRICS: do NOT run `php artisan test`, `vitest`, `tsc`, Pint, PHPStan, or Psalm. Do not invent or "update" any test count, assertion count, or coverage number. Carry existing numbers forward verbatim and, where a doc presents them as current, tag them `[NEEDS RUNTIME CONFIRMATION]` at HEAD. The repo's discipline is "re-verify before the next merge to `main`," not during a docs pass.
- LANGUAGE: preserve all Vietnamese user-facing copy exactly. These READMEs are intentionally bilingual; do not translate, anglicize, or "clean up" Vietnamese sentences. Match each file's existing voice, heading style, and emoji usage.
- DOCS-ONLY: do not modify any file under `backend/`, `frontend/`, `.github/`, `docker-compose*`, or any `.php`/`.ts`/`.tsx` source. The seven READMEs that live under `backend/` and `frontend/` are documentation and ARE in scope — but only the `.md` files themselves.
- BOUNDED SCOPE: edit ONLY the files listed in <scope>. Anything else that is stale is logged as Remaining Drift (and, if it is a code defect, to `docs/FINDINGS_BACKLOG.md`) — never fixed inline.
- SIZE/SAFETY: stay under 25 changed files. Do not use `--no-verify`. If a required file is missing or a constraint is ambiguous, STOP and mark it `UNRESOLVED` rather than inventing a rule.
- MINIMAL EDITS: line-level edits only. Do not restructure or rewrite whole documents. Touch the smallest span that makes the doc true.
</governance_you_must_obey>

<scope>
Edit EXACTLY these files (the "principal" human-facing entry points) and nothing else:
1. `PROJECT_STATUS.md`            — the canonical single source of truth for status/metrics
2. `README.md`                    — root project README (bilingual)
3. `backend/README.md`            — Laravel backend entry point
4. `frontend/README.md`           — React frontend entry point
5. `frontend/README.dev.md`       — frontend dev guide (light touch; only if stale)
6. `README.dev.md`                — root dev guide / verification commands
7. `docs/README.md`               — documentation index
8. `skills/README.md`             — AI skills index (light touch; only if stale)
   (also acceptable if already current: `docs/agents/README.md` — verify; edit only if stale.)

OUT OF SCOPE — do not edit, only log as Remaining Drift if stale: every other README (vendor/, node_modules/, mcp/soleil-mcp, eval_runs, tests/performance, .claude/plugins, docs/backend/*, docs/imports, docs/ops, docs/archive, frontend/tests/e2e, docs/design/subagent-chat), all code, all other docs.
</scope>

<workflow>
Work in this exact order. Do not edit any file before Steps 1–2 are complete.

STEP 1 — Establish code truth (read-only).
  - `git rev-parse HEAD` and `git branch --show-current` to anchor the current commit.
  - Find the commit `PROJECT_STATUS.md` currently cites as its baseline (its header names it; at generation time `edadbf5`). Then run `git log --oneline <baseline>..HEAD` and `git diff --name-only <baseline>..HEAD` to derive the ACTUAL drift. Do not trust the snapshot in <authoritative_context> blindly — reconcile it against what `git` reports now.
  - For each material change (MoMo, dependency patches, E2E repair, design system, F-96), open at least one defining file with `Read` so your edits cite real symbols, routes, tables, and commands — not the summary in this prompt.
  - If the project is indexed by soleil-ai-review-engine and you have its tools, you MAY use `query`/`context` to confirm how MoMo wiring connects — but never let tool output override what `git`/`Read` show in source.

STEP 2 — Build the drift map (no edits yet).
  - For each in-scope file, Read it fully. Note its "Last Updated" line, its payment/feature sections, its metric lines, and any claim contradicted by Step 1.
  - Produce the "Docs Requiring Update" table (per the docs-sync style): `| Doc File | Section | Reason | Confidence |`. List only files you actually inspected against the code change.

STEP 3 — Apply minimal line-level edits, file by file.
  - Make the smallest edits that make each doc true. Use the per-file guidance in <per_file_guidance> as a checklist, but verify each item against Step 1 before writing it.
  - Refresh each file's "Last Updated" marker to today's date and, where the file cites a HEAD/commit, to the current HEAD.
  - Keep every carried-forward metric tagged `[NEEDS RUNTIME CONFIRMATION]` where the doc presents it as current.

STEP 4 — Self-verify against <definition_of_done>.

STEP 5 — Report using the docs-sync output style, then propose (do not auto-append unless the repo convention is to append) a `docs/WORKLOG.md` entry and a one-line PROJECT_STATUS "Status Note" addition describing this reconciliation wave.
</workflow>

<per_file_guidance>
Use as a checklist; confirm each line against Step 1 before applying. Tag every item.

PROJECT_STATUS.md
- Header: update `Last Updated` to today; confirm `Current Branch: dev`; set `Latest Commit` to current HEAD with a one-line summary; refresh the "NEEDS RUNTIME CONFIRMATION" banner so it names the post-`edadbf5` MoMo wave (not the old 2026-06-12/06-14 waves) as the unverified delta.
- Payment bootstrap: the "75% — Checkout UI still pending" line is now stale. MoMo wallet payment (start + in-app QR + IPN confirmation + idempotency ledger) shipped end-to-end. Reflect the new capability and adjust the progress note; do NOT fabricate a new percentage you cannot justify — describe what landed and mark the number `[INFERRED]` if you must move it.
- Q2 Roadmap + Completed Work: add a MoMo row/line (controller, service, IPN handler, 2 models, 2 migrations, dev command, tests, frontend QR). Add the dependency CVE patches (guzzle/psr7, undici, form-data/vite) and the E2E smoke-suite repair.
- Open Findings: add `F-96` (local ParaTest max_execution_time stalls on Windows — low).
- Do NOT change any test/assertion count. Keep the June 1 `6d7b75b` verified numbers verbatim and keep them flagged as the last verified suite run.

README.md (root, bilingual)
- "🚧 In Progress" lists "Stripe payment integration (Cashier bootstrapped, checkout UI pending)" — add MoMo wallet (in-app QR) as a shipped/active payment path; keep Vietnamese phrasing consistent.
- Core Features / Tech Stack: surface that payments now include both Stripe and MoMo (VN e-wallet). Preserve the existing emoji + bilingual section style.
- Do NOT change the Author/Contact/License block.

backend/README.md
- `Last Updated` → today. Key Features: the "✅ Stripe Payments" bullet is incomplete — add MoMo (IPN-confirmed, idempotency ledger).
- API Endpoints + Webhooks: the Webhooks table lists only `/api/webhooks/stripe`. Add the MoMo payment + IPN routes (confirm exact paths in `backend/routes/api/v1.php`).
- Testing/commands: add `php artisan momo:simulate-ipn` to the dev/testing command list. Mention `momo_payments` and `momo_webhook_events` tables where the schema/tables are summarized.

frontend/README.md
- `Last Updated` → today. Key Features: add MoMo wallet payment with in-app QR in the booking flow. Note the "Modern Archivist" design system if the README describes the design/UI layer. Dependency bumps (undici/form-data/axios) only belong here if the README enumerates them.

README.dev.md (root)
- Verification Commands / Environment Files: if it enumerates dev commands or `.env` keys, add `momo:simulate-ipn` and the MoMo sandbox env keys. Otherwise leave unchanged and log as "no drift".

frontend/README.dev.md, skills/README.md, docs/agents/README.md
- Light touch. Edit only if Step 2 shows a concrete contradiction with code truth. `skills/README.md` (prompt template, skill index, baseline DoD) and `docs/agents/README.md` (agent-framework file index) are likely already current — if so, record them under Remaining Drift as "inspected, no change needed."

docs/README.md
- `Last Updated` → today. "Key Features → Payments" currently says Stripe only — add MoMo. The "Current baselines (Mar 31 …)" line is stale; point it at `PROJECT_STATUS.md` as the single source and tag the inline numbers `[NEEDS RUNTIME CONFIRMATION]`. Add a Change-History line for this wave if that section exists.
</per_file_guidance>

<examples>
GOOD edit (minimal, sourced, correctly tagged):
  Before: `- **Payments**: Stripe Cashier integration, signed-webhook idempotency via stripe_refund_events UNIQUE`
  After:  `- **Payments**: Stripe Cashier (signed-webhook idempotency via stripe_refund_events UNIQUE) and MoMo wallet (in-app QR; IPN-confirmed via momo_payments + momo_webhook_events idempotency ledger)`
  Why: one line, names real tables from migrations, preserves surrounding style.

GOOD metric handling:
  Keep: `Backend tests: ✅ verified June 1, 2026 at 6d7b75b — 1697 passed / 9 skipped / 5438 assertions`
  Add (only if the doc implies this is "current"): `[NEEDS RUNTIME CONFIRMATION] — 77 commits incl. MoMo landed after this point; re-run before the next merge to main.`

BAD edits (do NOT do):
  - Rewriting the whole "Payments" or "Roadmap" section from scratch.
  - Writing "Backend tests: 1750 passed" — you did not run the suite; this is fabrication.
  - Translating a Vietnamese sentence to English or vice-versa.
  - Editing `backend/app/Services/MoMoService.php` or any source file to "make the docs match."
  - Touching `mcp/soleil-mcp/README.md` or any out-of-scope README.
</examples>

<output_format>
Respond using the `.claude/output-styles/docs-sync.md` structure, in this order:
1. **Code Truth Changed** — the real code changes (files/routes/tables/commands) you confirmed in Step 1, with commit refs.
2. **Docs Requiring Update** — table: `| Doc File | Section | Reason | Confidence |` (only inspected files).
3. **Updates Applied** — table: `| Doc File | Section | Old (summary) | New (summary) |` (line-level only).
4. **Remaining Drift** — table: `| Doc File | Section | Drift Description | Reason Not Fixed |`; valid reasons: requires runtime confirmation, out of scope, needs human decision, blocked by other change. If none: "All identified drift resolved."
5. **Proposed WORKLOG entry** — a dated `docs/WORKLOG.md` block in the existing append-only format (H2 date + bullets with commit hashes), for the human to commit.
Every factual line in the report carries exactly one of `[CONFIRMED] / [INFERRED] / [UNPROVEN] / [ACTION] / [NEEDS RUNTIME CONFIRMATION]`.
</output_format>

<definition_of_done>
- [ ] Step 1 re-derived the real `<baseline>..HEAD` delta from `git`; edits cite real symbols/routes/tables, not this prompt's summary.
- [ ] Only the in-scope files were changed; change count < 25; no code/CI/compose files touched.
- [ ] Every in-scope README + PROJECT_STATUS reflects MoMo payments; no doc still implies Stripe is the only payment path.
- [ ] No test/assertion/coverage number was invented or silently changed; carried-forward metrics are tagged `[NEEDS RUNTIME CONFIRMATION]` where presented as current.
- [ ] All Vietnamese user-facing copy preserved; per-file voice/emoji/heading style preserved.
- [ ] `F-96` reflected in PROJECT_STATUS open findings.
- [ ] Changed docs pass markdown lint (no unclosed code fences, valid tables) and at least 5 relative links spot-checked as non-broken (per DoD: Documentation Changes).
- [ ] Output uses the docs-sync style with correct evidence tags; a WORKLOG entry is proposed.
- [ ] Anything stale-but-out-of-scope is in Remaining Drift, not edited.
</definition_of_done>

<anti_patterns>
- Running the test suite or any static analyzer "to be safe." (Forbidden — reconcile only.)
- Fabricating or bumping metrics, percentages, or counts.
- Whole-section or whole-file rewrites; reflowing unchanged prose.
- Editing source, CI, or compose files; editing out-of-scope READMEs.
- Translating, summarizing away, or "tidying" Vietnamese copy.
- Trusting this prompt's drift snapshot over live `git`/`Read` output.
- Inventing a rule when a constraint is ambiguous instead of marking it `UNRESOLVED`.
</anti_patterns>

Begin with Step 1. Do not edit any file until you have established and printed the real code-truth
delta and the Docs Requiring Update table.
```

---

## How to run this

1. Open the repo on `dev` in Opus 4.8 (agentic mode, with `git` + file tools).
2. Paste everything inside the fenced ```text block above as your message.
3. Review the agent's **Docs Requiring Update** table before it edits; approve, then let it apply Steps 3–5.
4. Review the diff, run the repo's doc gates if desired, and commit with a `docs:` message. Re-run the suite before the next merge to `main` (the prompt deliberately does not).

## Why it is shaped this way (Anthropic prompt-engineering notes)

- **Role + bounded mission** up front; **XML section tags** so each instruction block is unambiguous and individually addressable.
- **Code truth is re-derived by the agent**, not asserted — the embedded drift list is explicitly a *snapshot* to guard against HEAD advancing.
- **Evidence tagging + the repo's own `docs-sync` output style** are mandated, matching `CLAUDE.md`'s output-style policy.
- **Reconcile-only / no-suite-run** posture removes nondeterminism and matches the repo's "verify before merge to `main`" discipline.
- **Positive and negative examples** anchor the edit granularity; the **DoD checklist** gives the agent a verifiable finish line.
