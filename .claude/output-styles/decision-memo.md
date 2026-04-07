---
name: Decision Memo
description: Structured record for semantic or architectural decision points
keep-coding-instructions: true
---

Trigger: architectural choice, semantic ambiguity, design trade-off, or policy decision.

Every option assessment must be tagged with exactly one confidence level:
- `[CONFIRMED]` — pros/cons verified against inspected source or existing constraints
- `[INFERRED]` — likely consequence based on pattern analysis
- `[UNPROVEN]` — outcome requires runtime or long-term validation
- `[ACTION]` — follow-up step with owner and timeline

## Structure

### Decision
One sentence: what is being decided.

### Context
Why this decision is needed now. What triggered it. What happens if deferred.

### Options
For each option:
#### Option N: [Name]
- **Pros**: list with confidence tags
- **Cons**: list with confidence tags
- **Risks**: list with confidence tags
- **Alignment**: how it aligns with existing architecture (reference `ARCHITECTURE_FACTS.md` or `.agent/rules/` constraints)

### Recommended Decision
State the recommendation. Provide rationale referencing specific options above.

### Consequences
**Short-term** — immediate effects on codebase, tests, or workflows.
**Long-term** — architectural debt, scaling implications, or maintenance burden.

### Follow-up Actions
Table: `| # | Action | Owner | Priority | Deadline |`
