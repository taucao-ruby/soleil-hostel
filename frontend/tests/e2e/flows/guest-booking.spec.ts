import { test } from '@playwright/test'
import { LoginPage } from '../pages/LoginPage'
import { RoomsPage } from '../pages/RoomsPage'
import { BookingFormPage } from '../pages/BookingFormPage'
import { projectDayOffset } from '../helpers/projectWindow'

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
 *   - Submitting ("Giữ phòng và thanh toán") creates the booking server-side
 *     (POST /v1/bookings -> 201). Payment + confirmation are owned by
 *     payment-webhook.spec.ts; this UI flow stops at booking-created because the
 *     testing-mode fake client_secret cannot mount real Stripe Elements.
 */
const TEST_USER = {
  email: 'user@soleil.test',
  password: 'P@ssworD!123',
}

// @smoke — gates every PR via .github/workflows/e2e.yml. Owns the booking
// happy-path: any regression here is a hard merge blocker.
test.describe('Guest booking @smoke', () => {
  test('logs in → picks room → completes form → booking created @smoke', async ({ page }) => {
    // Bound the run so a missing element fails fast instead of burning the
    // 25-minute global timeout in playwright.config.ts. 120s leaves headroom for
    // the one-shot reload recovery in BookingFormPage.waitUntilReady on mobile.
    test.setTimeout(120_000)

    const login = new LoginPage(page)
    const rooms = new RoomsPage(page)
    const form = new BookingFormPage(page)

    await login.goto()
    await login.login(TEST_USER.email, TEST_USER.password)

    await rooms.goto()
    await rooms.bookFirstAvailableRoom()

    // Let the form finish mounting before filling — the Pixel-5 emulation can
    // leave /booking on the auth-check/rooms-load state for a few seconds after
    // navigation, which otherwise raced the first date-field fill.
    await form.waitUntilReady()

    // Dates well past the seeded preview bookings (which sit within ~3 weeks of
    // today), plus a per-project offset so the 4 nightly projects book disjoint
    // windows on the same room instead of colliding on the exclusion constraint.
    const today = new Date()
    const base = 45 + projectDayOffset()
    const checkIn = addDays(today, base)
    const checkOut = addDays(today, base + 2)

    await form.fillStayDates(checkIn, checkOut)
    await form.fillGuestDetails({ name: 'E2E Guest', email: 'e2e-guest@example.com' })
    await form.submitExpectingBookingCreated()
  })
})

function addDays(d: Date, n: number): string {
  const copy = new Date(d.getTime())
  copy.setDate(copy.getDate() + n)
  return copy.toISOString().slice(0, 10)
}
