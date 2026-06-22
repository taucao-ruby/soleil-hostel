import { Page, expect } from '@playwright/test'

/**
 * Public rooms listing (/rooms).
 *
 * Each available room renders a data-testid="room-card" whose "Đặt ngay" CTA
 * navigates to /booking?room_id=... (RoomList only renders the CTA for rooms
 * with status === 'available').
 */
export class RoomsPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/rooms')
  }

  /**
   * Navigate to /rooms CLIENT-SIDE via the header "Phòng" link instead of a full
   * page load. A `page.goto('/rooms')` after login remounts the SPA and re-runs
   * the AuthContext /me-httponly check; on the CI built-in server that request
   * intermittently hangs on the slower browsers, stranding the next protected
   * route ("/booking") on "Đang kiểm tra phiên đăng nhập...". Staying client-side
   * keeps the already-resolved auth state, so no re-check happens.
   *
   * Works on both layouts: the desktop nav link is visible directly; on mobile
   * (<768px, e.g. Pixel 5) the links live behind the "Mở menu" hamburger.
   */
  async gotoViaNav(): Promise<void> {
    const desktopLink = this.page
      .getByRole('navigation', { name: 'Điều hướng chính' })
      .getByRole('link', { name: 'Phòng', exact: true })

    if (await desktopLink.isVisible()) {
      await desktopLink.click()
    } else {
      await this.page.getByRole('button', { name: 'Mở menu' }).click()
      await this.page
        .getByRole('navigation', { name: 'Điều hướng di động' })
        .getByRole('link', { name: 'Phòng', exact: true })
        .click()
    }

    await this.page.waitForURL('**/rooms', { timeout: 15_000 })
  }

  async bookFirstAvailableRoom(): Promise<void> {
    const cta = this.page.getByRole('button', { name: /đặt ngay/i }).first()
    // Room cards arrive via an API fetch; allow for a cold backend first paint.
    await expect(cta).toBeVisible({ timeout: 15_000 })
    await cta.click()
    await this.page.waitForURL('**/booking**', { timeout: 15_000 })
  }
}
