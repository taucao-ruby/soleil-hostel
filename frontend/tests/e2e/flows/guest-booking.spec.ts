import { test } from '@playwright/test'
import { LoginPage } from '../pages/LoginPage'
import { RoomsPage } from '../pages/RoomsPage'
import { BookingFormPage } from '../pages/BookingFormPage'

/**
 * Flow 1 — Guest booking happy path (authenticated).
 *
 * Booking is gated behind ProtectedRoute (router.tsx), so there is no
 * anonymous flow — the guest signs in first.
 *
 * Pre-conditions (seeded by DevRolePreviewSeeder):
 *   - Verified user user@soleil.test / P@ssworD!123 exists.
 *   - At least one bookable room exists at the default location.
 *
 * Assertions:
 *   - Login redirects to the dashboard.
 *   - /rooms exposes a bookable room card whose CTA opens the booking form.
 *   - Submitting the form surfaces the success confirmation + booking ref.
 */
const TEST_USER = {
  email: 'user@soleil.test',
  password: 'P@ssworD!123',
}

// @smoke — gates every PR via .github/workflows/e2e.yml. Owns the booking
// happy-path: any regression here is a hard merge blocker.
test.describe('Guest booking @smoke', () => {
  test('logs in → picks room → completes form → sees confirmation @smoke', async ({ page }) => {
    // Bound the run so a missing element fails fast (~90s) instead of burning
    // the 25-minute global timeout in playwright.config.ts.
    test.setTimeout(90_000)

    const login = new LoginPage(page)
    const rooms = new RoomsPage(page)
    const form = new BookingFormPage(page)

    await login.goto()
    await login.login(TEST_USER.email, TEST_USER.password)

    await rooms.goto()
    await rooms.bookFirstAvailableRoom()

    // Dates well past the seeded preview bookings (which sit within ~3 weeks of
    // today) to avoid the booking exclusion constraint biting this flow.
    const today = new Date()
    const checkIn = addDays(today, 45)
    const checkOut = addDays(today, 47)

    await form.fillStayDates(checkIn, checkOut)
    await form.fillGuestDetails({ name: 'E2E Guest', email: 'e2e-guest@example.com' })
    await form.submit()
    await form.expectConfirmation()
  })
})

function addDays(d: Date, n: number): string {
  const copy = new Date(d.getTime())
  copy.setDate(copy.getDate() + n)
  return copy.toISOString().slice(0, 10)
}
