# Remediation Plan — Post-Execution Audit

> Date: 2026-03-25 | Covers: All CRITICAL and MAJOR findings

## CRITICAL Findings

None. Zero CRITICAL findings were identified.

---

## MAJOR Findings — Remediation

### M-01: COMPACT.md missing `status` metadata field

| Attribute | Value |
|-----------|-------|
| **Finding** | `docs/COMPACT.md` freshness metadata block has 4 of 5 required fields. Missing: `status: ACTIVE \| STALE \| ARCHIVED` |
| **Action required** | Add `status: ACTIVE` to the Lifetime metadata block in `docs/COMPACT.md`, below the `expiry_trigger` line |
| **Owner layer** | COMPACT_SNAPSHOT |
| **Estimated effort** | 1 line addition (~2 minutes) |
| **Blocking** | No |
| **Risk** | None — additive change to metadata block |

```diff
 > **Lifetime metadata** (per master contract)
 > - generated_from: ARCHITECTURE_FACTS.md, CONTRACT.md, COMMANDS_AND_GATES.md, FINDINGS_BACKLOG.md
 > - last_verified_at: 2026-03-21
 > - scope: AI session handoff state (current snapshot, active work, known warnings, pointers)
 > - expiry_trigger: any code task, gate run, or milestone change
+> - status: ACTIVE
```

---

### M-02: Missing fixture types (boundary-failure + agent-escalation)

| Attribute | Value |
|-----------|-------|
| **Finding** | `docs/validation/fixtures/` has 3 fixtures (RC-001, SE-001, CD-001). Missing: boundary-failure fixture and agent-escalation fixture |
| **Action required** | Create 2 new fixture files following the pattern of existing fixtures |
| **Owner layer** | VALIDATION |
| **Estimated effort** | 30–45 minutes per fixture |
| **Blocking** | No |

#### BF-001: Boundary Failure Fixture (suggested)

```markdown
# BF-001: Boundary Failure Fixture — MCP Blocked Path Access

> Tests that the MCP server correctly blocks access to secret-bearing files.

## Assertion
The MCP server must block read access to files matching blocked_file_patterns in policy.json.

## Verification Target
| File | What to Check |
|------|--------------|
| `mcp/soleil-mcp/policy.json` | blocked_file_patterns array |
| `mcp/soleil-mcp/src/index.ts` | enforcement logic |

## Expected Result
Requesting `.env`, `*.key`, `*.pem` returns error/blocked response, not file contents.

## Forbidden Behaviors
- Returning file contents for any blocked pattern
- Silently succeeding without checking the blocklist
```

#### AE-001: Agent Escalation Fixture (suggested)

```markdown
# AE-001: Agent Escalation Fixture — Security Reviewer Scope Boundary

> Tests that security-reviewer agent stops and escalates when encountering schema-level issues outside its scope.

## Assertion
When security-reviewer encounters a column existence problem, it must:
1. Identify the issue is in db-investigator's scope
2. Stop its own analysis for that finding
3. Reference the API handoff protocol

## Verification Target
| File | What to Check |
|------|--------------|
| `.claude/agents/security-reviewer.md` | "The db-investigator agent owns schema column existence checks" boundary statement |
| `docs/agents/api-handoff-protocol.md` | Handoff triggers and output contract |

## Expected Result
Agent file contains explicit scope boundary. Protocol file defines handoff conditions.

## Forbidden Behaviors
- Security-reviewer asserting schema column existence without deferring
- Ignoring the scope boundary between agents
```

---

### M-03: Empty human countersigns on gate records

| Attribute | Value |
|-----------|-------|
| **Finding** | All 4 gate records in `docs/gates/` have `human_countersign: ""` |
| **Action required** | Human reviewer must: (1) Review each gate's Human Review Checklist, (2) Sign the `human_countersign` field in YAML frontmatter, (3) For Gate RC1: complete the 7-item Next Steps list |
| **Owner layer** | HUMAN (not agent-actionable) |
| **Estimated effort** | 15–30 minutes total |
| **Blocking** | YES — blocks formal pipeline closure |

Gate RC1 specifically requires:
1. Countersign this gate
2. Countersign Gates A, B, C
3. Acknowledge REM-1 (control plane gap)
4. Test B3-1 (`npx soleil-ai-review-engine analyze` re-injection)
5. Delete `docs/DEVELOPMENT_HOOKS.md`
6. Plan WORKLOG archive at ~250 lines
7. Next refactor cycle starts from Phase 0

---

### M-04: Agent contracts missing aspirational template sections

| Attribute | Value |
|-----------|-------|
| **Finding** | All 4 `.claude/agents/*.md` files are missing: Forbidden actions (0/4), Negative examples (0/4), formal Escalation path (2/4 have linked protocols only) |
| **Action required** | Add to each agent file: `## Forbidden Actions` (min 2 items), `## Negative Examples` (min 2 "should NOT do this" cases), `## Escalation Path` (structured) |
| **Owner layer** | AGENTS |
| **Estimated effort** | 20–30 minutes per agent (×4 = 80–120 minutes total) |
| **Blocking** | No — may be deferred to next refactor cycle |

**Priority order**: security-reviewer (highest risk) → db-investigator → frontend-reviewer → docs-sync

**Example for security-reviewer.md**:
```markdown
## Forbidden Actions
- Do not modify application code — this agent is read-only
- Do not assert schema column existence (db-investigator scope)

## Negative Examples
- ❌ "Column `verified_at` does not exist in users table" — this is schema investigation, not security review
- ❌ Modifying `api.ts` to fix a CSRF issue found during review — this agent reports, it does not fix

## Escalation Path
1. If a finding touches both security and schema: use API handoff protocol
2. If a finding is CRITICAL severity: stop and surface immediately with evidence
3. If unsure whether an issue is in scope: flag as "SCOPE-UNCLEAR" and continue
```

---

## MINOR Findings — Notes (no formal remediation required)

| ID | Quick Fix |
|----|-----------|
| m-01 | Self-correcting: next code session updates COMPACT.md §1 test counts |
| m-02 | Delete `docs/DEVELOPMENT_HOOKS.md` (included in M-03 gate closeout) |
| m-03 | Add a "Violations" subsection to governance maintenance guide specifying consequence for authority order violations |
| m-04 | Update structural checklist to reflect 7 rule files (from 3) and re-run validation |
