import crypto from 'node:crypto'

/**
 * Build a Stripe-compatible webhook signature header for `rawBody`.
 *
 * The HMAC is computed over the EXACT bytes the caller will transmit, so the
 * caller MUST send this same `rawBody` string verbatim — never a re-encoded
 * object. (Playwright's APIRequestContext JSON-serializes an object `data` but
 * sends a string `data` as-is; signing `JSON.stringify(payload)` and then
 * sending `data: payload` would transmit different bytes than were signed.)
 *
 * Mirrors Stripe's scheme, which the backend validates via
 * `Stripe\Webhook::constructEvent`:
 *   signed_payload = "{timestamp}.{rawBody}"
 *   header         = "t={timestamp},v1={hmac_sha256(secret, signed_payload)}"
 */
export function signStripeWebhookPayload(rawBody: string, secret: string): string {
  if (!secret) {
    throw new Error(
      'STRIPE_WEBHOOK_SECRET is required to sign Stripe webhook payloads for the E2E test'
    )
  }

  const timestamp = Math.floor(Date.now() / 1000)
  const signature = crypto
    .createHmac('sha256', secret)
    .update(`${timestamp}.${rawBody}`, 'utf8')
    .digest('hex')

  return `t=${timestamp},v1=${signature}`
}
