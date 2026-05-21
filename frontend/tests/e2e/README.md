# Playwright E2E suite

Batch 4 / 3H. Four user-visible flows live here; each uses the page object
model so locators and step semantics are reused across tests.

## Flows

| File                            | Flow                                                                                                     |
| ------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `flows/guest-booking.spec.ts`   | Land on availability page → select room → complete booking form → confirmation                           |
| `flows/payment-webhook.spec.ts` | Seed a pending booking via the API → signed `payment_intent.succeeded` → booking API reports `confirmed` |
| `flows/ai-proposal.spec.ts`     | Open room discovery widget → submit NL query → confirm proposal → booking created                        |
| `flows/admin-restore.spec.ts`   | Admin soft-deletes booking → trashed list → restore → reappears in active list                           |

## Running

```bash
cd frontend
pnpm exec playwright install --with-deps  # first time only
pnpm exec playwright test                  # all flows
pnpm exec playwright test flows/guest-booking
pnpm exec playwright test --ui             # interactive
```

## Page objects

`pages/*.ts` encapsulates locators + actions for one screen. Tests compose
multiple pages; pages must not import other pages or test files.

## Stability discipline

- Each `*.spec.ts` is self-contained (no shared state) — Playwright runs
  files in parallel by default.
- No external network: Stripe webhooks are simulated by hitting our own
  `/api/webhooks/stripe` endpoint with a signed test payload (see flow file).
- Selectors prefer `getByRole` / `getByTestId` over CSS — surface tests
  through user intent, not markup.

## CI

See `.github/workflows/e2e.yml`. Two modes:

- **PR gate** (`pull_request` → `main`/`dev`): runs only the `@smoke` flows
  (`guest-booking` + `payment-webhook`) on chromium. Failure blocks merge.
- **Nightly + manual** (`schedule` 02:00 UTC, `workflow_dispatch`): the full
  suite across all browsers. Non-smoke flows live here until stable enough to
  earn a `@smoke` tag.

`@smoke` flows must seed through the real API (no test-only backend harness):
log in (`/auth/login-v2`), create/mutate via the v1 endpoints, then assert.
