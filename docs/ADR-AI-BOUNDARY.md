# ADR: AI Safety Boundary

**Status**: Accepted  
**Date**: 2026-04-09  
**Deciders**: Principal Engineer, AI Harness Engineer  
**Context**: Soleil Hostel AI integration — Phase 1–3 boundary design

## Decision

AI models operate strictly within a read-and-draft boundary. The harness enforces
this at multiple layers — **not through prompt instructions alone**.

### Boundary Rules

1. **No autonomous writes**: The model cannot create, update, or delete any record.
   All mutation tools are classified BLOCKED in `ToolRegistry` permanently.

2. **APPROVAL_REQUIRED for drafts**: Admin drafts (Phase 3) are returned as
   `ToolDraft` structs. They are never written to DB. A human must explicitly
   confirm before any downstream action occurs.

3. **Policy enforcement is authoritative**: `PolicyEnforcementService` (L4) makes
   final safety decisions. Prompt instructions are behavioral guidance only —
   the policy layer is the actual safety control.

4. **Context is allowlisted**: Each `TaskType` has a static source allowlist in
   `ContextAssemblyService`. The model cannot request sources outside its allowlist.

5. **RBAC at assembly time**: Admin-only sources (contact_messages) are filtered
   at context assembly, not just at middleware. Even if middleware is bypassed,
   the assembly layer blocks unauthorized access.

6. **Cross-customer PII**: Third-party customer data in admin drafts is detected
   and blocked before inclusion in context. Admin's own customer context is allowed
   (they need PII to respond), but other customers' data is excluded.

## Alternatives Considered

### A: Prompt-only safety
Rejected. Prompts are advisory and can be circumvented through prompt injection.
Safety controls must be enforced in code (L4, L5).

### B: Full autonomous agent
Rejected. Business risk too high for a hospitality booking system. Double-booking
prevention and refund integrity require human oversight.

### C: Allowlist-specific tool permissions per role
Partially adopted via RBAC gates in `ToolRegistry`. Full per-action authorization
deferred to Phase 4+ if needed.

## Consequences

- All AI features are read-only or draft-only — no autonomous writes
- Admin drafts require explicit human confirmation — increased latency but guaranteed safety
- Tool classification is static and cannot be changed at runtime
- Unknown tools default to BLOCKED — fail-safe by design
- Nightly regression gate catches any drift in model behavior
