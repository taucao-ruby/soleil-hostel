# Mutation Testing — Booking Core (D-1)

Infection runs nightly against the booking-integrity core to measure how much
of the domain logic the test suite actually *kills*, not merely covers. Line
coverage proves execution; the Mutation Score Indicator (MSI) proves the
assertions would notice a behavioral change in the FSM guards, the half-open
overlap predicate, and the refund math.

## Scope

Exactly the 9 booking-integrity files, pinned by `--filter` in the
`composer mutation` / `composer mutation:phpdbg` scripts (canonical list also
documented in `backend/infection.json5`):

| Surface | Files |
|---|---|
| FSM | `app/Enums/BookingStatus.php`, `app/Enums/DepositStatus.php`, `app/Models/Booking.php` (`transitionTo`), `app/Models/Deposit.php` |
| Overlap | `app/Models/Booking.php` (`scopeOverlappingBookings`), `app/Services/CreateBookingService.php`, `app/Services/RoomAvailabilityService.php`, `app/Repositories/EloquentBookingRepository.php` |
| Refund | `app/Models/Booking.php` (refund math), `app/Booking/CancellationPolicy.php`, `app/Models/Deposit.php` (`computeRefundAmount`), `app/Services/CancellationService.php` |

Out-of-scope methods inside in-scope files (relations, cache plumbing, UI
helpers) are excluded via `mutators.global-ignore` in `infection.json5` so the
MSI reflects only booking-integrity logic. **Keep the three lists in sync**:
`infection.json5` comment, `composer.json` scripts (both variants), and this
table.

## Nightly gate

Workflow: `.github/workflows/mutation-nightly.yml` — 02:30 UTC, checks out
`dev`, Postgres 16 + Redis services, xdebug coverage, then `composer mutation`.
The schedule fires from the workflow file on the **default branch**, so the
job starts running only after this file is promoted `dev -> main`.

Floor semantics (measure-then-ratchet — same philosophy as the suggest-only
frontend coverage ratchet; **no bot commits**, a human moves the floor):

| State | Behavior |
|---|---|
| `MUTATION_MIN_MSI` repo variable unset | Report-only. Summary records the MSI and suggests a floor. Never fails on score. |
| `MUTATION_MIN_MSI` set | Enforced via `--min-msi`. Job fails below the floor. |
| `MUTATION_MIN_COVERED_MSI` set (optional) | Additionally enforced via `--min-covered-msi`. |

A `workflow_dispatch` input `min_msi` overrides the variable for one run
(useful to rehearse a ratchet before committing to it).

### Ratchet procedure

1. Let the report-only run produce a baseline for ≥ 3 consecutive nights.
2. Set `MUTATION_MIN_MSI` (repo Settings → Secrets and variables → Variables)
   to `min(last 3 nights) − 2` points — the margin absorbs mutant-count jitter
   from timeouts on busy runners.
3. Ratchet upward only. When killing escaped mutants raises the measured MSI,
   bump the variable; never lower it without a written rationale in
   `docs/FINDINGS_BACKLOG.md`.

### Triage of escaped mutants

Download the `infection-report-*` artifact; `infection.html` lists every
escaped mutant with its diff. For each one, either:

- **Add the missing assertion** (preferred — an escaped mutant in
  `canTransitionTo`, the overlap operators, or refund math is a real test gap), or
- **Exclude the method** via `global-ignore` when it is genuinely outside the
  booking-integrity contract — config-only change, with a comment.

## Running locally

CI uses xdebug. Local Windows has no xdebug/pcov and no `ext-pcntl`, so use
the phpdbg variant (single-threaded — expect a long run; the per-mutant
timeout is 90s for Postgres `FOR UPDATE` tests):

```bash
docker compose up -d db redis
cd backend
composer mutation:phpdbg
```

On Linux/macOS with xdebug or pcov: `composer mutation`.

Reports land in `backend/build/infection/` (text, html, summary, per-mutator
markdown, and `infection.json` — the file the CI summary parses).

## Why this is not a PR gate

A full pass costs tens of minutes even parallelized — it would dominate the PR
loop for changes that mostly do not touch the booking core. `ship.sh` (T-5)
deliberately does **not** mirror this job; it mirrors the PR-blocking gates
only. Nightly cadence still bounds the exposure window of an assertion-quality
regression to one day, which matches the risk profile of the debt item (D-1)
this implements.
