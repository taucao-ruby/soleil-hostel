# Engine use cases validated on this repo

Validated: 2026-03-27. Engine version: soleil-engine-cli 1.4.0.
All commands below were executed in this session and produced the output shown.

---

## Auth / RBAC — symbol discovery and source retrieval

```bash
./scripts/soleil.sh context EnsureUserHasRole
```

What it surfaces: symbol found at `backend/app/Http/Middleware/EnsureUserHasRole.php`
lines 24–55. No incoming/outgoing call edges (engine-gap for PHP), but file path
and line range are accurate — jump to source from the result.

Useful for: quickly locating where role-enforcement middleware lives without
grepping, especially when reviewing a PR that touches auth or adds a new route.

---

## Admin booking controller — full source via `--content`

```bash
./scripts/soleil.sh context AdminBookingController --content
```

What it surfaces: full PHP source of `AdminBookingController` (lines 20–241),
including `Gate::authorize('view-all-bookings')`, `Gate::authorize('admin')`,
`hasOverlappingBookings(...)` call before restore, and `auditService->log(...)`.
All business-critical invariants are visible inline.

Useful for: understanding what the admin booking controller actually enforces
before modifying it, without opening the file manually. Captures both the RBAC
gates and the overlap-check logic in one call.

---

## Booking / overlap — test coverage discovery via query

```bash
./scripts/soleil.sh query "double booking prevention overlap check"
```

What it surfaces: `CreateBookingConcurrencyTest.php` and `ConcurrentBookingTest.php`
— the two test files that cover the double-booking invariant — surfaced as
top definitions even though process-level flows returned empty.

Useful for: finding which tests cover a domain concept before changing booking
logic. Run this before touching any overlap or availability code to confirm which
test files must pass.

---

## Raw graph queries via Cypher

```bash
./scripts/soleil.sh cypher "MATCH (a)-[r]->(b) WHERE a.filePath CONTAINS 'Controllers' RETURN a.name, b.name LIMIT 10"
```

What it surfaces: file-level structural edges from controller symbols — confirms
4,391 nodes and 11,393 edges indexed. Useful for custom graph exploration when
the higher-level commands don't expose what you need.

Useful for: debugging why `context` returns empty edges, or writing custom graph
queries to trace namespaces and containment relationships.

---

## Gaps observed this session

- **impact callers for PHP classes**: PARTIAL — `impact BookingService` returns 0
  callers, risk=LOW. PHP method-call edges not resolved. Blocker type: engine-gap.
  Workaround: use `context --content` to read the source; grep for callers manually.

- **`detect_changes` via CLI**: BLOCKED — no CLI subcommand exists. Only accessible
  via engine HTTP server (`soleil serve` on port 4747), which was not running.
  Blocker type: protocol-gap (feature exists in MCP/server path, not CLI path).

- **process-level execution flows**: PARTIAL — `query` returns `"processes": []`
  for all booking and auth queries. Text/embedding search still surfaces relevant
  definitions (test files, migration files). Useful as semantic grep, less useful
  as execution-flow tracer. Blocker type: engine-gap (PHP execution flow indexing).

- **`--depth` flag on `context`**: BLOCKED — option not recognized by CLI v1.4.0.
  Use `--content` instead.

---

## What to try after npm publish

Once `soleil-engine-cli` is published to npm registry:

```bash
npm install -g soleil-engine-cli   # not available yet as of 2026-03-27
npx soleil-engine-cli analyze . --skills
npx soleil-engine-cli context AdminBookingController --repo soleil-hostel --content
```

The `--repo soleil-hostel` flag will still be required if multiple repos are indexed.
