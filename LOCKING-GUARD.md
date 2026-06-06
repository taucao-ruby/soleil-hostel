# Locking Guard — booking write-path lock coverage (T-6)

A CI gate that **fails closed** if any service performing booking mutations is
missing pessimistic-lock protection. It defends the domain invariant in
[`.agent/rules/booking-integrity.md`](.agent/rules/booking-integrity.md) and
`CLAUDE.md`: *booking-critical writes keep pessimistic locking.*

| Artifact | Role |
|----------|------|
| [`.agent/scripts/check-locking-coverage.sh`](.agent/scripts/check-locking-coverage.sh) | Guard logic (bash). |
| [`booking-write-services.yaml`](booking-write-services.yaml) | Discovery manifest — the declared set of booking write services (source of truth). |
| [`.locking-guard.sha256`](.locking-guard.sha256) | SHA-256 pin of the script (tamper seal). |
| [`.github/workflows/locking-guard.yml`](.github/workflows/locking-guard.yml) | The CI gate. |
| `locking-report.json` | Machine-readable result (generated; git-ignored; CI artifact). |

> **Why a manifest and not Kubernetes?** The original T-6 brief assumed a K8s +
> Java/Spring architecture (`kubectl ... -l booking-eng/write-path=true`,
> `@WithLock` / `@Transactional(lock=PESSIMISTIC_WRITE)`). This repository is a
> **Laravel monolith** with no cluster and no annotations — locking is the PHP
> call `->lockForUpdate()` / the Eloquent `withLock()` scope. The checked-in
> manifest is the declarative, fail-closed analogue of "the label must be
> present on every Deployment".

---

## Exit code reference

| Code | Name | Meaning | CI behaviour |
|------|------|---------|--------------|
| `0` | PASS | Every discovered write path has a lock primitive. | Pipeline continues; report uploaded. |
| `1` | FAIL | One or more **unlocked** write paths. | **Blocks deploy.** PR comment lists `UNLOCKED_PATH` lines. |
| `2` | INFRA | Discovery timeout, missing manifest, or a declared file is missing (manifest drift). | **Blocks deploy + pages on-call.** |
| `3` | EMPTY | Discovery returned **zero** services. Treated as misconfiguration, never a pass. | **Blocks deploy + pages on-call (high severity).** |

CI reads **both** the exit code (authoritative) and `locking-report.json`
(`{ "passed": bool, "services": [{ "name", "writePaths": [...], "locked": bool }] }`)
for reporting.

---

## Running it locally

```bash
# Full guard run (what CI runs)
./.agent/scripts/check-locking-coverage.sh --auto-discover

# Just list the discovered services (exit 0)
./.agent/scripts/check-locking-coverage.sh --list-services

# Help / exit-code contract
./.agent/scripts/check-locking-coverage.sh --help
```

Requires `bash` + coreutils (`awk`, `grep`, `sed`, `sha256sum`). Works in Git
Bash on Windows and on Ubuntu CI. Overrides: `LOCKING_GUARD_MANIFEST`,
`LOCKING_GUARD_REPORT`.

---

## Discovery method

`discover_write_services()` parses [`booking-write-services.yaml`](booking-write-services.yaml)
— a constrained YAML subset (one service per line as a flow map
`- { name: X, file: Y, writePaths: a b c }`). There are **no hardcoded service
names** in the script; the manifest is the only source. For each declared
service the guard greps its file for `(lockForUpdate|withLock)\(` and emits
`LOCKED_PATH` / `UNLOCKED_PATH` lines (all results collected before exit — never
short-circuits on the first failure).

> **Verification depth.** The guard proves a lock *primitive is present* in each
> declared write service. *Method-level* correctness (the right rows locked, in
> the right transaction) is proven by the test suite —
> `tests/Feature/Booking/PdoLockBlockingTest.php`,
> `tests/Feature/Booking/ConcurrentBookingTest.php`,
> `tests/Feature/CreateBookingConcurrencyTest.php`. This split keeps the static
> guard's false-positive rate at zero.

---

## Delegated locking (`lockedVia`)

Some booking write **entry points** hold no in-file lock and instead route the
mutation through another enforced service. The canonical case is
`StripePaymentIntentSucceededHandler::applyToBooking()` (the live Stripe webhook
+ the reconcile reaper): it reads the booking unlocked, then performs the
`CONFIRMED` transition via `BookingService::markPaidAndConfirm()`, where the
`lockForUpdate()` + status re-check actually serialize concurrent callers.

Declaring such an entry point with a normal in-file check would be a **false
positive** (the file truthfully contains no lock). Instead, declare the
delegation:

```yaml
- { name: StripePaymentIntentSucceededHandler,
    file: backend/app/Services/Payment/StripePaymentIntentSucceededHandler.php,
    writePaths: applyToBooking,
    lockedVia: BookingService::markPaidAndConfirm }
```

The guard marks it `locked` **only if both** hold:

1. the file actually calls `markPaidAndConfirm(` — so the delegation is real,
   not just declared (catches a refactor that introduces a direct write); and
2. `BookingService` is itself in the discovered set **and** in-file locked
   (catches the delegate losing its lock).

Either failing → `UNLOCKED_PATH` + `delegation_unverified(...)` + exit 1.

> **Scope note.** Delegation verifies the *state-transition* write is locked
> through the delegate. The handler's `AlreadyConfirmed` branch also does a
> direct, lock-free `forceFill()->save()` of **payment-reconciliation fields**
> on an already-confirmed booking (not a state transition, not overlap-
> introducing), which is intentionally outside the lock invariant. If that ever
> grows into a state-machine write, give the handler its own in-file lock.

---

## Maintaining coverage

**Adding a new booking write service** (the manifest is the K8s-label analogue —
keep it in sync with reality):

1. Add an entry to `booking-write-services.yaml`:
   ```yaml
   - { name: MyNewBookingService, file: backend/app/Services/MyNewBookingService.php, writePaths: doThing }
   ```
2. Ensure that file calls `lockForUpdate()` / `withLock()` inside its write
   transaction.
3. The manifest is CODEOWNERS-protected → the PR needs owner approval.
4. Run the guard locally to confirm PASS, then **re-pin the hash if you also
   changed the script** (see below).

**Delegating entry point?** If the new service performs no in-file lock but
routes its booking mutation through an already-enforced locked service, declare
`lockedVia: DelegateService::delegateMethod` instead of expecting an in-file
lock (see "Delegated locking" above).

**Review candidates.** The "REVIEW CANDIDATES" block at the foot of
`booking-write-services.yaml` is the holding area for paths a human has not yet
triaged. It is currently empty — `StripePaymentIntentSucceededHandler` was
triaged and is now enforced via `lockedVia`. When a new booking-mutating path
appears, add it there first, confirm how it locks, then promote it into
`services:` (with an in-file lock or a `lockedVia` delegation).

---

## Changing the guard script (re-pinning the hash)

The script is hash-pinned. After any **legitimate** edit to
`check-locking-coverage.sh`, regenerate the pin or the next CI run fails with
`GUARD_TAMPERED`:

```bash
sha256sum .agent/scripts/check-locking-coverage.sh | awk '{print $1}' \
  | { read h; printf '%s  %s\n' "$h" ".agent/scripts/check-locking-coverage.sh" > .locking-guard.sha256; }

# verify it round-trips exactly as CI will:
sha256sum -c .locking-guard.sha256
```

Both the script and `.locking-guard.sha256` are CODEOWNERS-protected, so a
script change + re-pin lands in one owner-approved PR. `*.sh` is `eol=lf`
(`.gitattributes`), so the pin matches across Windows and Linux checkouts.

---

## CI wiring

The gate ([`locking-guard.yml`](.github/workflows/locking-guard.yml)) triggers on
every PR to `main`/`master` and every push to those branches — i.e. **after**
the unit-test gate on the same change and **before** the tag-driven deploy
([`deploy.yml`](.github/workflows/deploy.yml)) can ship.

To make it actually block a deploy, add it as a **required status check** in
branch protection:

1. Repo → Settings → Branches → branch protection rule for `main`.
2. *Require status checks to pass before merging* → add
   **`Booking write-path lock coverage`**.

There is **no `continue-on-error`** and **no admin skip** in the job. Exit codes
`1`, `2`, and `3` all fail the pipeline; `2` and `3` additionally page on-call.

---

## Break-glass — there is NO bypass

This guard has **no override flag, env var, or admin skip.** A blocked deploy is
not unblocked by skipping the check — it is unblocked by **fixing the lock in
code**.

If a deploy is blocked:

1. **Read the failure.** Exit `1` → the PR comment / `UNLOCKED_PATH` lines name
   the service and write paths. Exit `2`/`3` → manifest/infra problem, on-call
   is paged.
2. **Fix the cause:**
   - Exit `1` (unlocked): add `lockForUpdate()` / `withLock()` inside the write
     transaction of the named service. Re-run the guard.
   - Exit `3` (empty): `booking-write-services.yaml` lost its entries — restore
     them (check git history / the wiping PR). This is a high-severity incident.
   - Exit `2` (drift/infra): a declared `file:` path moved or was deleted —
     update the manifest to match, or restore the file.
3. **Genuine false positive?** Raise it with the manifest/guard owner
   (`@soleil-hostel/maintainers` in [CODEOWNERS](.github/CODEOWNERS); replace
   with a security team when one exists). The remedy is to correct the guard or
   manifest *via an owner-approved PR + hash re-pin* — never to disable the gate.

Escalation path: **fix in code → re-run guard → if still blocked, owner review →
owner-approved guard/manifest change.** The gate stays on the whole time.
