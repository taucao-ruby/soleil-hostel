import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { AxiosInstance, AxiosResponse } from 'axios'
import {
  createBooking,
  fetchMyBookings,
  cancelBooking,
  getBookingById,
  submitReview,
} from '../booking.api'
import type {
  Booking,
  BookingApiRaw,
  BookingDetailRaw,
  BookingFormData,
  CancelBookingResponse,
  ReviewSubmitData,
  ReviewSubmitResponse,
} from '../booking.types'

// ─── Mock the shared api module ───────────────────────────────────────────────

const { mockApiInstance } = vi.hoisted(() => ({
  mockApiInstance: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  } as unknown as AxiosInstance & {
    get: ReturnType<typeof vi.fn>
    post: ReturnType<typeof vi.fn>
    put: ReturnType<typeof vi.fn>
    delete: ReturnType<typeof vi.fn>
  },
}))

vi.mock('@/shared/lib/api', () => ({
  default: mockApiInstance,
}))

// ─── Fixtures ────────────────────────────────────────────────────────────────

const makeBookingRaw = (overrides: Partial<BookingApiRaw> = {}): BookingApiRaw => ({
  id: 1,
  room_id: 10,
  user_id: 42,
  check_in: '2026-06-01',
  check_out: '2026-06-03',
  guest_name: 'Nguyễn Văn A',
  guest_email: 'nguyen.van.a@example.com',
  status: 'pending',
  status_label: 'Chờ xác nhận',
  nights: 2,
  created_at: '2026-05-01T10:00:00+07:00',
  updated_at: '2026-05-01T10:00:00+07:00',
  ...overrides,
})

const makeDetailRaw = (overrides: Partial<BookingDetailRaw> = {}): BookingDetailRaw => ({
  ...makeBookingRaw(),
  room: {
    id: 10,
    name: 'Phòng Dorm 4 Giường',
    display_name: null,
    room_number: '101',
    max_guests: 4,
    price: 350000,
  },
  ...overrides,
})

const makeBooking = (overrides: Partial<Booking> = {}): Booking => ({
  id: 1,
  room_id: 10,
  guest_name: 'Nguyễn Văn A',
  guest_email: 'nguyen.van.a@example.com',
  check_in: '2026-06-01',
  check_out: '2026-06-03',
  number_of_guests: 2,
  special_requests: null,
  status: 'pending',
  total_price: 700000,
  created_at: '2026-05-01T10:00:00+07:00',
  updated_at: '2026-05-01T10:00:00+07:00',
  ...overrides,
})

const makeFormData = (overrides: Partial<BookingFormData> = {}): BookingFormData => ({
  room_id: 10,
  guest_name: 'Nguyễn Văn A',
  guest_email: 'nguyen.van.a@example.com',
  check_in: '2026-06-01',
  check_out: '2026-06-03',
  number_of_guests: 2,
  ...overrides,
})

/** Creates a mock AxiosResponse wrapper. */
function axiosResponse<T>(data: T): AxiosResponse<T> {
  return {
    data,
    status: 200,
    statusText: 'OK',
    headers: {},
    config: {} as never,
  }
}

// ─── createBooking ─────────────────────────────────────────────────────────────

describe('createBooking', () => {
  beforeEach(() => vi.clearAllMocks())

  it('POSTs to /v1/bookings with the form data', async () => {
    const formData = makeFormData()
    const booking = makeBooking()
    mockApiInstance.post.mockResolvedValueOnce(axiosResponse({ data: booking }))

    await createBooking(formData)

    expect(mockApiInstance.post).toHaveBeenCalledOnce()
    expect(mockApiInstance.post).toHaveBeenCalledWith('/v1/bookings', formData)
  })

  it('returns the booking data from the response', async () => {
    const booking = makeBooking({ id: 99, status: 'pending' })
    mockApiInstance.post.mockResolvedValueOnce(axiosResponse({ data: booking }))

    const result = await createBooking(makeFormData())

    expect(result).toEqual(booking)
  })

  it('propagates API errors to the caller', async () => {
    const error = Object.assign(new Error('Network Error'), {
      isAxiosError: true,
      response: { status: 422, data: { success: false, message: 'Room unavailable' } },
    })
    mockApiInstance.post.mockRejectedValueOnce(error)

    await expect(createBooking(makeFormData())).rejects.toThrow('Network Error')
  })

  it('sends optional special_requests field when provided', async () => {
    const formData = makeFormData({ special_requests: 'Phòng tầng cao' })
    mockApiInstance.post.mockResolvedValueOnce(axiosResponse({ data: makeBooking() }))

    await createBooking(formData)

    const [, sentData] = mockApiInstance.post.mock.calls[0]
    expect(sentData).toMatchObject({ special_requests: 'Phòng tầng cao' })
  })
})

// ─── fetchMyBookings ──────────────────────────────────────────────────────────

describe('fetchMyBookings', () => {
  beforeEach(() => vi.clearAllMocks())

  it('GETs /v1/bookings', async () => {
    mockApiInstance.get.mockResolvedValueOnce(axiosResponse({ success: true, data: [] }))

    await fetchMyBookings()

    expect(mockApiInstance.get).toHaveBeenCalledOnce()
    expect(mockApiInstance.get).toHaveBeenCalledWith('/v1/bookings', expect.objectContaining({}))
  })

  it('returns the array from response.data.data', async () => {
    const bookings = [makeBookingRaw({ id: 1 }), makeBookingRaw({ id: 2 })]
    mockApiInstance.get.mockResolvedValueOnce(axiosResponse({ success: true, data: bookings }))

    const result = await fetchMyBookings()

    expect(result).toHaveLength(2)
    expect(result[0].id).toBe(1)
    expect(result[1].id).toBe(2)
  })

  it('passes an AbortSignal when provided', async () => {
    mockApiInstance.get.mockResolvedValueOnce(axiosResponse({ success: true, data: [] }))
    const controller = new AbortController()

    await fetchMyBookings(controller.signal)

    const [, config] = mockApiInstance.get.mock.calls[0]
    expect(config.signal).toBe(controller.signal)
  })

  it('returns empty array when no bookings exist', async () => {
    mockApiInstance.get.mockResolvedValueOnce(axiosResponse({ success: true, data: [] }))

    const result = await fetchMyBookings()

    expect(result).toEqual([])
  })

  it('propagates abort errors', async () => {
    const abortError = new DOMException('Aborted', 'AbortError')
    mockApiInstance.get.mockRejectedValueOnce(abortError)

    await expect(fetchMyBookings()).rejects.toThrow('Aborted')
  })
})

// ─── cancelBooking ────────────────────────────────────────────────────────────

describe('cancelBooking', () => {
  beforeEach(() => vi.clearAllMocks())

  it('POSTs to /v1/bookings/:id/cancel', async () => {
    const cancelResponse: CancelBookingResponse = {
      success: true,
      message: 'Đặt phòng đã được hủy.',
      data: makeBookingRaw({ status: 'cancelled' }),
    }
    mockApiInstance.post.mockResolvedValueOnce(axiosResponse(cancelResponse))

    await cancelBooking(7)

    expect(mockApiInstance.post).toHaveBeenCalledOnce()
    expect(mockApiInstance.post).toHaveBeenCalledWith('/v1/bookings/7/cancel')
  })

  it('returns the full cancel response envelope', async () => {
    const cancelResponse: CancelBookingResponse = {
      success: true,
      message: 'Đặt phòng đã được hủy.',
      data: makeBookingRaw({ id: 7, status: 'cancelled' }),
    }
    mockApiInstance.post.mockResolvedValueOnce(axiosResponse(cancelResponse))

    const result = await cancelBooking(7)

    expect(result.success).toBe(true)
    expect(result.message).toBe('Đặt phòng đã được hủy.')
    expect(result.data.status).toBe('cancelled')
  })

  it('uses the correct booking id in the URL', async () => {
    mockApiInstance.post.mockResolvedValueOnce(
      axiosResponse({ success: true, message: '', data: makeBookingRaw() })
    )

    await cancelBooking(42)

    const [url] = mockApiInstance.post.mock.calls[0]
    expect(url).toBe('/v1/bookings/42/cancel')
  })

  it('propagates 403 errors when user cannot cancel', async () => {
    const error = Object.assign(new Error('Forbidden'), {
      isAxiosError: true,
      response: { status: 403, data: { success: false, message: 'Cannot cancel' } },
    })
    mockApiInstance.post.mockRejectedValueOnce(error)

    await expect(cancelBooking(1)).rejects.toThrow('Forbidden')
  })
})

// ─── getBookingById ───────────────────────────────────────────────────────────

describe('getBookingById', () => {
  beforeEach(() => vi.clearAllMocks())

  it('GETs /v1/bookings/:id', async () => {
    mockApiInstance.get.mockResolvedValueOnce(
      axiosResponse({ success: true, data: makeDetailRaw() })
    )

    await getBookingById(5)

    expect(mockApiInstance.get).toHaveBeenCalledOnce()
    expect(mockApiInstance.get).toHaveBeenCalledWith('/v1/bookings/5', expect.objectContaining({}))
  })

  it('returns the booking detail from response.data.data', async () => {
    const detail = makeDetailRaw({ id: 5 })
    mockApiInstance.get.mockResolvedValueOnce(axiosResponse({ success: true, data: detail }))

    const result = await getBookingById(5)

    expect(result.id).toBe(5)
    expect(result.room).toBeDefined()
    expect(result.room?.name).toBe('Phòng Dorm 4 Giường')
  })

  it('passes an AbortSignal when provided', async () => {
    mockApiInstance.get.mockResolvedValueOnce(
      axiosResponse({ success: true, data: makeDetailRaw() })
    )
    const controller = new AbortController()

    await getBookingById(5, controller.signal)

    const [, config] = mockApiInstance.get.mock.calls[0]
    expect(config.signal).toBe(controller.signal)
  })

  it('uses the correct booking id in the URL', async () => {
    mockApiInstance.get.mockResolvedValueOnce(
      axiosResponse({ success: true, data: makeDetailRaw() })
    )

    await getBookingById(99)

    const [url] = mockApiInstance.get.mock.calls[0]
    expect(url).toBe('/v1/bookings/99')
  })

  it('propagates 404 errors for unknown bookings', async () => {
    const error = Object.assign(new Error('Not Found'), {
      isAxiosError: true,
      response: { status: 404, data: { message: 'Booking not found' } },
    })
    mockApiInstance.get.mockRejectedValueOnce(error)

    await expect(getBookingById(99999)).rejects.toThrow('Not Found')
  })
})

// ─── submitReview ─────────────────────────────────────────────────────────────

describe('submitReview', () => {
  beforeEach(() => vi.clearAllMocks())

  const makeReviewData = (overrides: Partial<ReviewSubmitData> = {}): ReviewSubmitData => ({
    booking_id: 1,
    title: 'Trải nghiệm tuyệt vời',
    content: 'Phòng sạch sẽ, nhân viên thân thiện.',
    rating: 5,
    ...overrides,
  })

  const makeReviewResponse = (): ReviewSubmitResponse => ({
    success: true,
    message: 'Cảm ơn đánh giá của bạn.',
    data: {
      id: 1,
      title: 'Trải nghiệm tuyệt vời',
      content: 'Phòng sạch sẽ, nhân viên thân thiện.',
      rating: 5,
      booking_id: 1,
      room_id: 10,
      user_id: 42,
      approved: false,
      created_at: '2026-06-04T08:00:00+07:00',
      updated_at: '2026-06-04T08:00:00+07:00',
    },
  })

  it('POSTs to /v1/reviews', async () => {
    mockApiInstance.post.mockResolvedValueOnce(axiosResponse(makeReviewResponse()))

    await submitReview(makeReviewData())

    expect(mockApiInstance.post).toHaveBeenCalledOnce()
    expect(mockApiInstance.post).toHaveBeenCalledWith('/v1/reviews', expect.any(Object))
  })

  it('sends the correct review payload', async () => {
    const data = makeReviewData({ rating: 4, title: 'Tốt' })
    mockApiInstance.post.mockResolvedValueOnce(axiosResponse(makeReviewResponse()))

    await submitReview(data)

    const [, sentData] = mockApiInstance.post.mock.calls[0]
    expect(sentData).toMatchObject({
      booking_id: 1,
      rating: 4,
      title: 'Tốt',
      content: 'Phòng sạch sẽ, nhân viên thân thiện.',
    })
  })

  it('returns the full review submit response', async () => {
    const response = makeReviewResponse()
    mockApiInstance.post.mockResolvedValueOnce(axiosResponse(response))

    const result = await submitReview(makeReviewData())

    expect(result.success).toBe(true)
    expect(result.data.rating).toBe(5)
    expect(result.data.approved).toBe(false)
  })

  it('propagates 422 validation errors', async () => {
    const error = Object.assign(new Error('Unprocessable'), {
      isAxiosError: true,
      response: {
        status: 422,
        data: { success: false, message: 'Đặt phòng này đã có đánh giá.' },
      },
    })
    mockApiInstance.post.mockRejectedValueOnce(error)

    await expect(submitReview(makeReviewData())).rejects.toThrow('Unprocessable')
  })

  it('propagates 403 errors for policy violations', async () => {
    const error = Object.assign(new Error('Forbidden'), {
      isAxiosError: true,
      response: {
        status: 403,
        data: { success: false, message: 'Booking không thuộc về bạn.' },
      },
    })
    mockApiInstance.post.mockRejectedValueOnce(error)

    await expect(submitReview(makeReviewData())).rejects.toThrow('Forbidden')
  })
})
