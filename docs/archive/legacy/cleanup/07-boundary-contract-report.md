---
schema_version: 1.0
produced_by_batch: B9A
phase: Phase C
date: 2026-03-22
input_artifacts:
  - docs/cleanup/01-classification-matrix.md
  - foundation/00-master-contract.md
  - foundation/00-output-schemas.md
authority_order_applied: false
unresolved_count: 3
---

# Boundary Contract Report — Batch 9A

> Generated: 2026-03-22 | Branch: dev

## Observed reality

MCP server at mcp/soleil-mcp/ provides 5 tools (repo_overview, read_file, search, run_verify, project_invariants). All read-only. Policy defined in mcp/soleil-mcp/policy.json. User-facing docs at docs/mcp.md. Developer docs at mcp/soleil-mcp/readme.md.

## Conflicts detected

- Minor: frontend_lint uses `npm run lint` in policy.json but `pnpm lint` in some documentation. Non-breaking (both work).

## Boundary coverage table

| boundary | capability_defined | schema_version | failure_modes_defined | auth_defined | fallback_defined | gaps |
|----------|-------------------|---------------|----------------------|-------------|-----------------|------|
| mcp/soleil-mcp (read_file) | YES | NO | YES (blocked paths, blocked patterns, max size) | N/A (local stdio) | NO | No schema_version field |
| mcp/soleil-mcp (search) | YES | NO | YES (max results, max files, snippet limit) | N/A (local stdio) | NO | No schema_version field |
| mcp/soleil-mcp (run_verify) | YES | NO | YES (allowlist-only, timeout per target) | N/A (local stdio) | NO | No schema_version field |
| mcp/soleil-mcp (repo_overview) | YES | NO | YES (read-only) | N/A (local stdio) | NO | No schema_version field |
| mcp/soleil-mcp (project_invariants) | YES | NO | YES (read-only) | N/A (local stdio) | NO | No schema_version field |

## Changes applied

No changes made in this batch (audit-only).

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|-----------------|-------------|
| UNRESOLVED-B9A-1 | policy.json has no schema_version field | Decision on whether versioning is required for this local-only server | — |
| UNRESOLVED-B9A-2 | frontend_lint npm vs pnpm inconsistency | Which package manager is canonical for frontend lint | — |
| UNRESOLVED-B9A-3 | MCP boundary contract does not match the full boundary contract template (missing: idempotency/retry, observability, fallback/manual procedure, test/validation strategy) | These sections may not apply to a local stdio MCP server | — |

## Deliverables produced

- docs/cleanup/07-boundary-contract-report.md (this file)

## Risks and follow-up for next batch

- If MCP server adds write capabilities, boundary contract must be expanded to cover auth, idempotency, and failure modes
- schema_version should be added if policy.json changes are expected
