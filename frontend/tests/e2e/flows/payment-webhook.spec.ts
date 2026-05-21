import {
  test,
  expect,
  request as playwrightRequest,
  type APIRequestContext,
} from '@playwright/test'
import crypto from 'node:crypto'

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
      //    flow (+45d) so the booking exclusion constraint never bites this
      //    room/window. The availability filter guarantees the room is bookable.
      const checkIn = isoDate(120)
      const checkOut = isoDate(122)

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

      // In APP_ENV=testing the booking flow attaches a deterministic fake
      // PaymentIntent id rather than calling Stripe, so we can derive the id
      // the create flow stored on the booking and address the webhook to it.
      const paymentIntentId = fakePaymentIntentId(bookingId)

      // 3. POST a signed payment_intent.succeeded event to our own webhook.
      const payload = JSON.stringify({
        id: `evt_e2e_${Date.now()}`,
        type: 'payment_intent.succeeded',
        data: { object: { id: paymentIntentId, status: 'succeeded' } },
      })
      const timestamp = Math.floor(Date.now() / 1000)
      const signature = crypto
        .createHmac('sha256', webhookSecret)
        .update(`${timestamp}.${payload}`)
        .digest('hex')

      const webhookResp = await ctx.post(url('/webhooks/stripe'), {
        headers: {
          'content-type': 'application/json',
          'stripe-signature': `t=${timestamp},v1=${signature}`,
        },
        data: payload,
      })
      expect(webhookResp.status(), 'webhook must accept signed payload').toBeLessThan(300)

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

/**
 * Mirror of StripeService::createPaymentIntent's testing fake:
 *   pi_test_{id}_{first 12 hex of sha256("booking_payment_intent_{id}")}
 * Kept in sync with backend/app/Services/StripeService.php. If that fake or its
 * idempotency-key format changes, the final assertion fails loudly (the webhook
 * would target a non-existent PaymentIntent and the booking stays pending).
 */
function fakePaymentIntentId(bookingId: number): string {
  const idempotencyKey = `booking_payment_intent_${bookingId}`
  const suffix = crypto.createHash('sha256').update(idempotencyKey).digest('hex').slice(0, 12)
  return `pi_test_${bookingId}_${suffix}`
}

/** YYYY-MM-DD `daysFromToday` days out, in UTC. */
function isoDate(daysFromToday: number): string {
  const d = new Date()
  d.setDate(d.getDate() + daysFromToday)
  return d.toISOString().slice(0, 10)
}
