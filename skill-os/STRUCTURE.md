# Folder Structure — Soleil Hostel Booking Skill OS

```
skill-os/
├── README.md                          # Usage instructions, quick-start, philosophy
├── TAXONOMY.md                        # Skill categories with failure-mode justifications
├── BACKLOG.md                         # Prioritized skill backlog (P0/P1/P2)
├── VERIFICATION-FRAMEWORK.md         # Cross-cutting verification philosophy and hierarchy
├── RISK-REGISTER.md                   # Open assumptions, deferred decisions, expansion plan
├── ROLLOUT-14DAY.md                   # Sequenced 14-day adoption plan
├── OPERATING-GUIDE.md                 # Daily usage instructions and decision trees
│
├── context/                           # Domain truth documents loaded before skill execution
│   └── INVARIANTS.md                  # INV-1 through INV-10 with schema references
│
├── skills/                            # Executable skill definitions, grouped by category
│   ├── verification/
│   │   ├── verify-no-double-booking/
│   │   │   ├── SKILL.md              # Full skill specification
│   │   │   └── checklist.md          # Binary pass/fail checklist
│   │   └── verify-docs-vs-code/
│   │       └── SKILL.md
│   ├── review/
│   │   └── review-schema-change-risk/
│   │       └── SKILL.md
│   └── release/
│       └── pre-release-verification/
│           └── SKILL.md
│
├── templates/                         # Fill-in-the-blank templates for structured output
│   ├── migration-risk-review.md      # Per-migration risk assessment template
│   └── release-readiness-report.md   # Release go/no-go report template
│
├── examples/                          # Worked examples demonstrating skill execution
│   └── docs-drift-review-example.md  # Real-world docs-vs-code drift review
│
├── lessons/                           # Institutional knowledge captured from failures
│   └── booking-invariant-gotchas.md  # Seed file of 8 hostel-booking gotchas
│
├── scripts/                           # Automation scripts for skill execution (future)
│   └── .gitkeep
│
├── test-data/                         # Sample data for skill dry-runs (future)
│   └── .gitkeep
│
├── outputs/                           # Completed skill execution reports (gitignored)
│   └── .gitkeep
│
└── logs/                              # Skill execution logs for audit trail (gitignored)
    └── .gitkeep
```

## Directory Rationale

| Directory | Purpose |
|---|---|
| `context/` | Domain truth that must be loaded before any skill runs. Separates "what is true" from "what to do about it." |
| `skills/` | Each skill is a self-contained directory with a SKILL.md and optional supporting files. Grouped by taxonomy category. |
| `templates/` | Reusable structured output formats. Skills reference these; humans fill them in. |
| `examples/` | Worked examples show what good skill execution looks like. Reduces ambiguity in skill interpretation. |
| `lessons/` | Post-failure knowledge capture. Feeds back into skill updates. Prevents institutional amnesia. |
| `scripts/` | Future: shell/PHP scripts that automate parts of skill execution (e.g., constraint verification queries). |
| `test-data/` | Future: sample booking/migration data for dry-running skills without touching real state. |
| `outputs/` | Completed reports from skill execution. Gitignored to avoid repo bloat; kept locally for audit. |
| `logs/` | Execution timestamps and pass/fail records. Gitignored. Useful for tracking skill adoption. |
