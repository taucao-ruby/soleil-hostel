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

## Where to host — zero-cost ($0) options

The deploy is the same; only *where* the stack runs differs. Every option below uses a **free
platform subdomain with HTTPS** — no domain purchase needed. Point `MOMO_IPN_URL` at that subdomain.

> **Vercel/Netlify alone cannot run the backend.** Laravel here is stateful (Postgres + Redis + queue
> worker + scheduler), not serverless — those hosts are for the static frontend only.

### A. Just prove MoMo (no deploy, $0) — fastest
Run the stack locally (`docker compose -f docker-compose.prod.yml up -d`, `DOMAIN=localhost`) and
expose it with a free **Cloudflare Tunnel**: `cloudflared tunnel --url http://localhost:80` →
`https://<random>.trycloudflare.com`. Set `MOMO_IPN_URL=https://<that>/api/v1/payments/momo/ipn`, fire
a real sandbox IPN, retire the last `[UNPROVEN]`. Machine must stay on. No VPS, no domain.

### B. Always-on staging, $0 — reuses this compose (recommended)
**Oracle Cloud Always Free** ARM VM (Ampere A1, free forever) ≈ a free VPS.
1. Create an **Ampere A1** instance (Ubuntu 24.04). Open ingress **22/80/443** in the OCI security
   list, **and** on the VM open the OS firewall (Oracle images block these by default):
   `sudo iptables -I INPUT -p tcp --dport 80 -j ACCEPT && sudo iptables -I INPUT -p tcp --dport 443 -j ACCEPT && sudo netfilter-persistent save`.
2. Install Docker + the compose plugin; `git clone` the repo.
3. Register a free subdomain at **duckdns.org** (e.g. `soleil.duckdns.org`) → point it at the VM's public IP.
4. Env:
   - root **`.env`** (compose): `DB_PASSWORD=…`, `REDIS_PASSWORD=…`, `DOMAIN=soleil.duckdns.org`, and
     **`FRONTEND_PORT=8081`** — with `--profile proxy`, Caddy owns host `80`/`443`, so move the
     frontend's published port off `80` to avoid a clash.
   - **`backend/.env.production`** (the F-98 file): `APP_KEY`, payment keys,
     `APP_URL=https://soleil.duckdns.org`, `SANCTUM_STATEFUL_DOMAINS=soleil.duckdns.org`,
     `MOMO_IPN_URL=https://soleil.duckdns.org/api/v1/payments/momo/ipn`.
5. Bring up **with the proxy** (Caddy auto-issues Let's Encrypt for the DuckDNS name via HTTP-01):
   `docker compose -f docker-compose.prod.yml --profile proxy up -d --build`.
6. Migrate + cache (checklist steps 2–3). Caddy already routes `/api/*`→`backend:8080`, `/`→`frontend:8080`.

Always-on → scheduler, queue, and MoMo IPN are all reliable. The `Caddyfile` CSP already allows Stripe;
if the MoMo QR/redirect ever needs an external origin, add it there.

### C. No VM to manage, $0 — but cold starts
Frontend → **Cloudflare Pages** (`*.pages.dev`, no commercial restriction unlike Vercel Hobby);
backend → **Render free** (`*.onrender.com`, already wired via `RENDER_DEPLOY_HOOK` in `deploy.yml`);
Postgres → **Neon** free (do **not** use Render's free Postgres — it expires); Redis → **Upstash** free.
Render free **spins down** after ~15 min idle (30–60 s cold start) and won't run the scheduler while
asleep — fine to prove DE-01/MoMo, not for real traffic.

**Pick:** **A** to prove MoMo today; **B** for an always-on $0 staging that reuses everything you built.

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
