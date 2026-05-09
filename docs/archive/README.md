# docs/archive — historical process artifacts

Index of historical documentation that is preserved for audit purposes but is **not** part of the active reference set. Do not cite these as current truth. For live state see:

- `PROJECT_STATUS.md` — current health snapshot
- `docs/agents/ARCHITECTURE_FACTS.md` — current invariants
- `docs/PERMISSION_MATRIX.md` — current RBAC
- `docs/DB_FACTS.md` — current DB facts
- `docs/FINDINGS_BACKLOG.md` — current open findings
- `BACKLOG.md` — current actionable queue
- `scripts/ship.sh` — canonical gates

## Archived content (still located in their original folders)

These directories are archived in classification only — their files remain at the original paths so that internal cross-references resolve. Each folder carries an `ARCHIVED.md` marker.

| Folder | Role | Why archived |
|---|---|---|
| `docs/cleanup/` | Reports from the 2026-Q1 doc cleanup waves (00–08, unresolved-registry) | Historical batch outputs; superseded by current `docs/agents/` + `docs/PERMISSION_MATRIX.md` |
| `docs/gates/` | Gate result snapshots (gate-a/b/c/rc1) | Point-in-time results; live gates run via `scripts/ship.sh` |
| `docs/decisions/` | wave-0 decision lock | One-shot decision; folded into current rules |
| `docs/governance/` | Pipeline closure / instruction-system-maintenance / post-execution-audit-verdict | Process artifacts; live governance is `docs/AI_GOVERNANCE.md` + `docs/agents/CONTROL_PLANE_OWNERSHIP.md` |
| `docs/audit/` | Post-execution audit report + remediation plan + findings summary | Historical audit; current findings live in `docs/FINDINGS_BACKLOG.md` |
| `docs/validation/` | Structural / boundary / drift checklists from cleanup waves | Snapshot validation; superseded by canonical fact docs |

## Older snapshot audits at root

- `docs/AUDIT_2026_02_21.md` — full repo audit snapshot, 2026-02-21
- `docs/AUDIT_2026_03_12_STRUCTURE.md` — structure snapshot, 2026-03-12

These are dated and remain at root for direct citation; do not overwrite.
