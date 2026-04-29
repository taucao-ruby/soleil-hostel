import { Page, expect } from '@playwright/test'

/**
 * Room discovery / AI proposal widget — exposed on the availability page when
 * the AI harness feature flag is on.
 */
export class AiProposalWidget {
  constructor(private readonly page: Page) {}

  async open(): Promise<void> {
    await this.page.getByTestId('ai-proposal-trigger').click()
  }

  async submitQuery(query: string): Promise<void> {
    await this.page.getByLabel(/yêu cầu|prompt/i).fill(query)
    await this.page.getByRole('button', { name: /gửi|submit/i }).click()
  }

  async expectProposalRendered(): Promise<void> {
    await expect(this.page.getByTestId('ai-proposal-card')).toBeVisible({ timeout: 15_000 })
  }

  async confirmProposal(): Promise<void> {
    await this.page.getByRole('button', { name: /xác nhận|confirm/i }).click()
  }

  async expectBookingCreated(): Promise<void> {
    await expect(this.page.getByText(/đã tạo đặt phòng|booking created/i)).toBeVisible({
      timeout: 15_000,
    })
  }
}
