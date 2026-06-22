import { Page, expect } from '@playwright/test'

export interface GuestDetails {
  name: string
  email: string
}

// Bound each interaction so a selector that no longer matches the app fails in
// seconds with a clear "element not found" instead of silently auto-waiting to
// the test-level timeout. A button rename ("Xác nhận đặt phòng" -> "Giữ phòng
// và thanh toán") previously burned the full 90s as a `timedOut` with no signal
// about which step broke.
const ACTION_TIMEOUT = 15_000

/**
 * Booking form (/booking, behind ProtectedRoute).
 *
 * The room is pre-selected from the room_id query param, so the guest only
 * supplies stay dates + contact details. Submitting ("Giữ phòng và thanh toán")
 * creates the booking (POST /v1/bookings) and then moves to the Stripe payment
 * step.
 *
 * The smoke flow asserts the booking was CREATED (the real "guest can book"
 * guarantee), not that the Stripe payment UI rendered: the testing-mode backend
 * returns a fake PaymentIntent whose client_secret is not Stripe's required
 * `${id}_secret_${secret}` format, so real Stripe Elements throws on mount.
 * Payment confirmation is owned by payment-webhook.spec.ts at the API/webhook
 * layer.
 */
export class BookingFormPage {
  constructor(private readonly page: Page) {}

  async fillStayDates(checkIn: string, checkOut: string): Promise<void> {
    // check_in first: BookingForm auto-nudges check_out forward when it is
    // empty or <= check_in, so set check_out last to lock in our value.
    await this.page
      .getByLabel('Ngày nhận phòng', { exact: true })
      .fill(checkIn, { timeout: ACTION_TIMEOUT })
    await this.page
      .getByLabel('Ngày trả phòng', { exact: true })
      .fill(checkOut, { timeout: ACTION_TIMEOUT })
  }

  async fillGuestDetails(details: GuestDetails): Promise<void> {
    await this.page
      .getByLabel('Họ và tên', { exact: true })
      .fill(details.name, { timeout: ACTION_TIMEOUT })
    await this.page
      .getByLabel('Địa chỉ email', { exact: true })
      .fill(details.email, { timeout: ACTION_TIMEOUT })
  }

  /**
   * Click "Giữ phòng và thanh toán" and assert the booking was created
   * server-side. Waiting on the POST /v1/bookings response (rather than a
   * post-submit UI state) keeps the assertion deterministic and independent of
   * the Stripe payment step, which cannot render against the testing fake.
   */
  async submitExpectingBookingCreated(): Promise<void> {
    const [response] = await Promise.all([
      this.page.waitForResponse(
        response =>
          /\/v1\/bookings(\?|$)/.test(response.url()) && response.request().method() === 'POST',
        { timeout: ACTION_TIMEOUT }
      ),
      this.page
        .getByRole('button', { name: /giữ phòng và thanh toán/i })
        .click({ timeout: ACTION_TIMEOUT }),
    ])
    expect(response.status(), `booking creation must return 201, got ${response.status()}`).toBe(
      201
    )
  }
}
