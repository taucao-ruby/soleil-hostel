# First Deploy to Staging — DE-01 Runbook

> **Purpose:** the *first* end-to-end deploy that turns "verified in CI" into "running and
> proven with real (test-mode) traffic" — the **DE-01** acceptance in [`BACKLOG.md`](../../BACKLOG.md)
> (EPIC 7). This is a **procedure checklist**, not a config reference.
>
> **Read alongside (mechanics — intentionally NOT duplicated here):**
> - Config reference (Dockerfiles, nginx, env, monitoring, backup): [`PRODUCTION_DEPLOYMENT.md`](./PRODUCTION_DEPLOYMENT.md)
> - Step-by-step deploy procedure: [`../backend/DEPLOYMENT.md`](../backend/DEPLOYMENT.md)
> - Rollback + HTTPS: [`../OPERATIONAL_PLAYBOOK.md`](../OPERATIONAL_PLAYBOOK.md)
>
> **Last verified state (2026-06-24, `origin/dev` `08aaed9`):** full gate green — backend
> 1789 passed / 9 skipped / 5740 assertions, frontend 545 / 55 files, Pint/PHPStan/Psalm clean;
> `docker compose -f docker-compose.prod.yml config` → **VALID**.

## Why deploy first

Staging gives a **public HTTPS URL**, which is exactly what the remaining proof steps need:

- MoMo can POST a **real IPN** to `https://<staging>/api/v1/payments/momo/ipn` — **no local tunnel
  required**. This is the only thing that retires the last `[UNPROVEN]`: that our IPN signature
  field-order matches the real MoMo gateway (the test suite + `momo:simulate-ipn` only prove our
  own sign/verify is internally symmetric).
- A real Stripe **test-mode** payment exercises the webhook path against a live endpoint.

## Prerequisites

### P1 — Complete the prod env template (F-98) — *human task*

`backend/.env.production.example` carries no payment keys, so a deploy from it silently disables
payments (MoMo IPN fail-closes on the blank secret; Stripe inert). Agent edits are blocked by the
`guard-sensitive-files.sh` hook by design — add by hand. The copy-paste key block is in
[`FINDINGS_BACKLOG.md` → F-98](../FINDINGS_BACKLOG.md). Also drop the dead `CACHE_DRIVER`.
Real secret values live in the deploy secret store, never in the committed file.

### P2 — Set GitHub Actions secrets (`.github/workflows/deploy.yml`)

Pick **one** deploy-target group; set the shared groups too:

- **Registry:** `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`
- **Prod DB:** `PROD_DB_HOST`, `PROD_DB_NAME`, `PROD_DB_USERNAME`, `PROD_DB_PASSWORD`
- **Target — pick one:** SSH (`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_KEY`) · Coolify
  (`COOLIFY_API_TOKEN`, `COOLIFY_APP_ID`) · Forge (`FORGE_API_TOKEN`, `FORGE_SERVER_ID`,
  `FORGE_SITE_ID`) · Render (`RENDER_DEPLOY_HOOK`)
- **App / notify:** `INTERNAL_API_TOKEN`, `SLACK_WEBHOOK_URL`
- **Runtime env (sourced into the container, not committed):** `APP_KEY`, `REDIS_PASSWORD`,
  `STRIPE_KEY`/`STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET`, `CASHIER_CURRENCY=vnd`, and the `MOMO_*`
  set — note `MOMO_IPN_URL=https://<staging>/api/v1/payments/momo/ipn`.

## Deploy checklist (in order)

1. **Bring up the stack** on the VPS: `docker compose -f docker-compose.prod.yml up -d --build`.
   Confirm every service is `healthy`: `docker compose -f docker-compose.prod.yml ps`.
2. **Migrate:** `docker compose -f docker-compose.prod.yml exec -T backend php artisan migrate --force`
   (applies the MoMo tables + the F-97 `chk_momo_payments_expected_amount_nonneg` CHECK).
3. **Cache:** `config:cache` + `route:cache` + `view:cache` (inside the backend container).
4. **Health:** `curl -f https://<staging>/api/health/live` → `{"status":"ok"}`. Configure the
   Sentry DSN (the frontend `ErrorBoundary` has a TODO for it).
5. **Prove MoMo with a real callback** ← retires the last `[UNPROVEN]`:
   - In the SPA: create a pending prepaid booking → choose MoMo → render the QR.
   - Scan with the MoMo **sandbox** app → MoMo POSTs a real IPN to your staging IPN URL.
   - Assert: signature verifies, booking → `confirmed`, a row exists in `momo_webhook_events`
     keyed by (`order_id`, `trans_id`). Fire the same notification again → **204, no double-confirm**.
6. **Prove Stripe (test-mode):** one test-mode payment → `payment_intent.succeeded` webhook →
   booking `confirmed`; on a refund, an idempotency row appears in `stripe_refund_events`.
7. **Record `DEPLOYMENT_LOG.md`** at the repo root (template below) — this is the DE-01 acceptance.

## `DEPLOYMENT_LOG.md` template

```markdown
## <date> — first staging deploy
- Commit:           <sha> (origin/dev or origin/main)
- Target:           <SSH host | Coolify | Forge | Render>
- Services healthy: db, redis, backend, frontend, (caddy/nginx)
- Migrations:       <count> applied (incl. momo_payments, momo_webhook_events)
- MoMo real IPN:    PASS/FAIL — booking #<id> confirmed; momo_webhook_events row <id>
- Stripe test-mode: PASS/FAIL — booking #<id> confirmed
- Health:           /api/health/live OK; Sentry receiving events
- Notes / issues:
```

## After staging is green

- Promote `dev → main` (`--no-ff`, human-reviewed — per the branch flow in `CLAUDE.md`).
- Update `PROJECT_STATUS.md` (Deployment %) + add a Status Note; close **DE-01** in `BACKLOG.md`.
- Flip **F-98** to Fixed once the prod template carries the payment keys.

## Rollback

See [`../OPERATIONAL_PLAYBOOK.md`](../OPERATIONAL_PLAYBOOK.md) (Docker rollback). The pipeline's
`rollback` job triggers on a failed deploy/health-check and notifies Slack.
