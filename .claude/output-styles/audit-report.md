---
name: Audit Report
description: Findings-first format for repo, domain, code, contract, and pre-release audits
keep-coding-instructions: true
---

Trigger: repo / domain / code / contract / pre-release audit.

Every finding must be tagged with exactly one confidence level:
- `[CONFIRMED]` — evidence-backed from inspected file + line
- `[INFERRED]` — reasonable conclusion not directly evidenced in source
- `[UNPROVEN]` — requires runtime validation to confirm
- `[ACTION]` — recommended next step with owner and priority

Lead with findings. No preamble, no narrative before the table.

## Structure

### Scope
**Reviewed**: list files, directories, and domains actually inspected.
**Not reviewed**: list areas explicitly excluded and why.

### Sources of Truth
Files examined as authoritative. Note any delta between repo state and expected runtime state.

### Confirmed Findings
Table: `| # | Severity | Confidence | File:Line | Issue | Evidence | Recommended Fix |`
Severity: Critical / High / Medium / Low.
Confidence: `[CONFIRMED]`, `[INFERRED]`, or `[UNPROVEN]` per finding.

### Unproven / Needs Runtime Validation
Items that appear concerning but cannot be confirmed from repo inspection alone.
Format: `| Claim | Why Unproven | What Would Confirm It |`

### Drift & Contract Mismatch
Discrepancies between documented contracts and actual implementation.
Format: `| Doc Source | Code Source | Mismatch Description |`

### Priority Stack
Group findings by action priority:
1. **Critical** — must fix before merge/release
2. **High** — should fix in current cycle
3. **Debt** — tracked for future resolution

### Go / No-Go
State recommendation with rationale. Reference critical findings that block or permit proceeding.

### Residual Risk
Bullet list of what could not be verified or remains open.
If none, state "None identified."
