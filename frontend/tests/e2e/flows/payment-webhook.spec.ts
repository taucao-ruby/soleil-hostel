import {
  test,
  expect,
  request as playwrightRequest,
  type APIRequestContext,
} from '@playwright/test'
import { signStripeWebhookPayload } from '../helpers/stripeWebhook'
import { projectDayOffset } from '../helpers/projectWindow'

/**
 * Flow 2 — Payment webhook → booking confirmed.
 *
 * Mechanics:
 *   1. Authenticate as the seeded verified guest and create a real PENDING
 *      booking through the public v1 API (login → pick an available room →
 *      POST /bookings). No backend test harness / seed endpoint is required.
 *   2. POST a signed Stripe-style `payment_intent.succeeded` event to our own
 *      webhook endpoint with the test secret CI uses.
 *   3. Read the booking back through the owner's API and assert it flipped to
 *      `confirmed`.
 *
 * The webhook handler is exercised end-to-end over HTTP, so this flow validates
 * signature verification + confirmation together. Confirmation is synchronous —
 * StripeWebhookController::handlePaymentIntentSucceeded applies the booking
 * state change inline (no queue worker) — so step 3 needs no polling.
 *
 * NOTE: requests use full URLs (`${apiBase}${path}`), not Playwright's
 * `baseURL`. With `baseURL` set to ".../api", a leading-slash path resolves via
 * `new URL()` and DROPS the "/api" segment (→ 404). Building the URL explicitly
 * avoids that.
 */
// @smoke — gates every PR via .github/workflows/e2e.yml. Owns the
// payment-webhook contract: signature verification + booking confirmation
// must stay green; failures here are a hard merge blocker.
test.describe('Payment webhook @smoke', () => {
  const apiBase = (process.env.E2E_API_BASE ?? 'http://localhost:8000/api').replace(/\/+$/, '')
  const webhookSecret = process.env.STRIPE_WEBHOOK_SECRET ?? 'whsec_e2e_test_secret'

  // Seeded by DevRolePreviewSeeder (see .github/workflows/e2e.yml bootstrap).
  const TEST_USER = { email: 'user@soleil.test', password: 'P@ssworD!123' }

  test('payment_intent.succeeded → booking becomes confirmed @smoke', async () => {
    // Bound the run so a missing element fails fast instead of burning the
    // 25-minute global timeout in playwright.config.ts.
    test.setTimeout(60_000)

    const ctx = await playwrightRequest.newContext()
    const url = (path: string): string => `${apiBase}${path}`

    try {
      // 1. Authenticate as the seeded verified guest (Bearer token). The
      //    booking endpoints require check_token_valid + verified.
      const token = await loginBearer(ctx, apiBase, TEST_USER.email, TEST_USER.password)
      const authHeaders = { authorization: `Bearer ${token}` }

      // 2. Create a pending booking through the REAL booking API. Dates sit far
      //    past both the seeded preview bookings (≤ +22d) and the guest-booking
      //    flow so the booking exclusion constraint never bites this room/window.
      //    The per-project offset keeps the 4 nightly projects from exhausting the
      //    3 seeded rooms on the same window. The availability filter guarantees
      //    the room is bookable.
      const base = 120 + projectDayOffset()
      const checkIn = isoDate(base)
      const checkOut = isoDate(base + 2)

      const roomsResp = await ctx.get(url('/v1/rooms'), {
        params: { check_in: checkIn, check_out: checkOut },
      })
      expect(roomsResp.ok(), 'rooms list must load').toBeTruthy()
      const roomId = (await roomsResp.json()).data?.[0]?.id as number | undefined
      expect(roomId, 'an available room must exist for the booking window').toBeTruthy()

      const bookingResp = await ctx.post(url('/v1/bookings'), {
        headers: authHeaders,
        data: {
          room_id: roomId,
          check_in: checkIn,
          check_out: checkOut,
          guest_name: 'E2E Webhook Guest',
          guest_email: 'e2e-webhook@example.com',
        },
      })
      expect(bookingResp.ok(), 'pending booking creation must succeed').toBeTruthy()
      const created = (await bookingResp.json()).data
      const bookingId = created.id as number
      expect(created.status, 'a fresh booking starts pending').toBe('pending')

      // 2b. Attach a PaymentIntent through the REAL payment endpoint so the
      //     booking carries the payment_intent_id the webhook addresses. In
      //     APP_ENV=testing StripeService returns a deterministic fake intent
      //     whose client_secret is `${id}_secret_test`; recovering the id from
      //     that response keeps the test decoupled from the backend's internal
      //     idempotency-key hashing. (Re-deriving the id here is exactly how
      //     this flow first rotted: the key format changed from
      //     `booking_payment_intent_{id}` to `booking:{id}:payment_intent:create:v1`.)
      const intentResp = await ctx.post(url(`/v1/bookings/${bookingId}/payment-intent`), {
        headers: authHeaders,
      })
      expect(
        intentResp.ok(),
        `payment-intent creation must succeed (got ${intentResp.status()}): ${await intentResp.text()}`
      ).toBeTruthy()
      const clientSecret = (await intentResp.json()).data?.client_secret as string | undefined
      expect(clientSecret, 'payment-intent response must carry a client_secret').toBeTruthy()
      const paymentIntentId = (clientSecret as string).replace(/_secret_test$/, '')

      // Read the booking back so the webhook payload mirrors the amount +
      // currency the handler validates against. assertPaymentIntentMatchesBooking
      // is strict — amount, currency, and metadata.booking_id all must match the
      // booking — so a bare { id, status } payload is rejected with a 500.
      const payableResp = await ctx.get(url(`/v1/bookings/${bookingId}`), { headers: authHeaders })
      expect(payableResp.ok(), 'owner can read their own booking').toBeTruthy()
      const payable = (await payableResp.json()).data
      const amount = payable.amount as number
      const currency = payable.payment_currency as string

      // 3. POST a signed payment_intent.succeeded event to our own webhook.
      //    Sign the EXACT raw body string we transmit: Playwright sends a string
      //    `data` verbatim, so the bytes the backend verifies are the bytes we
      //    signed. (Signing this string but sending `data: <object>` would make
      //    Playwright re-serialize and break verification.)
      const rawPayload = JSON.stringify({
        id: `evt_e2e_${Date.now()}`,
        type: 'payment_intent.succeeded',
        data: {
          object: {
            id: paymentIntentId,
            status: 'succeeded',
            amount,
            currency,
            metadata: { booking_id: String(bookingId) },
          },
        },
      })
      const stripeSignature = signStripeWebhookPayload(rawPayload, webhookSecret)

      const webhookResp = await ctx.post(url('/webhooks/stripe'), {
        headers: {
          'content-type': 'application/json',
          'stripe-signature': stripeSignature,
        },
        data: rawPayload,
      })
      // Surface status + body on failure so a rejected/500 webhook is
      // self-diagnosing in CI instead of just "expected < 300, got 500".
      expect(
        webhookResp.status(),
        `webhook must accept signed payload (got ${webhookResp.status()}): ${await webhookResp.text()}`
      ).toBeLessThan(300)

      // 4. The booking — read back through the owner's API — is now confirmed.
      const confirmResp = await ctx.get(url(`/v1/bookings/${bookingId}`), { headers: authHeaders })
      expect(confirmResp.ok(), 'owner can read their own booking').toBeTruthy()
      expect((await confirmResp.json()).data.status).toBe('confirmed')
    } finally {
      await ctx.dispose()
    }
  })
})

/** Log in via the Bearer endpoint and return the personal access token. */
async function loginBearer(
  ctx: APIRequestContext,
  apiBase: string,
  email: string,
  password: string
): Promise<string> {
  const resp = await ctx.post(`${apiBase}/auth/login-v2`, { data: { email, password } })
  expect(resp.ok(), 'login must succeed').toBeTruthy()
  // login-v2 wraps its payload in the ApiResponse envelope: { success, data: { token, ... } }.
  const token = (await resp.json()).data?.token as string | undefined
  expect(token, 'login response must carry a bearer token').toBeTruthy()
  return token as string
}

/** YYYY-MM-DD `daysFromToday` days out, in UTC. */
function isoDate(daysFromToday: number): string {
  const d = new Date()
  d.setDate(d.getDate() + daysFromToday)
  return d.toISOString().slice(0, 10)
}
