import { test, expect } from '@playwright/test'
import { AvailabilityPage } from '../pages/AvailabilityPage'
import { BookingFormPage } from '../pages/BookingFormPage'

/**
 * Flow 1 — Guest booking happy path.
 *
 * Pre-conditions (seeded by backend test seeder):
 *   - At least one bookable room exists at default location.
 *   - A verified test guest account is logged in (or anonymous booking is enabled).
 *
 * Assertions:
 *   - Availability page renders room cards.
 *   - Booking form accepts guest details.
 *   - Confirmation surface appears within 10s of submit.
 */
// @smoke — gates every PR via .github/workflows/e2e.yml. Owns the booking
// happy-path: any regression here is a hard merge blocker.
test.describe('Guest booking @smoke', () => {
  test('lands → selects room → completes form → sees confirmation @smoke', async ({ page }) => {
    const availability = new AvailabilityPage(page)
    const form = new BookingFormPage(page)

    await availability.goto()

    // Use a date pair safely in the future and disjoint from other E2E flows
    // to avoid the exclusion constraint biting parallel runs.
    const today = new Date()
    const checkIn = addDays(today, 14)
    const checkOut = addDays(today, 16)

    await availability.searchDates(checkIn, checkOut)
    await availability.pickFirstRoom()

    await form.fillGuestDetails({
      name: 'E2E Guest',
      email: 'e2e-guest@example.com',
      phone: '0900000001',
    })
    await form.submit()
    await form.expectConfirmation()

    // Confirmation page must include the dates we picked so the surface is
    // genuinely the booking we just created (and not a stale render).
    await expect(page.getByText(checkIn)).toBeVisible()
    await expect(page.getByText(checkOut)).toBeVisible()
  })
})

function addDays(d: Date, n: number): string {
  const copy = new Date(d.getTime())
  copy.setDate(copy.getDate() + n)
  return copy.toISOString().slice(0, 10)
}
