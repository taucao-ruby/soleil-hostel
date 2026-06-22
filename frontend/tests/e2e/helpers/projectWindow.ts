import { test } from '@playwright/test'

/**
 * Per-project day offset for booking-creating flows.
 *
 * The nightly full run executes every spec across 4 Playwright projects
 * (chromium / firefox / webkit / Mobile Chrome) with workers:1 against ONE
 * shared backend seeded with only 3 rooms (DevRolePreviewSeeder). If each
 * project booked the same room/window the 2nd+ project would hit the booking
 * exclusion constraint (409) or exhaust availability — which is exactly why the
 * full run was red on the later projects. Giving each project a disjoint date
 * window keeps the flows independent. (The PR @smoke gate runs chromium only,
 * so offset 0 there.)
 */
const PROJECT_DAY_OFFSET: Record<string, number> = {
  chromium: 0,
  firefox: 10,
  webkit: 20,
  'Mobile Chrome': 30,
}

export function projectDayOffset(): number {
  return PROJECT_DAY_OFFSET[test.info().project.name] ?? 0
}
