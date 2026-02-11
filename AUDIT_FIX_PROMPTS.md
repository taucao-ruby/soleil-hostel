# AUDIT_FIX_PROMPTS.md

This file is now the index for audit remediation playbooks.

## Playbooks

- v1 only: `AUDIT_FIX_PROMPTS_V1.md`
- v2 only: `AUDIT_FIX_PROMPTS_V2.md`

## How to Run

1. Choose audit stream first: v1 or v2.
2. Open the matching playbook and execute one batch at a time.
3. Use the batch-specific copy/paste prompt and commit convention in that file.
4. Do not mix v1 and v2 issue IDs in one batch or commit.

## Common Verification Commands

```bash
# Compose validation
cd <repo-root> && docker compose config

# Backend verification
cd backend && php artisan test

# Frontend verification
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
```

## Source of Truth

- Verified repo state and audit status: `AUDIT_REPORT.md`
- Executive status summary: `PROJECT_STATUS.md`