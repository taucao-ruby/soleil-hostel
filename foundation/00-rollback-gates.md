# Rollback Gates — High-Risk Batch Safety Protocol

> Foundation file. READ before Batch 3 and any batch marked HIGH risk.
> Defines rollback criteria and recovery procedures.

---

## When This File Applies

- **Mandatory** before: Batch 3 (root contract rewrite)
- **Recommended** before: any batch with risk level MEDIUM-HIGH or higher
- **Reference** for: gate reviewers assessing whether to FAIL a gate

---

## Batch 3 — Root Contract Rewrite

### Pre-Rewrite Checklist

```
[ ] foundation/03-invariant-baseline.md exists and contains all invariants
[ ] Invariant baseline covers CLAUDE.md (I-01 through I-74)
[ ] Invariant baseline covers AGENTS.md unique content (A-01 through A-04)
[ ] Git working tree is clean (no uncommitted changes to CLAUDE.md)
[ ] Current CLAUDE.md content has been read in this session
```

### Rollback Trigger

Roll back the Batch 3 rewrite if ANY of the following are true:

1. Any invariant from `03-invariant-baseline.md` has no disposition enum in `02-invariant-delta.md`
2. Any invariant marked `INTENTIONALLY_REMOVED` lacks explicit justification
3. The refactored `CLAUDE.md` introduces a statement that contradicts an invariant marked `PRESERVED_IN_PLACE`
4. The refactored `CLAUDE.md` references a file path that does not exist
5. Gate A has not passed (Phase A prerequisite not met)

### Recovery Procedure

1. Restore `CLAUDE.md` from git: `git checkout HEAD -- CLAUDE.md`
2. Log the failure reason in `docs/cleanup/unresolved-registry.md`
3. Re-run the preservation test to confirm baseline is intact
4. Address the failure cause before re-attempting Batch 3

---

## Batch 5 — Skills Refactoring

### Rollback Trigger

Roll back if:

1. A split skill fragment cannot execute independently (over-split guard failure)
2. A transactional boundary was broken by the split
3. Split-decision log is missing or incomplete in `04-skills-refactor-report.md`

### Recovery Procedure

1. Restore original skill files from git
2. Log the over-split failure in `unresolved-registry.md`
3. Re-apply the over-split guard analysis before re-attempting

---

## General Rollback Protocol (any batch)

### When to Roll Back

- Output artifact fails schema validation against `00-output-schemas.md`
- Silent deletion detected (content removed without traceability log)
- Authority order violated (lower layer overwrote higher layer)
- Gate verdict is FAIL and remediation would require re-running the batch from scratch

### How to Roll Back

1. Identify affected files via `git diff` against the pre-batch state
2. Restore files: `git checkout HEAD -- <paths>`
3. Log rollback reason and affected files in batch report
4. Do NOT delete the failed batch output — move it to a `_failed/` suffix for forensics
5. Re-run the batch after addressing the root cause

---

## Cross-Reference

- Invariant baseline: `foundation/03-invariant-baseline.md`
- Invariant delta: `docs/cleanup/02-invariant-delta.md`
- Master contract: `foundation/00-master-contract.md`
- Output schemas: `foundation/00-output-schemas.md`
