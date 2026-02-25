import { useState, useEffect, useCallback, useRef } from 'react'
import { fetchMyBookings, cancelBooking } from '@/features/booking/booking.api'
import type { BookingApiRaw } from '@/features/booking/booking.types'
import { toBookingViewModel, type BookingViewModel } from './bookingViewModel'

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
      if (err instanceof DOMException && err.name === 'AbortError') return
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

  const cancel = useCallback(async (id: number): Promise<boolean> => {
    setIsPending(true)
    setError(null)
    try {
      await cancelBooking(id)
      return true
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to cancel booking'
      setError(message)
      return false
    } finally {
      setIsPending(false)
    }
  }, [])

  const clearError = useCallback(() => setError(null), [])

  return { cancel, isPending, error, clearError }
}
