---
verified-against: AGENTS.md
section: "GitNexus — Code Intelligence"
last-verified: 2026-03-25
maintained-by: docs-sync
---

# GitNexus Impact And Change Scope

## Purpose
Make symbol edits and refactors evidence-gated by graph impact analysis instead of grep-only guesses.

## Rule
- Before modifying a function, class, or method, run GitNexus impact analysis and report the blast radius.
- Before renaming a symbol, use the GitNexus rename workflow rather than manual find-and-replace.
- Before commit or merge, run GitNexus change detection to verify the changed scope matches intent.
- HIGH or CRITICAL impact findings require an explicit warning and confirmation before editing proceeds.

## Why it exists
These steps reduce hidden call-site breakage, unsafe renames, and scope creep in critical flows.

## Applies to
Agents, humans, commands, reviews, and refactors that touch code symbols in the indexed repository.

## Violations
- Editing a symbol without first checking upstream impact.
- Renaming code symbols with text search only.
- Committing without reviewing GitNexus change scope.
- Proceeding through HIGH or CRITICAL impact output without surfacing the risk.

## Enforcement
- Canonical source: `AGENTS.md` GitNexus section.
- Workflow enforcement: GitNexus MCP tooling and the repo's GitNexus skill set.
- Review enforcement: blast-radius reporting and pre-commit scope checks in refactor/review work.

## Linked skills / hooks
- `.claude/skills/gitnexus/gitnexus-guide/SKILL.md`
- `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md`
- `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md`
