import { describe, it, expect } from 'vitest'
import { getErrorMessage } from './toast'

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

  it('falls back to error.message when response.data has no message or errors', () => {
    const axiosLike = {
      isAxiosError: true,
      message: 'Network Error',
      response: { status: 0, data: {} },
    }

    expect(getErrorMessage(axiosLike)).toBe('Network Error')
  })

  it('returns error.message for plain Error instances', () => {
    expect(getErrorMessage(new Error('Plain error message'))).toBe('Plain error message')
  })

  it('returns the string itself when given a string', () => {
    expect(getErrorMessage('Direct string error')).toBe('Direct string error')
  })

  it('returns a safe fallback for unknown error shapes', () => {
    expect(getErrorMessage(null)).toBe('An unexpected error occurred')
    expect(getErrorMessage(undefined)).toBe('An unexpected error occurred')
    expect(getErrorMessage(42)).toBe('An unexpected error occurred')
  })
})
