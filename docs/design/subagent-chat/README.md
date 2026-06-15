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

## Decision (2026-06-15) — kept as-is, no consolidation

Batch 4 was **relocation only**. The owner elected to **keep all four files unchanged** — no
merge, dedup, or deletion. These are unimplemented, zero-reference design RFCs; consolidating
them into a single canonical doc was judged low-value. This folder stands as a documented
design archive, and OQ-1 (V3 vs R3_CONTRACTS canonical) is **closed as "not pursued"** rather
than decided. The version lineage above is the map; read whichever round you need.
