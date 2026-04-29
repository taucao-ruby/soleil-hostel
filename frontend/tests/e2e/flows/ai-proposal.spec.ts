import { test, expect } from '@playwright/test'
import { AvailabilityPage } from '../pages/AvailabilityPage'
import { AiProposalWidget } from '../pages/AiProposalWidget'

/**
 * Flow 3 — AI proposal acceptance creates a booking.
 *
 * Pre-conditions:
 *   - AI harness Redis flag is on (`feature:toggle ai_harness.enabled on`).
 *   - The signed-in user is a guest with a verified email.
 *
 * Assertions:
 *   - The natural-language query returns a proposal card.
 *   - Confirming the proposal triggers booking creation (status surfaced
 *     by the post-confirm toast / banner).
 *
 * The flow asserts the *user-visible* outcome only — backend integration
 * (proposer_user_id binding, audit row insertion, etc.) is covered by the
 * Pest feature tests.
 */
test.describe('AI proposal', () => {
  test.skip(
    process.env.E2E_AI_HARNESS_ENABLED !== 'true',
    'AI harness disabled — set E2E_AI_HARNESS_ENABLED=true to run this flow.'
  )

  test('NL query → proposal renders → confirm → booking created', async ({ page }) => {
    const availability = new AvailabilityPage(page)
    const widget = new AiProposalWidget(page)

    await availability.goto()
    await widget.open()
    await widget.submitQuery('Tôi muốn đặt phòng đôi cuối tuần này')
    await widget.expectProposalRendered()
    await widget.confirmProposal()
    await widget.expectBookingCreated()

    // Sanity: a confirmation toast that includes the booking number proves we
    // actually wrote a row, not just rendered an optimistic UI state.
    await expect(page.getByText(/booking #\d+/i)).toBeVisible({ timeout: 10_000 })
  })
})
