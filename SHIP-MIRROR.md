# Ship CI Mirror & Delta gate (T-5)

`scripts/ship.sh` makes **"READY TO SHIP" mean the same gates as CI** — or it
tells you, explicitly, exactly which CI gates it did not run. This repo's CI runs
~16 jobs directly on `ubuntu-latest` (there is **no container image** to pull and
mirror), so ship.sh is a *hybrid* mirror: it runs every CI gate it can run
locally at full CI strength and prints an explicit local-vs-CI delta for the rest.

| Artifact | Role |
|----------|------|
| [scripts/ship.sh](scripts/ship.sh) | Orchestrator — two modes + the READY TO SHIP gate. Zero inline gate logic. |
| [ci/gates/manifest.tsv](ci/gates/manifest.tsv) | **Single source**: every CI gate, its provenance, and how/whether ship.sh mirrors it. |
| [ci/gates/](ci/gates/)`*.sh` | One gate script per locally-runnable CI gate. No args; env-driven; `GATE_PASS`/`GATE_FAIL`. |
| [.ship-hashes.sha256](.ship-hashes.sha256) | Tamper seal over ship.sh + manifest + gate scripts. |
| [scripts/update-ship-hashes.sh](scripts/update-ship-hashes.sh) | `--confirm`-gated re-seal after a legitimate gate change. |

> **Why not Docker-image-by-digest?** T-5's brief assumed CI runs gates inside a
> declared `container.image` to pull by digest. Soleil's workflows have no such
> image — every `image:` is a postgres/redis **service**; gates run on the runner
> with `setup-php`/`setup-node`. The execution-environment contract (pinned tool
> versions) is captured in the manifest and enforced by `--mode=delta` instead.

---

## Modes & exit codes

```bash
./scripts/ship.sh                 # mirror (default): run local gates, print delta
./scripts/ship.sh --mode=mirror   # same
./scripts/ship.sh --mode=delta    # diagnostic only: JSON env diff, runs NO gates
./scripts/ship.sh --help
```

**Mirror** — runs each runnable gate; collects all results (never short-circuits).
- `0` — all runnable gates passed. Banner states **full** mirror (`✅ READY TO SHIP`,
  zero delta) vs **partial** (`⚠️ LOCAL GATES PASSED` + the list of CI gates not run).
- `1` — a runnable gate failed, or the integrity seal mismatched (`SHIP_TAMPERED`).

**Delta** — compares local env vs CI's declared contract; runs no gates. JSON to
stdout (`{status, commit, differences[]}`), human summary to stderr.
- `0` compliant · `1` blocking differences · `2` warnings only.

---

## What gets mirrored vs reported as delta

`local_mode` in the manifest decides per gate:

| mode | behaviour |
|------|-----------|
| `run` | always runs (bash/sh only) |
| `needs-tool` | runs iff `tool` is on PATH; else reported in the delta (`tool-missing`) |
| `needs-db` | runs iff the test DB is reachable; else delta (`db-down`) — `docker compose up -d db` |
| `delta` | never run locally (heavy/infra: e2e, stress, trivy, contract-lint…); always in the delta |

Gates that close the historic gap (previously absent/weaker in ship.sh): PHPStan,
Psalm, Pint, `php -l`, composer-audit, npm-audit, gitleaks, ESLint, production
build, plus the **95% backend** and **frontend coverage floors**.

---

## READY TO SHIP certification

Commit with `[READY TO SHIP]` in the message (case-insensitive). On the next
`ship.sh` run this **forces** the strict flow regardless of flags — there is no
skip flag:

1. **delta precheck** — abort (`SHIP_BLOCKED`) if the local env has blocking divergence.
2. **full local mirror** — every runnable gate must pass.
3. **CI verification** — `gh` confirms every check-run is green for *this exact commit*
   (this covers the gates that can't run locally). Fails closed: if `gh`/`jq` is
   missing or unauthenticated, or CI is not green/finished, it **blocks**.
4. Only then: `READY_TO_SHIP_VERIFIED commit=<sha>`.

Local mirror (what you can run) + CI-green-for-this-SHA (what you can't) together
imply the full CI guarantee.

---

## Changing a gate (re-sealing)

After any legitimate edit to `ship.sh`, the manifest, or a `ci/gates/*.sh`,
re-pin or the next run fails `SHIP_TAMPERED`:

```bash
sh scripts/update-ship-hashes.sh --confirm
sha256sum -c .ship-hashes.sha256        # verify
git commit -m 'chore: update ship gate hashes [security-review-required]'
```

`scripts/ship.sh`, `ci/gates/`, and `.ship-hashes.sha256` are CODEOWNERS-protected,
so a gate change + re-seal lands in one owner-approved PR.

**Adding a CI gate:** add a row to `ci/gates/manifest.tsv` (provenance in the
`source` column) and, if locally runnable, a `ci/gates/<id>.sh` that runs the
exact CI command. Then re-seal.

---

## Break-glass — there is NO bypass

A blocked ship is not unblocked by skipping a gate. The fixes are real:

- **Mirror gate failed** → fix the code so the gate passes (run the gate to see why).
- **Gate reported as delta (`tool-missing`/`db-down`)** → install the tool, or
  `docker compose up -d db`, then re-run so it actually mirrors. Do not pretend it ran.
- **READY TO SHIP blocked on CI** → push, wait for CI to go green for this commit; the
  cert requires it. If CI is genuinely red, fix the failure — never override.
- **`SHIP_TAMPERED`** → a gate/manifest changed without a re-seal. If intentional,
  re-seal via the audited `--confirm` flow above (owner-approved). If not, investigate.

The gate set, the manifest, and the seal are owner-owned; weakening them is a
reviewed change, never a runtime flag. Secret values are never logged — delta mode
reports presence only.
