import { describe, it, expect } from 'vitest'
import { getErrorMessage } from './toast'

const FALLBACK_GENERIC = 'Đã có lỗi xảy ra. Vui lòng thử lại.'
const FALLBACK_NETWORK = 'Không thể kết nối máy chủ. Vui lòng kiểm tra mạng và thử lại.'
const FALLBACK_SESSION = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'
const FALLBACK_RATE_LIMIT = 'Bạn thao tác quá nhanh. Vui lòng thử lại sau.'

describe('getErrorMessage', () => {
  it('returns response.data.message for axios-shaped errors (backend-localized cancellation reason)', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 422',
      response: {
        status: 422,
        data: {
          message: 'Không thể hủy đặt phòng trong vòng 24 giờ trước ngày nhận phòng.',
        },
      },
    }

    expect(getErrorMessage(axiosLike)).toBe(
      'Không thể hủy đặt phòng trong vòng 24 giờ trước ngày nhận phòng.'
    )
  })

  it('joins Laravel validation errors from response.data.errors', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 422',
      response: {
        status: 422,
        data: {
          errors: {
            check_in: ['Ngày nhận phòng không hợp lệ.'],
            check_out: ['Ngày trả phòng phải sau ngày nhận phòng.'],
          },
        },
      },
    }

    expect(getErrorMessage(axiosLike)).toBe(
      'Ngày nhận phòng không hợp lệ., Ngày trả phòng phải sau ngày nhận phòng.'
    )
  })

  it('surfaces safe 409 conflict messages from response.data.message', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 409',
      response: {
        status: 409,
        data: {
          message: 'Phòng đã được đặt trong khoảng thời gian này.',
        },
      },
    }

    expect(getErrorMessage(axiosLike)).toBe('Phòng đã được đặt trong khoảng thời gian này.')
  })

  it('surfaces nested error.message payloads when 4xx', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 422',
      response: {
        status: 422,
        data: {
          error: {
            message: 'Không thể hủy đặt phòng trong trạng thái hiện tại.',
          },
        },
      },
    }

    expect(getErrorMessage(axiosLike)).toBe('Không thể hủy đặt phòng trong trạng thái hiện tại.')
  })

  it('returns the Vietnamese session-expired message for 401', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 401',
      response: {
        status: 401,
        data: { message: 'Unauthenticated.' },
      },
    }

    expect(getErrorMessage(axiosLike)).toBe(FALLBACK_SESSION)
  })

  it('returns the Vietnamese rate-limit message for 429 regardless of payload', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 429',
      response: {
        status: 429,
        data: { message: 'Too Many Requests' },
      },
    }

    expect(getErrorMessage(axiosLike)).toBe(FALLBACK_RATE_LIMIT)
  })

  it('suppresses 5xx server payloads with unsafe SQLSTATE internals', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 500',
      response: {
        status: 500,
        data: {
          message:
            'SQLSTATE[23503]: foreign key violation: 7 ERROR: insert or update on table "bookings" violates foreign key constraint "bookings_room_id_foreign"',
        },
      },
    }

    const result = getErrorMessage(axiosLike)
    expect(result).toBe(FALLBACK_GENERIC)
    expect(result).not.toMatch(/SQLSTATE/)
    expect(result).not.toMatch(/foreign key/)
  })

  it('suppresses 5xx server payloads with raw Exception traces', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 500',
      response: {
        status: 500,
        data: {
          message:
            'Stripe\\Exception\\ApiErrorException in /var/www/vendor/stripe/stripe-php/lib/StripeObject.php on line 42',
        },
      },
    }

    const result = getErrorMessage(axiosLike)
    expect(result).toBe(FALLBACK_GENERIC)
    expect(result).not.toMatch(/Stripe/)
    expect(result).not.toMatch(/vendor/)
    expect(result).not.toMatch(/Exception/)
  })

  it('suppresses an HTML error page payload', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 502',
      response: {
        status: 502,
        data: '<html><head><title>502 Bad Gateway</title></head><body>...</body></html>',
      },
    }

    const result = getErrorMessage(axiosLike)
    expect(result).toBe(FALLBACK_GENERIC)
    expect(result).not.toMatch(/<html/i)
  })

  it('suppresses 4xx payloads that smell like an Illuminate stack trace', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 422',
      response: {
        status: 422,
        data: {
          message:
            'Illuminate\\Database\\QueryException: stack trace at /var/www/app/Http/Controllers/BookingController.php',
        },
      },
    }

    expect(getErrorMessage(axiosLike)).toBe(FALLBACK_GENERIC)
  })

  it('suppresses an Authorization header leak in a payload', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 422',
      response: {
        status: 422,
        data: {
          message: 'Failed for Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.payload.signature',
        },
      },
    }

    const result = getErrorMessage(axiosLike)
    expect(result).toBe(FALLBACK_GENERIC)
    expect(result).not.toMatch(/Bearer/)
    expect(result).not.toMatch(/eyJ/)
  })

  it('returns the Vietnamese network message for axios errors without a response', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Network Error',
      code: 'ERR_NETWORK',
    }

    expect(getErrorMessage(axiosLike)).toBe(FALLBACK_NETWORK)
  })

  it('returns the Vietnamese network message for axios timeouts', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'timeout of 5000ms exceeded',
      code: 'ECONNABORTED',
    }

    expect(getErrorMessage(axiosLike)).toBe(FALLBACK_NETWORK)
  })

  it('returns error.message for plain Error instances when the text is safe', () => {
    expect(getErrorMessage(new Error('Đặt phòng thất bại.'))).toBe('Đặt phòng thất bại.')
  })

  it('falls back to the generic Vietnamese message when a plain Error leaks internals', () => {
    expect(getErrorMessage(new Error('SQLSTATE[23503]: foreign key violation'))).toBe(
      FALLBACK_GENERIC
    )
  })

  it('returns the string itself when it is a safe non-empty string', () => {
    expect(getErrorMessage('Đặt phòng thất bại.')).toBe('Đặt phòng thất bại.')
  })

  it('falls back to the generic Vietnamese message when the string is unsafe', () => {
    expect(getErrorMessage('Network Error')).toBe(FALLBACK_GENERIC)
  })

  it('returns the generic Vietnamese fallback for unknown error shapes', () => {
    expect(getErrorMessage(null)).toBe(FALLBACK_GENERIC)
    expect(getErrorMessage(undefined)).toBe(FALLBACK_GENERIC)
    expect(getErrorMessage(42)).toBe(FALLBACK_GENERIC)
  })

  it('falls back when 4xx payload has neither message nor errors', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Request failed with status code 400',
      response: { status: 400, data: {} },
    }
    expect(getErrorMessage(axiosLike)).toBe(FALLBACK_GENERIC)
  })
})
