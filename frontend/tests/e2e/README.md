# Playwright E2E suite

Batch 4 / 3H. Four user-visible flows live here; each uses the page object
model so locators and step semantics are reused across tests.

## Flows

| File                            | Flow                                                                              |
| ------------------------------- | --------------------------------------------------------------------------------- |
| `flows/guest-booking.spec.ts`   | Land on availability page → select room → complete booking form → confirmation    |
| `flows/payment-webhook.spec.ts` | Trigger Stripe `payment_intent.succeeded` → admin dashboard shows `confirmed`     |
| `flows/ai-proposal.spec.ts`     | Open room discovery widget → submit NL query → confirm proposal → booking created |
| `flows/admin-restore.spec.ts`   | Admin soft-deletes booking → trashed list → restore → reappears in active list    |

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

Flows are gated behind `workflow_dispatch` until the suite stabilises.
Once green for two consecutive merges, promote to `pull_request` trigger
in `.github/workflows/e2e.yml`.
