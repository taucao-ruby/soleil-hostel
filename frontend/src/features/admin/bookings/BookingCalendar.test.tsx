import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import BookingCalendar from './BookingCalendar'
import type { BookingDetailRaw } from '@/shared/types/booking.types'

const { mockGetAllBookings, mockGetLocations, mockGetRoomsByLocation, mockNavigate } = vi.hoisted(
  () => ({
    mockGetAllBookings: vi.fn(),
    mockGetLocations: vi.fn(),
    mockGetRoomsByLocation: vi.fn(),
    mockNavigate: vi.fn(),
  })
)

vi.mock('./adminBooking.api', () => ({
  getAllBookings: mockGetAllBookings,
}))

vi.mock('@/shared/lib/location.api', () => ({
  getLocations: mockGetLocations,
}))

vi.mock('@/features/admin/rooms/adminRoom.api', () => ({
  getRoomsByLocation: mockGetRoomsByLocation,
}))

// Capture navigation while keeping the real MemoryRouter / Link.
vi.mock('react-router-dom', async importOriginal => {
  const actual = await importOriginal<typeof import('react-router-dom')>()
  return { ...actual, useNavigate: () => mockNavigate }
})

// A single-night booking on the 15th of whatever month is on screen today, so it
// always lands inside the rendered grid (and renders exactly once — half-open
// [15, 16) occupies only day 15) regardless of when the suite runs.
const now = new Date()
const yyyy = now.getFullYear()
const mm = String(now.getMonth() + 1).padStart(2, '0')

const booking: BookingDetailRaw = {
  id: 101,
  room_id: 7,
  user_id: 23,
  check_in: `${yyyy}-${mm}-15`,
  check_out: `${yyyy}-${mm}-16`,
  guest_name: 'Nguyen Van A',
  guest_email: 'guest@example.com',
  number_of_guests: null,
  special_requests: null,
  status: 'confirmed',
  status_label: 'Confirmed',
  nights: 1,
  payment_policy: 'prepaid',
  payment_status: 'paid',
  created_at: '2026-04-01T08:00:00Z',
  updated_at: '2026-04-01T08:00:00Z',
}

// The exact wire shape that used to crash the calendar: the admin bookings
// endpoint returns { data: { bookings, meta } }, and getAllBookings normalizes
// it to { bookings, meta }. The component must read `.bookings`, not the wrapper.
const bookingsPayload = {
  bookings: [booking],
  meta: { current_page: 1, last_page: 1, per_page: 50, total: 1 },
}

function renderCalendar() {
  return render(
    <MemoryRouter>
      <BookingCalendar />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetLocations.mockResolvedValue([{ id: 1, name: 'Soleil Da Nang' }])
  mockGetRoomsByLocation.mockResolvedValue([{ id: 7, name: 'Superior 7' }])
  mockGetAllBookings.mockResolvedValue(bookingsPayload)
})

afterEach(() => {
  vi.useRealTimers()
})

describe('BookingCalendar', () => {
  it('renders a booking cell from a { bookings, meta } payload without crashing', async () => {
    // Old bug: setBookings(res.data.data) stored the { bookings, meta } object,
    // so getBookingForDay's `bookings.find(...)` threw "find is not a function"
    // the moment a location with rooms was selected. A rendered booking cell
    // proves `.find()` ran over an array — this assertion fails if `bookings`
    // is ever a non-array again.
    renderCalendar()

    // First location auto-selects on mount → rooms + bookings load for it.
    expect(await screen.findByText('Nguyen Van A')).toBeInTheDocument()
    // The room label is rendered too, confirming a room with bookings is shown.
    expect(screen.getByText('Superior 7')).toBeInTheDocument()
  })

  it('routes admin bookings through getAllBookings with the location filter and an abort signal', async () => {
    renderCalendar()

    await screen.findByText('Nguyen Van A')

    expect(mockGetAllBookings).toHaveBeenCalledWith({ location_id: 1 }, expect.any(AbortSignal))
    expect(mockGetRoomsByLocation).toHaveBeenCalledWith(1, expect.any(AbortSignal))
  })

  it('navigates via React Router (not window.location) when a booking cell is clicked', async () => {
    const user = userEvent.setup()
    renderCalendar()

    await user.click(await screen.findByText('Nguyen Van A'))

    expect(mockNavigate).toHaveBeenCalledWith('/admin/bookings/101')
  })

  it('aborts in-flight fetches on unmount without writing state or logging an error', async () => {
    vi.useFakeTimers()
    const consoleWarnSpy = vi.spyOn(console, 'warn').mockImplementation(() => undefined)

    let capturedSignal: AbortSignal | undefined
    mockGetLocations.mockImplementation((signal?: AbortSignal) => {
      capturedSignal = signal
      return new Promise(resolve => {
        window.setTimeout(() => resolve([{ id: 1, name: 'Soleil Da Nang' }]), 50)
      })
    })

    const { unmount } = renderCalendar()
    unmount()

    expect(capturedSignal?.aborted).toBe(true)

    // Let the delayed resolve fire after unmount: the `signal.aborted` guard must
    // prevent the state write, and no error should be logged for the abort.
    await act(async () => {
      await vi.advanceTimersByTimeAsync(50)
    })

    expect(consoleWarnSpy).not.toHaveBeenCalled()
    consoleWarnSpy.mockRestore()
  })
})
