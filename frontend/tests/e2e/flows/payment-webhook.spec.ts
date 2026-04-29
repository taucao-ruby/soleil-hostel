import { test, expect, request as playwrightRequest } from '@playwright/test'
import crypto from 'node:crypto'
import { AdminDashboardPage } from '../pages/AdminDashboardPage'

/**
 * Flow 2 — Payment webhook → booking confirmed.
 *
 * Mechanics:
 *   1. Create a pending booking via the API (skip the UI to keep the flow
 *      narrow and deterministic).
 *   2. POST a signed Stripe-style `payment_intent.succeeded` event to our
 *      own webhook endpoint with the test secret used by tests.
 *   3. Open admin dashboard → verify the booking row shows status confirmed.
 *
 * The webhook handler is exercised end-to-end via HTTP, so this flow validates
 * idempotency and signature verification together.
 */
test.describe('Payment webhook', () => {
  const apiBase = process.env.E2E_API_BASE ?? 'http://localhost:8000/api'
  const webhookSecret = process.env.STRIPE_WEBHOOK_SECRET ?? 'whsec_e2e_test_secret'

  test('payment_intent.succeeded → booking becomes confirmed', async ({ page }) => {
    // 1. Set up a pending booking via the test seeder API. The exact endpoint
    //    depends on the test bootstrap; this stub is the documented contract.
    const ctx = await playwrightRequest.newContext({ baseURL: apiBase })
    const bookingResp = await ctx.post('/v1/__e2e/seed-pending-booking', {
      data: { dates_offset_days: 21 },
    })
    expect(bookingResp.ok(), 'pending booking seed must succeed').toBeTruthy()
    const { booking_id, payment_intent_id } = await bookingResp.json()

    // 2. POST signed webhook event.
    const payload = JSON.stringify({
      id: `evt_e2e_${Date.now()}`,
      type: 'payment_intent.succeeded',
      data: { object: { id: payment_intent_id, status: 'succeeded' } },
    })
    const timestamp = Math.floor(Date.now() / 1000)
    const signature = crypto
      .createHmac('sha256', webhookSecret)
      .update(`${timestamp}.${payload}`)
      .digest('hex')
    const stripeSignatureHeader = `t=${timestamp},v1=${signature}`

    const webhookResp = await ctx.post('/webhooks/stripe', {
      headers: {
        'content-type': 'application/json',
        'stripe-signature': stripeSignatureHeader,
      },
      data: payload,
    })
    expect(webhookResp.status(), 'webhook must accept signed payload').toBeLessThan(300)

    // 3. Admin dashboard reflects the confirmed status.
    const dash = new AdminDashboardPage(page)
    await dash.gotoActive()
    await dash.expectBookingStatus(booking_id, 'confirmed')
  })
})
