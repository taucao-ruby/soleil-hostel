import { Page, expect } from '@playwright/test'

/**
 * Admin dashboard — the trashed/active booking views live here.
 *
 * Expected tabs (data-testid):
 *   - admin-tab-active
 *   - admin-tab-trashed
 *   - admin-tab-contacts
 *
 * Each booking row exposes data-testid="booking-row-{id}".
 */
export class AdminDashboardPage {
  constructor(private readonly page: Page) {}

  async gotoTrashed(): Promise<void> {
    await this.page.goto('/admin/dashboard')
    await this.page.getByTestId('admin-tab-trashed').click()
  }

  async gotoActive(): Promise<void> {
    await this.page.goto('/admin/dashboard')
    await this.page.getByTestId('admin-tab-active').click()
  }

  async expectBookingStatus(bookingId: number, status: string): Promise<void> {
    const row = this.page.getByTestId(`booking-row-${bookingId}`)
    await expect(row).toContainText(new RegExp(status, 'i'), { timeout: 10_000 })
  }

  async restoreBooking(bookingId: number): Promise<void> {
    const row = this.page.getByTestId(`booking-row-${bookingId}`)
    await row.getByRole('button', { name: /khôi phục|restore/i }).click()
    await this.page.getByRole('button', { name: /xác nhận|confirm/i }).click()
  }

  async expectBookingPresent(bookingId: number): Promise<void> {
    await expect(this.page.getByTestId(`booking-row-${bookingId}`)).toBeVisible()
  }

  async expectBookingAbsent(bookingId: number): Promise<void> {
    await expect(this.page.getByTestId(`booking-row-${bookingId}`)).toHaveCount(0)
  }
}
