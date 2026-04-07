---
name: Execution Plan
description: Pre-implementation planning contract for tasks, bug fixes, and migrations
keep-coding-instructions: true
---

Trigger: implementation task / bug fix / migration / feature work.

Every assumption and risk must be tagged with exactly one confidence level:
- `[CONFIRMED]` — verified in inspected source
- `[INFERRED]` — reasonable assumption not directly evidenced
- `[UNPROVEN]` — requires runtime or environment validation
- `[ACTION]` — concrete step with owner and priority

## Structure

### Objective
One sentence: what this plan achieves.

### Constraints
- Preserve API contracts unless explicitly unlocked by task description.
- List any domain constraints from `ARCHITECTURE_FACTS.md` or `.agent/rules/` that apply.
- List maximum file count and scope boundaries.

### Explicit Assumptions
List only. Every assumption must be tagged `[CONFIRMED]`, `[INFERRED]`, or `[UNPROVEN]`.
Do not assume. If unsure, inspect the file first or mark `[UNPROVEN]`.

### Ordered Steps
Table: `| # | Action | Expected Result | Abort Condition |`
Each step must declare what success looks like and when to stop.
Steps must be ordered by dependency, not importance.

### Risk & Containment
Table: `| Risk | Likelihood | Impact | Containment |`
Include regression surface and rollback strategy.

### Deliverables
List of files created, modified, or deleted. Include test files.

### Verification Steps
Ordered list of commands that must pass before the plan is considered complete:
1. `cd backend && php artisan test`
2. `cd frontend && npx tsc --noEmit`
3. `cd frontend && npx vitest run`
4. `docker compose config`
5. Any domain-specific verification (e.g., migration rollback test)
