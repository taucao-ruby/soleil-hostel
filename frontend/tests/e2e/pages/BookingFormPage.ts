import { Page, expect } from '@playwright/test'

export interface GuestDetails {
  name: string
  email: string
  phone?: string
}

export class BookingFormPage {
  constructor(private readonly page: Page) {}

  async fillGuestDetails(details: GuestDetails): Promise<void> {
    await this.page.getByLabel(/tên|name/i).fill(details.name)
    await this.page.getByLabel(/email/i).fill(details.email)
    if (details.phone) {
      await this.page.getByLabel(/sđt|phone/i).fill(details.phone)
    }
  }

  async submit(): Promise<void> {
    await this.page.getByRole('button', { name: /xác nhận|confirm/i }).click()
  }

  async expectConfirmation(): Promise<void> {
    await expect(this.page.getByText(/đặt phòng thành công|booking confirmed/i)).toBeVisible({
      timeout: 10_000,
    })
  }
}
