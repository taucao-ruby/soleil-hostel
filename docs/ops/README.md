# docs/ops — Operations & Deployment

Operational and deployment documentation for Soleil Hostel.

| File | Purpose |
|------|---------|
| [PRODUCTION_DEPLOYMENT.md](./PRODUCTION_DEPLOYMENT.md) | Full-stack production deployment **configuration reference** — Dockerfiles, `docker-compose.prod.yml`, nginx, env, CI/CD, monitoring, backup, security, performance |
| [FIRST_DEPLOY_STAGING.md](./FIRST_DEPLOY_STAGING.md) | **DE-01 first-deploy runbook** — ordered checklist to ship to staging and prove it with a real MoMo sandbox callback + Stripe test-mode payment; references the config/procedure docs for mechanics |
| [DEPLOY_EXECUTION_PROMPT.md](./DEPLOY_EXECUTION_PROMPT.md) | **Hands-on Option-B execution script** (VI) — run-on-VM-paste-output-back, command-by-command companion to the runbook; includes the seed step + verified test user + secret-safety checks |

## Related (elsewhere)

- Step-by-step deploy **runbook**: [`docs/backend/DEPLOYMENT.md`](../backend/DEPLOYMENT.md)
- Local/dev Docker **Compose guide**: [`docs/backend/guides/DEPLOYMENT.md`](../backend/guides/DEPLOYMENT.md)
- Operational **playbook**: [`docs/OPERATIONAL_PLAYBOOK.md`](../OPERATIONAL_PLAYBOOK.md)
- Frontend build/deploy: [`docs/frontend/DEPLOYMENT.md`](../frontend/DEPLOYMENT.md)

Created in Batch 5b of `docs/DOCS_RESTRUCTURE_PLAN.md`.
