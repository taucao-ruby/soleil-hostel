---
verified-against: CLAUDE.md
secondary-source: docs/agents/ARCHITECTURE_FACTS.md
section: "Non-negotiable constraints"
last-verified: 2026-03-25
maintained-by: docs-sync
---

# Security Runtime Hygiene

## Purpose
Prevent secret leakage, unsafe runtime configuration, and unsanitized user-controlled data from crossing instruction layers into code or docs.

## Rule
- Never commit real secrets, credentials, private keys, or token material.
- Runtime code reads configuration through `config()`, not direct `env()` access.
- User-controlled HTML and rich text keep the established sanitization path (`HtmlPurifierService` on the backend and shared security utilities on the frontend where applicable).
- Sensitive auth, session, payment, and identity artifacts must not be logged, echoed, or exposed in fixtures or user-visible errors.
- Secret-bearing files and credential patterns stay behind the sensitive-file protections already enforced by the repo.

## Why it exists
These constraints prevent credential disclosure, environment drift, XSS exposure, and sensitive-data leakage during debugging or audits.

## Applies to
Agents, humans, skills, commands, reviews, hooks, docs, and code touching auth, cookies, logging, compose/config files, user input, or environment wiring.

## Violations
- Committing a real `APP_KEY`, password, webhook secret, or private key.
- Calling `env()` in controllers or services.
- Rendering or storing unsanitized user-controlled HTML.
- Logging token values, passwords, auth headers, or session identifiers.
- Editing secret-bearing files without the repo's sensitive-file safeguards.

## Enforcement
- Canonical sources: `CLAUDE.md`, `docs/agents/ARCHITECTURE_FACTS.md`, `docs/HOOKS.md`.
- Runtime and CI enforcement: Gitleaks, logging processors, auth/security middleware, `.claude/hooks/guard-sensitive-files.sh`.
- Review and validation: `.claude/commands/audit-security.md`, `.claude/commands/review-pr.md`, security-focused backend/frontend tests.

## Linked skills / hooks
- `skills/laravel/security-secrets-skill.md`
- `skills/react/security-frontend-skill.md`
- `.claude/hooks/guard-sensitive-files.sh`
- `.claude/commands/audit-security.md`
