import { Page, Locator, expect } from '@playwright/test'

const ACTION_TIMEOUT = 15_000

/**
 * Admin bookings — the dashboard was redesigned from one tabbed page into a
 * sidebar layout with dedicated sub-routes (the old role="tab" / role="tabpanel"
 * selectors no longer exist):
 *
 *   - /admin/bookings          active bookings (AdminBookingTable). On desktop the
 *                              row is a <tr> carrying a "Xem" link to
 *                              /admin/bookings/{id} — a stable, id-based anchor.
 *   - /admin/bookings/trashed  soft-deleted bookings (TrashedBookings). Each row is
 *                              an <article> "ĐP #{id}" with an admin-only "Khôi
 *                              phục" (restore) button; restore is immediate.
 *
 * Navigation is by route; rows are matched by booking id (stable) rather than by
 * reference/label text that drifts with redesigns.
 */
export class AdminDashboardPage {
  constructor(private readonly page: Page) {}

  async gotoTrashed(): Promise<void> {
    await this.page.goto('/admin/bookings/trashed')
    await expect(this.page.getByRole('heading', { name: 'Đặt phòng đã xóa' })).toBeVisible({
      timeout: ACTION_TIMEOUT,
    })
  }

  async gotoBookings(): Promise<void> {
    await this.page.goto('/admin/bookings')
    // exact:true so this heading ("Đặt phòng") is not satisfied by the trashed
    // page's "Đặt phòng đã xóa".
    await expect(this.page.getByRole('heading', { name: 'Đặt phòng', exact: true })).toBeVisible({
      timeout: ACTION_TIMEOUT,
    })
  }

  /** Trashed row: an <article> whose "ĐP #{id}" is not a prefix of a longer id. */
  private trashedRow(bookingId: number): Locator {
    return this.page.locator('article').filter({ hasText: new RegExp(`ĐP #${bookingId}(?!\\d)`) })
  }

  /** Active-list anchor: the per-row "Xem" detail link → /admin/bookings/{id}. */
  private activeRowLink(bookingId: number): Locator {
    return this.page.locator(`a[href="/admin/bookings/${bookingId}"]`)
  }

  async restoreBooking(bookingId: number): Promise<void> {
    await this.trashedRow(bookingId)
      .getByRole('button', { name: 'Khôi phục' })
      .click({ timeout: ACTION_TIMEOUT })
  }

  async expectBookingInTrashed(bookingId: number): Promise<void> {
    await expect(this.trashedRow(bookingId)).toBeVisible({ timeout: ACTION_TIMEOUT })
  }

  async expectBookingNotInTrashed(bookingId: number): Promise<void> {
    await expect(this.trashedRow(bookingId)).toHaveCount(0)
  }

  async expectBookingInActiveList(bookingId: number): Promise<void> {
    await expect(this.activeRowLink(bookingId)).toBeVisible({ timeout: ACTION_TIMEOUT })
  }
}
