import React from 'react'
import { Link } from 'react-router-dom'
import type { BookingDetailRaw } from '@/features/booking/booking.types'
import {
  buildBookingReference,
  formatAdminBookingAmount,
  formatShortBookingDate,
  getAdminBookingRoomLabel,
  getAdminBookingStatusConfig,
} from './adminBooking.helpers'

interface AdminBookingTableProps {
  bookings: BookingDetailRaw[]
  currentPage: number
  lastPage: number
  isLoading: boolean
  hasActiveFilters: boolean
  onClearFilters: () => void
  onPageChange: (page: number) => void
}

const skeletonCellWidths = ['w-20', 'w-36', 'w-24', 'w-12', 'w-12', 'w-10', 'w-20', 'w-24']

const AdminBookingTable: React.FC<AdminBookingTableProps> = ({
  bookings,
  currentPage,
  lastPage,
  isLoading,
  hasActiveFilters,
  onClearFilters,
  onPageChange,
}) => {
  const showEmptyState = !isLoading && bookings.length === 0

  return (
    <section>
      {isLoading && (
        <div className="space-y-3" aria-label="Đang tải danh sách đặt phòng">
          <div className="hidden overflow-x-auto rounded-2xl border border-gray-200 bg-white md:block">
            <table className="w-full min-w-[968px] text-sm">
              <thead className="bg-gray-50 text-xs uppercase tracking-[0.18em] text-gray-500">
                <tr>
                  <th className="w-[100px] px-4 py-3 text-left font-medium">Mã ĐP</th>
                  <th className="w-[180px] px-4 py-3 text-left font-medium">Khách hàng</th>
                  <th className="w-[140px] px-4 py-3 text-left font-medium">Phòng</th>
                  <th className="w-[120px] px-4 py-3 text-left font-medium">Nhận phòng</th>
                  <th className="w-[120px] px-4 py-3 text-left font-medium">Trả phòng</th>
                  <th className="w-[48px] px-4 py-3 text-center font-medium">Đêm</th>
                  <th className="w-[100px] px-4 py-3 text-right font-medium">Tổng tiền</th>
                  <th className="w-[120px] px-4 py-3 text-left font-medium">Trạng thái</th>
                  <th className="w-[40px] px-4 py-3 text-right font-medium">→</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 bg-white">
                {Array.from({ length: 5 }, (_, index) => (
                  <tr
                    key={index}
                    data-testid="admin-booking-skeleton-row"
                    className="animate-pulse"
                  >
                    {skeletonCellWidths.map((widthClass, cellIndex) => (
                      <td
                        key={`${index}-${cellIndex}`}
                        className={`px-4 py-4 ${
                          cellIndex === 5
                            ? 'text-center'
                            : cellIndex === 6
                              ? 'text-right'
                              : 'text-left'
                        }`}
                      >
                        <div className={`h-4 rounded bg-gray-200 ${widthClass}`} />
                      </td>
                    ))}
                    <td className="px-4 py-4 text-right">
                      <div className="ml-auto h-4 w-8 rounded bg-gray-200" />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="space-y-3 md:hidden">
            {Array.from({ length: 5 }, (_, index) => (
              <div
                key={index}
                className="animate-pulse rounded-xl border border-gray-200 bg-white p-4"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="space-y-2">
                    <div className="h-4 w-28 rounded bg-gray-200" />
                    <div className="h-3 w-36 rounded bg-gray-200" />
                  </div>
                  <div className="h-6 w-24 rounded-full bg-gray-200" />
                </div>
                <div className="mt-4 h-3 w-44 rounded bg-gray-200" />
                <div className="mt-4 flex items-center justify-between">
                  <div className="h-4 w-20 rounded bg-gray-200" />
                  <div className="h-4 w-24 rounded bg-gray-200" />
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {showEmptyState && (
        <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M4 7h16M4 12h10m-10 5h7"
              />
            </svg>
          </div>
          <p className="mt-4 text-base font-semibold text-gray-900">Không tìm thấy đặt phòng nào</p>
          <button
            type="button"
            onClick={onClearFilters}
            className="mt-3 text-sm font-medium text-amber-700 transition hover:text-amber-800"
          >
            Xóa bộ lọc
          </button>
          {!hasActiveFilters && (
            <p className="mt-2 text-sm text-gray-500">Thử điều chỉnh từ khóa hoặc khoảng ngày.</p>
          )}
        </div>
      )}

      {!isLoading && !showEmptyState && (
        <>
          <div className="hidden overflow-x-auto rounded-2xl border border-gray-200 bg-white md:block">
            <table className="w-full min-w-[968px] text-sm">
              <thead className="bg-gray-50 text-xs uppercase tracking-[0.18em] text-gray-500">
                <tr>
                  <th className="w-[100px] px-4 py-3 text-left font-medium">Mã ĐP</th>
                  <th className="w-[180px] px-4 py-3 text-left font-medium">Khách hàng</th>
                  <th className="w-[140px] px-4 py-3 text-left font-medium">Phòng</th>
                  <th className="w-[120px] px-4 py-3 text-left font-medium">Nhận phòng</th>
                  <th className="w-[120px] px-4 py-3 text-left font-medium">Trả phòng</th>
                  <th className="w-[48px] px-4 py-3 text-center font-medium">Đêm</th>
                  <th className="w-[100px] px-4 py-3 text-right font-medium">Tổng tiền</th>
                  <th className="w-[120px] px-4 py-3 text-left font-medium">Trạng thái</th>
                  <th className="w-[40px] px-4 py-3 text-right font-medium">→</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 bg-white">
                {bookings.map(booking => {
                  const statusConfig = getAdminBookingStatusConfig(booking.status)
                  const roomLabel = getAdminBookingRoomLabel(booking)

                  return (
                    <tr key={booking.id} className="transition-colors hover:bg-amber-50/30">
                      <td className="px-4 py-4 align-top">
                        <span className="font-mono text-[13px] font-semibold text-amber-700">
                          {buildBookingReference(booking)}
                        </span>
                      </td>
                      <td className="px-4 py-4 align-top">
                        <p className="font-semibold text-gray-900">{booking.guest_name}</p>
                        <p className="mt-1 text-[12px] text-gray-500">{booking.guest_email}</p>
                      </td>
                      <td className="px-4 py-4 align-top text-gray-700">{roomLabel}</td>
                      <td className="px-4 py-4 align-top text-gray-700">
                        {formatShortBookingDate(booking.check_in)}
                      </td>
                      <td className="px-4 py-4 align-top text-gray-700">
                        {formatShortBookingDate(booking.check_out)}
                      </td>
                      <td className="px-4 py-4 text-center align-top text-gray-700">
                        {booking.nights}
                      </td>
                      <td className="px-4 py-4 text-right align-top font-medium text-gray-900">
                        {formatAdminBookingAmount(booking)}
                      </td>
                      <td className="px-4 py-4 align-top">
                        <span
                          className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ${statusConfig.className}`}
                        >
                          {statusConfig.label}
                        </span>
                      </td>
                      <td className="px-4 py-4 text-right align-top">
                        <Link
                          to={`/admin/bookings/${booking.id}`}
                          className="text-sm font-medium text-amber-700 transition hover:text-amber-800"
                        >
                          Xem
                        </Link>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          <div className="space-y-3 md:hidden">
            {bookings.map(booking => {
              const statusConfig = getAdminBookingStatusConfig(booking.status)
              const roomLabel = getAdminBookingRoomLabel(booking)

              return (
                <article
                  key={booking.id}
                  className="rounded-xl border border-gray-200 bg-white p-4"
                >
                  <div className="flex items-start justify-between gap-3">
                    <p className="font-semibold text-gray-900">{booking.guest_name}</p>
                    <span
                      className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ${statusConfig.className}`}
                    >
                      {statusConfig.label}
                    </span>
                  </div>

                  <p className="mt-3 text-[13px] text-gray-500">
                    {roomLabel} · {formatShortBookingDate(booking.check_in)} -{' '}
                    {formatShortBookingDate(booking.check_out)}
                  </p>

                  <div className="mt-4 flex items-center justify-between gap-3">
                    <p className="font-semibold text-gray-900">
                      {formatAdminBookingAmount(booking)}
                    </p>
                    <Link
                      to={`/admin/bookings/${booking.id}`}
                      className="text-sm font-medium text-amber-700 transition hover:text-amber-800"
                    >
                      Xem chi tiết →
                    </Link>
                  </div>
                </article>
              )
            })}
          </div>

          <div className="mt-6 flex items-center justify-center gap-4 text-sm">
            <button
              type="button"
              onClick={() => onPageChange(Math.max(1, currentPage - 1))}
              disabled={currentPage <= 1}
              className="rounded-full border border-gray-300 px-4 py-2 text-gray-700 transition hover:border-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-300"
            >
              ← Trang trước
            </button>

            <p className="text-gray-500">
              Trang {currentPage} / {Math.max(lastPage, 1)}
            </p>

            <button
              type="button"
              onClick={() => onPageChange(Math.min(Math.max(lastPage, 1), currentPage + 1))}
              disabled={currentPage >= Math.max(lastPage, 1)}
              className="rounded-full border border-gray-300 px-4 py-2 text-gray-700 transition hover:border-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-300"
            >
              Trang sau →
            </button>
          </div>
        </>
      )}
    </section>
  )
}

export default AdminBookingTable
