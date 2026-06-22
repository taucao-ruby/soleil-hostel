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
 * creates a HELD (pending) booking and advances to the secure payment step
 * (data-testid="payment-step", "Đã giữ phòng tạm thời"). Completing the Stripe
 * payment — and the resulting confirmation — is owned at the API/webhook layer
 * by payment-webhook.spec.ts, so this UI flow asserts the room-held state only
 * (CI runs without a real Stripe card iframe).
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

  async submit(): Promise<void> {
    await this.page
      .getByRole('button', { name: /giữ phòng và thanh toán/i })
      .click({ timeout: ACTION_TIMEOUT })
  }

  /**
   * The booking was created and the room is held: the form advances to the
   * secure payment step. We assert the held state rather than a completed
   * payment because CI runs without a real Stripe card iframe.
   */
  async expectRoomHeld(): Promise<void> {
    await expect(this.page.getByTestId('payment-step')).toBeVisible({ timeout: ACTION_TIMEOUT })
    await expect(this.page.getByText('Đã giữ phòng tạm thời')).toBeVisible()
  }
}
