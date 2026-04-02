import React, { useEffect, useState } from 'react'
import type { BookingDetailRaw } from '@/features/booking/booking.types'
import api from '@/shared/lib/api'
import AdminBookingTable from './AdminBookingTable'
import type { AdminBookingFilters } from './adminBooking.api'
import { getAllBookings } from './adminBooking.api'
import { normalizeAdminBookingSearch } from './adminBooking.helpers'

interface LocationOption {
  id: number
  name: string
}

interface BookingFilterDraft {
  search: string
  status: string
  locationId: string
  checkInStart: string
  checkInEnd: string
  checkOutStart: string
  checkOutEnd: string
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const emptyFilters: BookingFilterDraft = {
  search: '',
  status: '',
  locationId: '',
  checkInStart: '',
  checkInEnd: '',
  checkOutStart: '',
  checkOutEnd: '',
}

const defaultMeta: PaginationMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 0,
  total: 0,
}

function hasActiveFilters(filters: BookingFilterDraft): boolean {
  return Object.values(filters).some(value => value.trim() !== '')
}

function isAbortError(error: unknown): boolean {
  return (
    error instanceof Error &&
    (error.name === 'AbortError' || error.name === 'CanceledError' || error.name === 'ERR_CANCELED')
  )
}

function buildApiFilters(filters: BookingFilterDraft, page: number): AdminBookingFilters {
  const normalizedSearch = normalizeAdminBookingSearch(filters.search)

  return {
    ...(normalizedSearch ? { search: normalizedSearch } : {}),
    ...(filters.status ? { status: filters.status } : {}),
    ...(filters.locationId ? { location_id: Number(filters.locationId) } : {}),
    ...(filters.checkInStart ? { check_in_start: filters.checkInStart } : {}),
    ...(filters.checkInEnd ? { check_in_end: filters.checkInEnd } : {}),
    ...(filters.checkOutStart ? { check_out_start: filters.checkOutStart } : {}),
    ...(filters.checkOutEnd ? { check_out_end: filters.checkOutEnd } : {}),
    page,
  }
}

const AdminBookingDashboard: React.FC = () => {
  const [locations, setLocations] = useState<LocationOption[]>([])
  const [draftFilters, setDraftFilters] = useState<BookingFilterDraft>(emptyFilters)
  const [appliedFilters, setAppliedFilters] = useState<BookingFilterDraft>(emptyFilters)

  const [bookings, setBookings] = useState<BookingDetailRaw[]>([])
  const [meta, setMeta] = useState<PaginationMeta>(defaultMeta)
  const [currentPage, setCurrentPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()

    api
      .get('/v1/locations', { signal: controller.signal })
      .then(response => {
        setLocations(response.data.data)
      })
      .catch(error => {
        if (!isAbortError(error)) {
          setLocations([])
        }
      })

    return () => controller.abort()
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setIsLoading(true)
    setErrorMessage(null)

    void getAllBookings(buildApiFilters(appliedFilters, currentPage), controller.signal)
      .then(response => {
        if (!active || controller.signal.aborted) return

        setBookings(response.bookings)
        setMeta(response.meta)
      })
      .catch((error: unknown) => {
        if (!active || controller.signal.aborted || isAbortError(error)) return

        setBookings([])
        setMeta(defaultMeta)
        setErrorMessage('Không thể tải danh sách đặt phòng. Vui lòng thử lại.')
      })
      .finally(() => {
        if (active && !controller.signal.aborted) {
          setIsLoading(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [appliedFilters, currentPage])

  const updateFilter = <Key extends keyof BookingFilterDraft>(
    key: Key,
    value: BookingFilterDraft[Key]
  ) => {
    setDraftFilters(current => ({ ...current, [key]: value }))
  }

  const handleApplyFilters = (event?: React.FormEvent<HTMLFormElement>) => {
    event?.preventDefault()
    setCurrentPage(1)
    setAppliedFilters({ ...draftFilters })
  }

  const handleClearFilters = () => {
    setCurrentPage(1)
    setDraftFilters(emptyFilters)
    setAppliedFilters(emptyFilters)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
        <h1 className="text-[20px] font-semibold text-gray-950">Đặt phòng</h1>
        <p className="text-sm text-gray-500">Tổng quan / Đặt phòng</p>
      </div>

      <section className="rounded-xl border border-gray-200 bg-gray-50 p-4">
        <form className="space-y-4" onSubmit={handleApplyFilters}>
          <div className="grid gap-3 xl:grid-cols-[minmax(0,1.4fr)_220px_220px]">
            <div className="relative">
              <label htmlFor="booking-search" className="sr-only">
                Tìm kiếm đặt phòng
              </label>
              <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15a7.5 7.5 0 0 1 0 15Z"
                  />
                </svg>
              </span>
              <input
                id="booking-search"
                type="text"
                value={draftFilters.search}
                onChange={event => updateFilter('search', event.target.value)}
                placeholder="Tìm theo tên, email, mã..."
                className="h-11 w-full rounded-lg border border-gray-300 bg-white pl-10 pr-3 text-sm text-gray-900 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />
            </div>

            <div>
              <label htmlFor="booking-status" className="sr-only">
                Trạng thái
              </label>
              <select
                id="booking-status"
                value={draftFilters.status}
                onChange={event => updateFilter('status', event.target.value)}
                className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              >
                <option value="">Tất cả</option>
                <option value="pending">Chờ xác nhận</option>
                <option value="confirmed">Đã xác nhận</option>
                <option value="cancelled">Đã hủy</option>
                <option value="refund_pending">Hoàn tiền đang xử lý</option>
                <option value="refund_failed">Hoàn tiền thất bại</option>
              </select>
            </div>

            <div>
              <label htmlFor="booking-location" className="sr-only">
                Chi nhánh
              </label>
              <select
                id="booking-location"
                value={draftFilters.locationId}
                onChange={event => updateFilter('locationId', event.target.value)}
                className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              >
                <option value="">Tất cả chi nhánh</option>
                {locations.map(location => (
                  <option key={location.id} value={String(location.id)}>
                    {location.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid gap-3 lg:grid-cols-4">
            <label htmlFor="check-in-start" className="space-y-1">
              <span className="text-sm font-medium text-gray-600">Nhận phòng từ</span>
              <input
                id="check-in-start"
                type="date"
                value={draftFilters.checkInStart}
                onChange={event => updateFilter('checkInStart', event.target.value)}
                className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />
            </label>

            <label htmlFor="check-in-end" className="space-y-1">
              <span className="text-sm font-medium text-gray-600">đến</span>
              <input
                id="check-in-end"
                type="date"
                value={draftFilters.checkInEnd}
                onChange={event => updateFilter('checkInEnd', event.target.value)}
                className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />
            </label>

            <label htmlFor="check-out-start" className="space-y-1">
              <span className="text-sm font-medium text-gray-600">Trả phòng từ</span>
              <input
                id="check-out-start"
                type="date"
                value={draftFilters.checkOutStart}
                onChange={event => updateFilter('checkOutStart', event.target.value)}
                className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />
            </label>

            <label htmlFor="check-out-end" className="space-y-1">
              <span className="text-sm font-medium text-gray-600">đến</span>
              <input
                id="check-out-end"
                type="date"
                value={draftFilters.checkOutEnd}
                onChange={event => updateFilter('checkOutEnd', event.target.value)}
                className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />
            </label>
          </div>

          <div className="flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-3">
              <button
                type="submit"
                className="inline-flex items-center justify-center rounded-full bg-amber-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-600"
              >
                Áp dụng bộ lọc
              </button>

              <button
                type="button"
                onClick={handleClearFilters}
                className="text-sm font-medium text-gray-500 transition hover:text-gray-800"
              >
                Xóa bộ lọc
              </button>
            </div>

            <p className="text-sm text-gray-500">{meta.total} kết quả</p>
          </div>
        </form>
      </section>

      {errorMessage && (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {errorMessage}
        </div>
      )}

      <AdminBookingTable
        bookings={bookings}
        currentPage={meta.current_page}
        lastPage={meta.last_page}
        isLoading={isLoading}
        hasActiveFilters={hasActiveFilters(appliedFilters)}
        onClearFilters={handleClearFilters}
        onPageChange={setCurrentPage}
      />
    </div>
  )
}

export default AdminBookingDashboard
