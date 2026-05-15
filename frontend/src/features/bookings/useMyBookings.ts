import { useState, useEffect, useCallback, useRef } from 'react'
import { fetchMyBookings, cancelBooking } from '@/features/booking/booking.api'
import type { BookingApiRaw } from '@/shared/types/booking.types'
import { getErrorMessage } from '@/shared/utils/toast'
import { toBookingViewModel, type BookingViewModel } from './bookingViewModel'

export type CancelBookingResult =
  | { ok: true; errorMessage: null }
  | { ok: false; errorMessage: string }

/**
 * useMyBookingsQuery
 *
 * Fetches the authenticated user's bookings on mount.
 * Uses AbortController for cleanup on unmount.
 */
export function useMyBookingsQuery() {
  const [rawBookings, setRawBookings] = useState<BookingApiRaw[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isError, setIsError] = useState(false)
  const mountedRef = useRef(true)

  const fetchBookings = useCallback(async (signal?: AbortSignal) => {
    setIsLoading(true)
    setIsError(false)
    try {
      const data = await fetchMyBookings(signal)
      if (mountedRef.current) {
        setRawBookings(data)
      }
    } catch (err) {
      // Axios aborts via AbortSignal throw CanceledError (code: ERR_CANCELED), not DOMException
      const isCanceled =
        (err instanceof DOMException && err.name === 'AbortError') ||
        (err instanceof Error && (err as { code?: string }).code === 'ERR_CANCELED')
      if (isCanceled) return
      if (mountedRef.current) {
        setIsError(true)
      }
    } finally {
      if (mountedRef.current) {
        setIsLoading(false)
      }
    }
  }, [])

  useEffect(() => {
    mountedRef.current = true
    const controller = new AbortController()
    fetchBookings(controller.signal)
    return () => {
      mountedRef.current = false
      controller.abort()
    }
  }, [fetchBookings])

  const refetch = useCallback(() => {
    fetchBookings()
  }, [fetchBookings])

  const bookings: BookingViewModel[] = rawBookings.map(toBookingViewModel)

  return { bookings, isLoading, isError, refetch }
}

/**
 * useCancelBookingMutation
 *
 * Returns a cancel function + pending/error state.
 * Caller is responsible for refetching after success.
 */
export function useCancelBookingMutation() {
  const [isPending, setIsPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const cancel = useCallback(async (id: number): Promise<CancelBookingResult> => {
    setIsPending(true)
    setError(null)
    try {
      await cancelBooking(id)
      return { ok: true, errorMessage: null }
    } catch (err) {
      const message = getErrorMessage(err)
      setError(message)
      return { ok: false, errorMessage: message }
    } finally {
      setIsPending(false)
    }
  }, [])

  const clearError = useCallback(() => setError(null), [])

  return { cancel, isPending, error, clearError }
}
