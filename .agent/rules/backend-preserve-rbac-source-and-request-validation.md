---
verified-against: CLAUDE.md
secondary-source: docs/PERMISSION_MATRIX.md
section: "Non-negotiable constraints"
last-verified: 2026-03-25
maintained-by: docs-sync
---

# Backend Preserve RBAC Source And Request Validation

## Purpose
Keep backend authorization and validation anchored to one authority path instead of drifting across controllers, docs, and frontend helpers.

## Rule
- `docs/PERMISSION_MATRIX.md` remains the RBAC permission source of truth; lower-layer docs, skills, and reviews must reference it instead of redefining permissions.
- Backend request validation belongs in `*Request.php` classes, not controllers.
- Backend architecture remains Controller -> Service -> Repository.
- Frontend validation may improve UX, but backend request validation stays the authoritative rejection layer.

## Why it exists
This prevents permission drift, fat controllers, duplicated validation logic, and disagreement between UI expectations and backend enforcement.

## Applies to
Agents, humans, skills, commands, reviews, docs, and code touching backend authorization, request validation, or controller/service/repository boundaries.

## Violations
- Introducing a second permission matrix in a skill, command, or review checklist.
- Moving validation rules into a controller action.
- Bypassing the service or repository layer for business logic that already belongs there.
- Treating frontend validation as authoritative for API acceptance.

## Enforcement
- Canonical sources: `CLAUDE.md`, `docs/PERMISSION_MATRIX.md`, `docs/agents/CONTRACT.md`.
- Review and validation: `.claude/commands/fix-backend.md`, `.claude/commands/review-pr.md`, `skills/react/forms-validation-skill.md`, and the existing request-validation test suite.

## Linked skills / hooks
- `skills/laravel/testing-skill.md`
- `skills/react/forms-validation-skill.md`
- `.claude/commands/fix-backend.md`
- `.claude/commands/review-pr.md`
