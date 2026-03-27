# SOLEIL Engine — consumer guide for this repo

## What this is

The soleil engine (`soleil-engine-cli`) is an external code-intelligence tool.
It is not part of the app runtime or deployed artifacts. This repo uses it to
navigate the booking flows, auth chains, and RBAC structure via knowledge-graph
queries. It is a developer workflow aid — think of it as a smart index over the
codebase that answers "what is this class", "what calls it", and "show me its source".

## Prerequisites

- Node.js 22+ (validated with v22.15.0)
- Access to the engine tarball (npm registry publish not yet done as of 2026-03-27)

## Current validated invocation path

The engine package is not on npm registry yet. Install from the local tarball
produced by the engine repo:

```bash
# Discover the tarball (adjust path if engine repo is elsewhere):
find ~ -name "soleil-engine-cli-*.tgz" 2>/dev/null | head -3
# Typical location: ~/ai-tools/soleil-ai-review-engine/soleil-ai-review-engine/soleil-engine-cli-1.4.0.tgz

# Install as dev dependency in this repo:
npm install --save-dev /path/to/soleil-engine-cli-1.4.0.tgz
```

Binary lands at `./node_modules/.bin/soleil`.

A wrapper script at `scripts/soleil.sh` handles the `--repo soleil-hostel` flag
automatically — use that instead of the raw binary for tool commands.

## Verified working commands

```bash
# Confirm engine is available
./node_modules/.bin/soleil --help

# Re-index this repo (run after significant code changes)
./node_modules/.bin/soleil analyze . --skills

# Use the wrapper for all query/context/impact commands:

# Auth / RBAC context — see source + method membership
./scripts/soleil.sh context EnsureUserHasRole

# Admin booking controller — full source with Gate::authorize + overlap logic
./scripts/soleil.sh context AdminBookingController --content

# Booking flow query — surfaces related test files and symbols
./scripts/soleil.sh query "booking creation and overlap prevention"

# Overlap/double-booking test coverage
./scripts/soleil.sh query "double booking prevention overlap check"

# Impact analysis (note: call graph edges sparse for PHP — see Known limitations)
./scripts/soleil.sh impact BookingService

# Raw graph query via Cypher
./scripts/soleil.sh cypher "MATCH (a)-[r]->(b) WHERE a.filePath CONTAINS 'Controllers' RETURN a.name, b.name LIMIT 10"
```

## Multiple repos indexed — `--repo` flag is required

If the engine has indexed more than one repo (e.g., both `soleil-ai-review-engine`
and `soleil-hostel`), every `context`, `query`, `impact`, and `cypher` command
requires `--repo soleil-hostel`. The wrapper script (`scripts/soleil.sh`) injects
this automatically.

Without the flag you will see:
```
Error: Multiple repositories indexed. Specify which one with the "repo" parameter.
```

## Known limitations today

- **npm registry publish**: NOT YET DONE — install from tarball required (see above).
- **`npx soleil-ai-review-engine`**: NOT SUPPORTED — package-name resolution fails.
  `npx soleil-engine-cli` is the correct alias once published.
- **`detect_changes` via CLI**: BLOCKED — not exposed as a CLI subcommand.
  Only available via the engine's HTTP server (`soleil serve`, port 4747).
  Server was not running during consumer validation session.
- **PHP call graph edges (impact/callers)**: PARTIAL — symbol discovery works,
  but `impact` returns 0 callers for PHP classes. Laravel DI + facades are not
  statically resolved to call-graph edges. Use `context --content` to retrieve
  source; use `query` to find related test files. Blocker type: engine-gap.
- **`--depth` flag on `context`**: NOT SUPPORTED — use `--content` instead for
  source retrieval.
- **Interactive setup**: requires UI or `--non-interactive` if prompts appear.

## What not to assume

- `npx soleil-ai-review-engine` is not the same as `npx soleil-engine-cli` — the
  former requires a separate npm alias package not yet published.
- The engine is not vendored into this app's runtime or production dependencies.
- `impact` returning risk=LOW with 0 callers does not mean a symbol is safe to
  change — it means PHP call edges are not fully resolved. Read the source.
- Consumer validation ≠ platform architecture ownership.
