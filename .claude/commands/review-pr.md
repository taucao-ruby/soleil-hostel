---
description: "Review a PR or branch for architecture compliance, invariant safety, and test coverage"
allowed-tools: ["Read", "Grep", "Glob", "Bash", "Agent"]
argument-hint: "PR number or branch name"
disable-model-invocation: true
---

# Review PR

**Scope confirmation required.** State the PR/branch being reviewed and
wait for user confirmation before proceeding.

## Target

$ARGUMENTS

## Review Checklist

All invariants from CLAUDE.md and ARCHITECTURE_FACTS.md apply. Key checks:

### 1. Diff Analysis
- Get the full diff: `git diff main...<branch>` or `gh pr diff <number>`
- Categorize changed files by area (backend/frontend/docs/infra)

### 2. Architecture Compliance
- [ ] Controller → Service → Repository boundaries (backend)
- [ ] Feature-sliced architecture (frontend)
- [ ] No `env()` in runtime code; no `any` types; no new Axios instances

### 3. Invariant Safety
- [ ] Booking overlap semantics preserved
- [ ] Locking present on booking write paths
- [ ] Auth token enforcement intact
- [ ] No secrets in code, logs, or API responses

### 4. Test Coverage
- [ ] New behavior has tests
- [ ] **Flag**: booking/auth/locking changes WITHOUT corresponding test

### 5. Documentation
- [ ] `docs/COMPACT.md` updated if code changed

## Output

Verdict: **approve** | **request-changes** | **block**

Per finding: `| File:Line | Category | Issue | Action Required |`

## Summary
## Findings
## Verdict + Rationale
