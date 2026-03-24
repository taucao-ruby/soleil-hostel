# Authority Order — Conflict Resolution Protocol

> Foundation file. READ when any conflict is detected between instruction layers.
> This file governs resolution verdicts for the entire pipeline.

---

## Authority Hierarchy (higher layer wins)

```
1. CLAUDE.md                                                — root contract / constitution
2. docs/agents/ARCHITECTURE_FACTS.md                       — canonical invariants
3. docs/agents/CONTRACT.md                                 — definition of done / task contract
4. docs/PERMISSION_MATRIX.md, docs/DB_FACTS.md             — canonical policy references
5. .agent/rules/*.md, skills/, skill-os/,                  — derived rules / execution procedures
   .claude/skills/
6. .claude/commands/, .claude/output-styles/               — thin entrypoints / output surfaces
7. .claude/hooks/, .claude/settings*.json                  — runtime enforcement
8. .claude/agents/                                         — role contracts
9. docs/COMPACT.md, PROJECT_STATUS.md                      — temporary context snapshots
10. docs/WORKLOG.md, BACKLOG.md                            — dated execution ledger
```

---

## Resolution Rules

1. **Top wins.** When two layers conflict, the higher layer's statement is authoritative.
2. **Lower conforms.** The lower-layer artifact must be updated to align with the higher layer — never the reverse, unless a formal amendment is made to the higher layer.
3. **No prose negotiation.** Conflicts are resolved by choosing one layer's statement, not by blending wording.
4. **Map concepts to paths first.** If a prompt uses conceptual buckets (`rules/`, `commands/`, `compact/`), resolve them to the observed repo paths above before judging conflicts.
5. **Evidence required.** Resolution must cite the specific lines/sections in both layers, using repo-exact path casing.
6. **UNRESOLVED if unclear.** If evidence is insufficient to determine which statement is correct, mark `UNRESOLVED` and log to `docs/cleanup/unresolved-registry.md`.

---

## UNRESOLVED Escalation

When an item is marked UNRESOLVED:

1. Log it immediately in `docs/cleanup/unresolved-registry.md` using the registry schema
2. Declare `blocks_next_batch: yes` or `blocks_next_batch: no`
3. If `blocks_next_batch: yes` — full stop; human decision required before proceeding
4. If `blocks_next_batch: no` — continue with the current batch, but the item must be resolved before the next gate

---

## Amendment Protocol

To formally amend a higher layer to accommodate a lower layer's requirement:

1. Document the amendment rationale in the batch report
2. Update the higher-layer file with the amendment
3. Log the amendment in `docs/cleanup/unresolved-registry.md` with status `resolved`
4. Verify no downstream artifacts are broken by the amendment

---

## Cross-Reference

- Master contract: `foundation/00-master-contract.md` (Section: Authority Order)
- Unresolved registry: `docs/cleanup/unresolved-registry.md`
- Gate artifact schema: pipeline spec, Gate Artifact Schema section
