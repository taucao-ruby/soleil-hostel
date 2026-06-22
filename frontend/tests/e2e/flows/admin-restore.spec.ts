import {
  test,
  expect,
  request as playwrightRequest,
  type APIRequestContext,
} from '@playwright/test'
import { AdminDashboardPage } from '../pages/AdminDashboardPage'
import { LoginPage } from '../pages/LoginPage'

/**
 * Flow 4 — Admin soft-deletes a booking, then restores it from the trashed view.
 *
 * Mechanics:
 *   1. Seed through the real API (no test-only backend harness): the admin logs
 *      in, creates a booking, then soft-deletes it so it lands in the trashed
 *      list.
 *   2. Sign in through the real /login UI as the seeded admin and open the
 *      "Đã xóa" (trashed) tab.
 *   3. Restore it; confirm it leaves the trashed list and returns to the
 *      bookings list.
 *
 * Re-navigating to /admin between assertions forces a fresh fetch, so the
 * checks read server state rather than optimistic client state.
 *
 * NOTE: API requests use full URLs (`${apiBase}${path}`), not Playwright's
 * `baseURL`. With `baseURL` set to ".../api", a leading-slash path resolves via
 * `new URL()` and DROPS the "/api" segment (→ 404). Building the URL explicitly
 * avoids that.
 */
// Seeded by DevRolePreviewSeeder (see .github/workflows/e2e.yml bootstrap).
const ADMIN = { email: 'admin@soleil.test', password: 'P@ssworD!123' }

test.describe('Admin restore', () => {
  const apiBase = (process.env.E2E_API_BASE ?? 'http://localhost:8000/api').replace(/\/+$/, '')

  test('soft-deleted booking → trashed list → restore → reappears active', async ({ page }) => {
    test.setTimeout(90_000)

    const bookingId = await seedTrashedBooking(apiBase)

    // Sign in through the real login UI as admin (no /test/login-as-admin harness).
    const login = new LoginPage(page)
    await login.goto()
    await login.login(ADMIN.email, ADMIN.password)

    const dash = new AdminDashboardPage(page)
    await dash.gotoTrashed()
    await dash.expectBookingInTrashed(bookingId)
    await dash.restoreBooking(bookingId)

    // After restore: gone from the trashed list…
    await dash.gotoTrashed()
    await dash.expectBookingNotInTrashed(bookingId)

    // …and back in the active bookings list.
    await dash.gotoBookings()
    await dash.expectBookingInActiveList(bookingId)
  })
})

/**
 * Create a booking via the real v1 API as the admin, then soft-delete it so it
 * shows in the trashed list. Returns the booking id.
 */
async function seedTrashedBooking(apiBase: string): Promise<number> {
  const ctx = await playwrightRequest.newContext()
  const url = (path: string): string => `${apiBase}${path}`
  try {
    const token = await loginBearer(ctx, apiBase, ADMIN.email, ADMIN.password)
    const authHeaders = { authorization: `Bearer ${token}` }

    // Far past the seeded preview bookings (≤ +22d) and the other flows
    // (+45d, +120d) so the exclusion constraint never bites this room/window.
    const checkIn = isoDate(150)
    const checkOut = isoDate(152)

    const roomsResp = await ctx.get(url('/v1/rooms'), {
      params: { check_in: checkIn, check_out: checkOut },
    })
    expect(roomsResp.ok(), 'rooms list must load').toBeTruthy()
    const roomId = (await roomsResp.json()).data?.[0]?.id as number | undefined
    expect(roomId, 'an available room must exist for the booking window').toBeTruthy()

    const createResp = await ctx.post(url('/v1/bookings'), {
      headers: authHeaders,
      data: {
        room_id: roomId,
        check_in: checkIn,
        check_out: checkOut,
        guest_name: 'E2E Restore Guest',
        guest_email: 'e2e-restore@example.com',
      },
    })
    expect(createResp.ok(), 'booking creation must succeed').toBeTruthy()
    const bookingId = (await createResp.json()).data.id as number

    const deleteResp = await ctx.delete(url(`/v1/bookings/${bookingId}`), { headers: authHeaders })
    expect(deleteResp.ok(), 'soft-delete must succeed').toBeTruthy()

    return bookingId
  } finally {
    await ctx.dispose()
  }
}

/** Log in via the Bearer endpoint and return the personal access token. */
async function loginBearer(
  ctx: APIRequestContext,
  apiBase: string,
  email: string,
  password: string
): Promise<string> {
  const resp = await ctx.post(`${apiBase}/auth/login-v2`, { data: { email, password } })
  expect(resp.ok(), 'login must succeed').toBeTruthy()
  // login-v2 wraps its payload in the ApiResponse envelope: { success, data: { token, ... } }.
  const token = (await resp.json()).data?.token as string | undefined
  expect(token, 'login response must carry a bearer token').toBeTruthy()
  return token as string
}

/** YYYY-MM-DD `daysFromToday` days out, in UTC. */
function isoDate(daysFromToday: number): string {
  const d = new Date()
  d.setDate(d.getDate() + daysFromToday)
  return d.toISOString().slice(0, 10)
}
