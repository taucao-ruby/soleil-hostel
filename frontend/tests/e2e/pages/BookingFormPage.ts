import { Page, expect } from '@playwright/test'

export interface GuestDetails {
  name: string
  email: string
}

/**
 * Booking form (/booking, behind ProtectedRoute).
 *
 * The room is pre-selected from the room_id query param, so the guest only
 * supplies stay dates + contact details. On success BookingForm renders a
 * data-testid="success-message" surface (heading "Đặt phòng thành công!" plus
 * a booking reference) before auto-redirecting to /dashboard after ~2s.
 */
export class BookingFormPage {
  constructor(private readonly page: Page) {}

  async fillStayDates(checkIn: string, checkOut: string): Promise<void> {
    // check_in first: BookingForm auto-nudges check_out forward when it is
    // empty or <= check_in, so set check_out last to lock in our value.
    await this.page.getByLabel('Ngày nhận phòng', { exact: true }).fill(checkIn)
    await this.page.getByLabel('Ngày trả phòng', { exact: true }).fill(checkOut)
  }

  async fillGuestDetails(details: GuestDetails): Promise<void> {
    await this.page.getByLabel('Họ và tên', { exact: true }).fill(details.name)
    await this.page.getByLabel('Địa chỉ email', { exact: true }).fill(details.email)
  }

  async submit(): Promise<void> {
    await this.page.getByRole('button', { name: /xác nhận đặt phòng/i }).click()
  }

  async expectConfirmation(): Promise<void> {
    await expect(this.page.getByTestId('success-message')).toBeVisible({ timeout: 15_000 })
    await expect(this.page.getByTestId('booking-reference')).toBeVisible()
  }
}
