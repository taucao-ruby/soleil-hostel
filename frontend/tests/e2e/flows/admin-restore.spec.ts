import { test, expect, request as playwrightRequest } from '@playwright/test'
import { AdminDashboardPage } from '../pages/AdminDashboardPage'

/**
 * Flow 4 — Admin soft-deletes booking, then restores from trashed view.
 *
 * Mechanics:
 *   1. Seed a confirmed booking + soft-delete it via the test API (the UI
 *      delete path is covered separately; this flow is about restore).
 *   2. Login as admin, open trashed tab, click restore.
 *   3. Switch to active tab — booking is back; trashed list — booking gone.
 */
test.describe('Admin restore', () => {
  const apiBase = process.env.E2E_API_BASE ?? 'http://localhost:8000/api'

  test('soft-deleted booking → trashed list → restore → reappears active', async ({ page }) => {
    const ctx = await playwrightRequest.newContext({ baseURL: apiBase })
    const seedResp = await ctx.post('/v1/__e2e/seed-trashed-booking', {
      data: { dates_offset_days: 28 },
    })
    expect(seedResp.ok(), 'trashed booking seed must succeed').toBeTruthy()
    const { booking_id } = await seedResp.json()

    // Sign in as admin via the test login helper. The SPA reads the cookie
    // from the response and proceeds; the helper is part of the e2e harness.
    await page.goto('/test/login-as-admin')

    const dash = new AdminDashboardPage(page)
    await dash.gotoTrashed()
    await dash.expectBookingPresent(booking_id)
    await dash.restoreBooking(booking_id)

    // After restore: gone from trashed
    await dash.gotoTrashed()
    await dash.expectBookingAbsent(booking_id)

    // …and visible in active
    await dash.gotoActive()
    await dash.expectBookingPresent(booking_id)
  })
})
