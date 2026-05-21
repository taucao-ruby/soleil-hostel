import { Page, Locator, expect } from '@playwright/test'

/**
 * Admin dashboard — lives at `/admin` (router index of the admin section).
 *
 * Real DOM (see src/features/admin/AdminDashboard.tsx):
 *   - Tabs are `role="tab"` buttons with Vietnamese labels: "Đặt phòng"
 *     (bookings — shows ALL bookings incl. trashed), "Đã xóa" (trashed,
 *     admin-only), "Liên hệ" (contacts). Only the active tab's panel is
 *     mounted (`role="tabpanel"`).
 *   - Each booking renders in an `<article>` whose text contains "ĐP #{id}"
 *     immediately followed by the status label (e.g. "ĐP #9Chờ xác nhận").
 *   - The trashed tab exposes a "Khôi phục" (restore) button per row; restore
 *     is immediate (no confirmation dialog).
 */
export class AdminDashboardPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/admin')
  }

  async gotoBookings(): Promise<void> {
    await this.selectTab('Đặt phòng')
  }

  async gotoTrashed(): Promise<void> {
    await this.selectTab('Đã xóa')
  }

  /** Navigate to /admin and switch to a tab, waiting until it is selected. */
  private async selectTab(name: string): Promise<void> {
    await this.goto()
    const tab = this.page.getByRole('tab', { name })
    await tab.click()
    // Guard against a click that lands before React wires the handler: the
    // assertion retries until aria-selected flips, so the panel is mounted
    // before callers query it.
    await expect(tab).toHaveAttribute('aria-selected', 'true')
  }

  /**
   * The booking card for `bookingId`, scoped to the active tab panel (only one
   * panel is mounted at a time). `(?!\d)` stops "ĐP #9" from matching "ĐP #90";
   * a plain `\b` fails because the id is glued to the status word ("#9Chờ…").
   */
  private bookingRow(bookingId: number): Locator {
    return this.page
      .getByRole('tabpanel')
      .locator('article')
      .filter({ hasText: new RegExp(`ĐP #${bookingId}(?!\\d)`) })
  }

  async restoreBooking(bookingId: number): Promise<void> {
    await this.bookingRow(bookingId).getByRole('button', { name: 'Khôi phục' }).click()
  }

  async expectBookingPresent(bookingId: number): Promise<void> {
    await expect(this.bookingRow(bookingId)).toBeVisible({ timeout: 10_000 })
  }

  async expectBookingAbsent(bookingId: number): Promise<void> {
    await expect(this.bookingRow(bookingId)).toHaveCount(0)
  }
}
