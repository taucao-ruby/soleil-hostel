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

  async bookFirstAvailableRoom(): Promise<void> {
    const cta = this.page.getByRole('button', { name: /đặt ngay/i }).first()
    // Room cards arrive via an API fetch; allow for a cold backend first paint.
    await expect(cta).toBeVisible({ timeout: 15_000 })
    await cta.click()
    await this.page.waitForURL('**/booking**', { timeout: 15_000 })
  }
}
