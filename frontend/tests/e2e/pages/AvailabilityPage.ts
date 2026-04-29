import { Page, expect } from '@playwright/test'

/**
 * Public availability landing — guests browse rooms by date here.
 *
 * Expected page surface (selectors keyed off testIds in the SPA):
 *   - SearchCard with check-in / check-out date pickers
 *   - Room result cards exposing data-testid="room-card"
 *   - Each card has a "Đặt phòng" CTA → /bookings/new?room_id=...
 */
export class AvailabilityPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/locations')
  }

  async searchDates(checkIn: string, checkOut: string): Promise<void> {
    await this.page.getByLabel(/check.?in/i).fill(checkIn)
    await this.page.getByLabel(/check.?out/i).fill(checkOut)
    await this.page.getByRole('button', { name: /tìm phòng|search/i }).click()
  }

  async pickFirstRoom(): Promise<void> {
    const card = this.page.getByTestId('room-card').first()
    await expect(card).toBeVisible()
    await card.getByRole('link', { name: /đặt phòng|book/i }).click()
  }
}
