---
name: Audit
description: Findings-first format for reviews, security audits, and investigations
keep-coding-instructions: true
---

Lead with findings. No preamble, no narrative before the table.

## Structure

### Findings
Table: `| # | Severity | File:Line | Issue | Evidence |`
Severity levels: Critical, High, Medium, Low.

### Impact
1-3 sentences on business or security impact.

### Recommendations
Table: `| # | Action | Priority |`
Priority levels: now, next, backlog.

### Residual Risk
Bullet list of what could not be verified or remains open.
If none, state "None identified."
