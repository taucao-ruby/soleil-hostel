# SubAgent Chat Architecture — design RFCs (unimplemented)

> **Status: DESIGN / UNIMPLEMENTED.** Nothing in this folder is built. Every contract here is
> labelled `[PROPOSED]`, `DESIGN-BASELINE`, or `PROPOSED CONTRACT`. Do **not** treat any
> statement here as a domain invariant — canonical invariants live in
> `docs/agents/ARCHITECTURE_FACTS.md`. These docs only describe how a *future* guest-facing
> chat / booking agent would wrap existing endpoints; they never override the service-layer
> invariants they reference.

Relocated from the repository `docs/` root in restructure **Batch 4**
(`docs/DOCS_RESTRUCTURE_PLAN.md`), where they were a ~5,721-line orphan family with **zero**
inbound references anywhere in the repo.

## Version lineage

| File | Role |
|---|---|
| `SUBAGENT_ARCHITECTURE.md` | v1 base (1694 lines) |
| `SUBAGENT_ARCHITECTURE_DELTA_R2.md` | "Delta Hardening Round 2" (955) — self-declared *delta that supplements, does not replace, v1*; dated 2026-03-23, commit `d42211b` |
| `SUBAGENT_ARCHITECTURE_R3_CONTRACTS.md` | "Round 3 Final Implementation Contracts" (1184) — vFinal tool matrix, source baseline `d42211b` |
| `SUBAGENT_ARCHITECTURE_V3.md` | "V3" full restatement (1888) with `[PROPOSED]`/`[CONFIRMED]` labels |

## Pending — NOT done in this batch

Batch 4 performed **relocation only** (non-destructive). The consolidation is still gated:

- **OQ-1 — canonical choice (unresolved, needs human decision):** which of
  `SUBAGENT_ARCHITECTURE_V3.md` (full rewrite) or `SUBAGENT_ARCHITECTURE_R3_CONTRACTS.md`
  ("Round 3 Final") is the canonical base? Both claim finality; none is marked superseded.
- **Content preservation (before any merge/dedup):** `DELTA_R2`'s Output-7 operational-default
  thresholds + baseline-data-collection plan, and any v1-only content, must be folded into the
  chosen canonical doc first. **No content has been merged or deleted — only moved.**
