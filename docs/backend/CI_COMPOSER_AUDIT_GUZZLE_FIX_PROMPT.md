# CI Fix — `composer-audit` guzzle/psr7 CVEs — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. Self-contained, grounded in the
> current Soleil Hostel tree. This is a CI/security failure → use the **RCA output style**
> (`.claude/output-styles/rca.md`). The fix is a SCOPED dependency upgrade, not a suppression.

````text
<role>
You are a senior Laravel / PHP release-security engineer executing inside the Soleil Hostel monorepo. You
resolve dependency CVEs by upgrading to patched releases (never by ignoring advisories), you keep the lockfile
diff minimal, and you treat CLAUDE.md + its decision order as binding. Produce an RCA, prove the fix against
the EXACT CI command, and stop at the human-review boundary.
</role>

<context>
The `composer-audit` CI job is failing. `.github/workflows/tests.yml` (job `composer-audit`, step "Audit
Composer dependencies") runs, against the COMMITTED lockfile:

    composer audit --locked --no-interaction --format=table

Its exit-code contract (lines ~622–648): 0 = clean → pass; 100 = transport/runtime (retry); **any other
non-zero = real advisory finding → hard fail**. The job is `continue-on-error: false` (blocking) and has NO
ignore/allowlist mechanism for Composer (the NPM job has one; the Composer job deliberately does not). So the
ONLY way to green is to make the advisories go away — i.e. upgrade.

The run reported 3 advisories across 2 packages (all medium, reported 2026-06-18):
  - guzzlehttp/guzzle < 7.12.1 → CVE-2026-55767 (dot-only cookie domains match all hosts);
                                  CVE-2026-55568 (silent HTTPS proxy downgrade to cleartext)
  - guzzlehttp/psr7   < 2.12.1 → CVE-2026-55766 (CRLF injection in HTTP start-line serialization)

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → docs/HOOKS.md → this prompt. Unresolvable
conflict → `UNRESOLVED`.
</context>

<root_cause>
`[CONFIRMED]` `backend/composer.lock` pins `guzzlehttp/guzzle` **7.11.0** and `guzzlehttp/psr7` **2.11.0**, both
below the patched releases. These are TRANSITIVE deps (pulled by `laravel/framework`, `laravel/cashier`/Stripe,
sentry, and the Laravel HTTP client the MoMo path uses) — not direct requires in `composer.json`.
`[CONFIRMED]` The advisory DB was updated 2026-06-18, so a previously-green lock started failing `composer audit`
with no code change. `[CONFIRMED]` `laravel/framework` requires `guzzlehttp/guzzle: ^7.8.2` (and psr7 `^2.x`), so
the patched 7.12.1 / 2.12.1 are WITHIN the existing constraints → reachable by a scoped update with no
`composer.json` constraint change.
</root_cause>

<task>
1. RCA the failure (above) and confirm it on this machine.
2. SCOPED upgrade: bring `guzzlehttp/guzzle` to ≥ 7.12.1 and `guzzlehttp/psr7` to ≥ 2.12.1, touching only those
   packages and their required transitives — NOT a full `composer update`.
3. Prove `composer audit --locked --format=table` exits 0 (the exact CI command) and the suite still passes.
4. Commit the lockfile change. Do not push; do not merge. Report and wait.
</task>

<fix_plan>
Run from `backend/`:

    # Confirm the diagnosis
    composer audit --locked --format=table            # see the 3 advisories (reproduce CI)
    composer show guzzlehttp/guzzle guzzlehttp/psr7    # expect 7.11.0 / 2.11.0
    composer why guzzlehttp/guzzle                     # confirm transitive (laravel/framework, cashier, ...)

    # Scoped upgrade — ONLY these two + their needed transitives (-W). NEVER a bare `composer update`.
    composer update guzzlehttp/guzzle guzzlehttp/psr7 -W

    # If a guzzle sibling (promises / uri-template / psr/http-message) still pins an old version, widen
    # narrowly to the family rather than everything:
    #   composer update "guzzlehttp/*" -W

`[INFERRED]` No `composer.json` edit is required (constraints are `^` ranges that already admit the patched
versions). If — and only if — a direct constraint actually blocks the patched version, STOP and report the exact
blocker before changing any constraint. Do not add a `config.platform` change; the existing 8.3.30 platform pin is
unrelated to these advisories.
</fix_plan>

<security_notes>
These are real, payment-path-relevant medium CVEs — the upgrade is the security-correct action, not box-ticking:
- The MoMo adapter (createPayment) and Stripe SDK both make outbound HTTPS calls through Guzzle/psr7. CVE-2026-55568
  (silent HTTPS→cleartext proxy downgrade) and CVE-2026-55766 (psr7 CRLF injection in the request start-line) bear
  directly on outbound payment traffic integrity/confidentiality.
- Do NOT suppress: there is no Composer audit ignore-list in CI, and adding one (or `--no-verify` on the commit, or
  `SKIP_HOOKS`) to dodge the gate is prohibited here — it would fail the gate open on a live payment dependency.
  The fix is the version bump, full stop.
</security_notes>

<verification>
Mirror CI exactly, then prove no regression:

    composer audit --locked --no-interaction --format=table   # MUST exit 0 (the CI gate command)
    composer show guzzlehttp/guzzle guzzlehttp/psr7            # ≥ 7.12.1 / ≥ 2.12.1
    composer validate --strict                                 # composer.json/lock still consistent

    composer install --no-interaction --prefer-dist           # sync vendor to the new lock
    composer test                                              # FULL suite green (Guzzle backs Stripe + HTTP client)
    # If the test DB is needed: docker compose up -d db && php scripts/check-test-db.php first.

    git --no-pager diff --stat                                 # ideally only composer.lock (and composer.json IFF a constraint truly had to change)

Cross-check that the lock diff is confined to the guzzle family + any strictly-required transitive — not a
wholesale lock churn. If `composer update -W` rewrote dozens of unrelated packages, you used too broad a command;
reset and redo with the named packages only.
</verification>

<commit_spec>
- Branch: `[ACTION]` decide with me. RECOMMENDED: a dedicated `fix/guzzle-psr7-advisories` off `dev` (this is a
  repo-wide dependency-hygiene fix, independent of the MoMo feature). If the goal is specifically to unblock the
  `feature/momo-sandbox-payment` CI, the same lock bump can be applied there (or rebase the feature branch onto the
  fix). State which you want before I commit.
- Stage ONLY `backend/composer.lock` (and `backend/composer.json` IFF a constraint genuinely changed). Use explicit
  `git add <path>` — never `git add -A`. Do NOT stage the `docs/backend/*_PROMPT.md` planning scaffolding.
- Message (Conventional Commits; `fix` + allowed scope `backend`):
    fix(backend): upgrade guzzlehttp/guzzle & psr7 to patched releases
  Optional body: list CVE-2026-55767, CVE-2026-55568 (guzzle ≥7.12.1) and CVE-2026-55766 (psr7 ≥2.12.1).
  NO `Co-Authored-By` / tool-attribution trailer. NO `--no-verify` / `SKIP_HOOKS`.
- After commit: the soleil index may auto-reindex (PostToolUse hook); if not, run `npx soleil-engine-cli analyze`
  (add `--embeddings` if `.soleil-ai-review-engine/meta.json` `stats.embeddings` > 0).
</commit_spec>

<output_format>
Use the RCA output style `.claude/output-styles/rca.md` (CLAUDE.md routes CI/build/security failures there).
Tag every finding `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]`, `[ACTION]`. End with: the before/after
`composer audit --locked` output (advisories → none), the `composer show` versions, the `composer test` summary,
and the `git diff --stat` (scope = composer.lock only).
</output_format>

<stop_conditions>
Stop and confirm with me before: running a bare `composer update` (whole-lock churn — forbidden; use the scoped
named update); editing any `composer.json` constraint (only if a real blocker is found — report it first); adding a
Composer audit ignore-list / `--no-verify` / `SKIP_HOOKS` to dodge the gate; the upgrade pulling a MAJOR version of
any package (these should be patch bumps — a major bump means the constraint resolution went wrong, stop); pushing;
or merging to `dev`/`main`. Do NOT commit until `composer audit --locked` is green AND `composer test` passes.
Leave the change uncommitted-then-committed-locally only; show me the diff + audit/test output and wait for go.
</stop_conditions>
````
