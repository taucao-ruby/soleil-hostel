import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'
import { getStatusConfig, formatDateRangeVN } from '@/shared/lib/booking.utils'
import { formatVND } from '@/shared/lib/formatCurrency'
import { getErrorMessage, showToast } from '@/shared/utils/toast'
import type {
  AdminBookingRaw,
  AdminBookingsPaginatedResult,
  ContactMessageRaw,
  PaginationMeta,
} from './admin.types'
import {
  fetchAdminBookings,
  fetchContactMessages,
  fetchTrashedBookings,
  forceDeleteBooking,
  restoreBooking,
} from './admin.api'

type DashboardTab = 'bookings' | 'trashed' | 'contacts'

interface MetricCard {
  label: string
  trend: string
  trendTone: 'success' | 'warning' | 'accent'
  value: string
}

const defaultPagination: PaginationMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 0,
  total: 0,
}

// A dedicated metrics endpoint does not exist yet, so Phase 4 uses the approved product copy.
const metricCards: MetricCard[] = [
  {
    label: 'Đặt phòng hôm nay',
    value: '12',
    trend: '+3 so với hôm qua',
    trendTone: 'success',
  },
  {
    label: 'Chờ xác nhận',
    value: '4',
    trend: 'Cần xử lý',
    trendTone: 'warning',
  },
  {
    label: 'Đã xác nhận',
    value: '8',
    trend: 'Đang ổn định',
    trendTone: 'success',
  },
  {
    label: 'Doanh thu tháng',
    value: '28.400.000₫',
    trend: 'Cập nhật theo tháng',
    trendTone: 'accent',
  },
]

function isAbortError(error: unknown): boolean {
  return (
    error instanceof Error &&
    (error.name === 'AbortError' || error.name === 'CanceledError' || error.name === 'ERR_CANCELED')
  )
}

function parseDisplayDate(value: string): Date {
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value)
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return 'Chưa cập nhật'

  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parseDisplayDate(value))
}

function formatBookingAmount(booking: AdminBookingRaw): string {
  if (booking.amount_formatted) return booking.amount_formatted
  if (typeof booking.amount === 'number') return formatVND(booking.amount)
  return 'Chưa cập nhật'
}

function renderLoadingCards(count: number): React.ReactNode {
  return (
    <div className="space-y-3">
      {Array.from({ length: count }, (_, index) => (
        <div
          key={index}
          className="h-28 animate-pulse rounded-2xl border border-stone-200 bg-stone-100/80"
        />
      ))}
    </div>
  )
}

const trendClassNames: Record<MetricCard['trendTone'], string> = {
  success: 'bg-emerald-50 text-emerald-700',
  warning: 'bg-amber-50 text-amber-700',
  accent: 'bg-violet-50 text-violet-700',
}

const AdminDashboard: React.FC = () => {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  const [activeTab, setActiveTab] = useState<DashboardTab>('bookings')
  const [bookingPage, setBookingPage] = useState(1)

  const [bookings, setBookings] = useState<AdminBookingRaw[]>([])
  const [bookingsMeta, setBookingsMeta] = useState<PaginationMeta>(defaultPagination)
  const [bookingsLoading, setBookingsLoading] = useState(true)
  const [bookingsError, setBookingsError] = useState<string | null>(null)

  const [trashedBookings, setTrashedBookings] = useState<AdminBookingRaw[]>([])
  const [trashedLoading, setTrashedLoading] = useState(isAdmin)
  const [trashedError, setTrashedError] = useState<string | null>(null)

  const [contactMessages, setContactMessages] = useState<ContactMessageRaw[]>([])
  const [contactsLoading, setContactsLoading] = useState(isAdmin)
  const [contactsError, setContactsError] = useState<string | null>(null)

  const [processingBookingId, setProcessingBookingId] = useState<number | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setBookingsLoading(true)
    setBookingsError(null)

    void fetchAdminBookings(bookingPage, controller.signal)
      .then((result: AdminBookingsPaginatedResult) => {
        if (!active) return
        setBookings(result.bookings)
        setBookingsMeta(result.meta)
      })
      .catch((error: unknown) => {
        if (!active || isAbortError(error)) return
        setBookingsError('Không thể tải danh sách đặt phòng gần đây.')
      })
      .finally(() => {
        if (active) {
          setBookingsLoading(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [bookingPage])

  useEffect(() => {
    if (!isAdmin) {
      setActiveTab('bookings')
      setTrashedBookings([])
      setContactMessages([])
      setTrashedLoading(false)
      setContactsLoading(false)
      setTrashedError(null)
      setContactsError(null)
      return
    }

    const controller = new AbortController()
    let active = true

    setTrashedLoading(true)
    setContactsLoading(true)
    setTrashedError(null)
    setContactsError(null)

    void Promise.allSettled([
      fetchTrashedBookings(controller.signal),
      fetchContactMessages(controller.signal),
    ]).then(results => {
      if (!active) return

      const [trashedResult, contactsResult] = results

      if (trashedResult.status === 'fulfilled') {
        setTrashedBookings(trashedResult.value.bookings)
      } else if (!isAbortError(trashedResult.reason)) {
        setTrashedError('Không thể tải danh sách đặt phòng đã xóa.')
      }

      if (contactsResult.status === 'fulfilled') {
        setContactMessages(contactsResult.value)
      } else if (!isAbortError(contactsResult.reason)) {
        setContactsError('Không thể tải tin nhắn liên hệ.')
      }

      setTrashedLoading(false)
      setContactsLoading(false)
    })

    return () => {
      active = false
      controller.abort()
    }
  }, [isAdmin])

  const tabs: Array<{ id: DashboardTab; label: string }> = [
    { id: 'bookings', label: 'Đặt phòng' },
    ...(isAdmin
      ? [
          { id: 'trashed' as const, label: 'Đã xóa' },
          { id: 'contacts' as const, label: 'Liên hệ' },
        ]
      : []),
  ]

  const handleRestore = async (bookingId: number) => {
    setProcessingBookingId(bookingId)

    try {
      await restoreBooking(bookingId)

      const [nextBookings, nextTrashed] = await Promise.all([
        fetchAdminBookings(bookingPage),
        fetchTrashedBookings(),
      ])

      setBookings(nextBookings.bookings)
      setBookingsMeta(nextBookings.meta)
      setTrashedBookings(nextTrashed.bookings)
      setBookingsError(null)
      setTrashedError(null)
      showToast.success('Đã khôi phục đặt phòng.')
    } catch (error) {
      showToast.error(getErrorMessage(error))
    } finally {
      setProcessingBookingId(null)
    }
  }

  const handleForceDelete = async (bookingId: number) => {
    if (!window.confirm('Xóa vĩnh viễn đặt phòng này? Thao tác này không thể hoàn tác.')) {
      return
    }

    setProcessingBookingId(bookingId)

    try {
      await forceDeleteBooking(bookingId)

      const [nextBookings, nextTrashed] = await Promise.all([
        fetchAdminBookings(bookingPage),
        fetchTrashedBookings(),
      ])

      setBookings(nextBookings.bookings)
      setBookingsMeta(nextBookings.meta)
      setTrashedBookings(nextTrashed.bookings)
      setBookingsError(null)
      setTrashedError(null)
      showToast.success('Đã xóa vĩnh viễn đặt phòng.')
    } catch (error) {
      showToast.error(getErrorMessage(error))
    } finally {
      setProcessingBookingId(null)
    }
  }

  const renderBookingsPanel = () => {
    if (bookingsLoading) {
      return renderLoadingCards(4)
    }

    if (bookingsError) {
      return (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-5 text-sm text-red-700">
          {bookingsError}
        </div>
      )
    }

    if (bookings.length === 0) {
      return (
        <div className="rounded-2xl border border-dashed border-stone-300 bg-stone-50 px-4 py-8 text-center text-sm text-stone-500">
          Chưa có đặt phòng gần đây.
        </div>
      )
    }

    return (
      <div className="space-y-4">
        <div className="space-y-3">
          {bookings.map(booking => {
            const statusConfig = getStatusConfig(booking.status)

            return (
              <article
                key={booking.id}
                className="rounded-2xl border border-stone-200 bg-[#fcfbf8] p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
              >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <Link
                        to={`/admin/bookings/${booking.id}`}
                        className="text-sm font-semibold text-stone-900 transition hover:text-amber-700"
                      >
                        ĐP #{booking.id}
                      </Link>
                      <span
                        className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${statusConfig.colorClass}`}
                      >
                        {statusConfig.label}
                      </span>
                    </div>
                    <p className="mt-2 text-sm font-medium text-stone-900">{booking.guest_name}</p>
                    <p className="truncate text-sm text-stone-500">{booking.guest_email}</p>
                  </div>

                  <div className="shrink-0 sm:text-right">
                    <p className="text-[11px] uppercase tracking-[0.28em] text-stone-400">
                      Doanh thu
                    </p>
                    <p className="mt-1 text-sm font-semibold text-stone-900">
                      {formatBookingAmount(booking)}
                    </p>
                  </div>
                </div>

                <div className="mt-4 grid gap-3 text-sm text-stone-600 sm:grid-cols-[minmax(0,1fr)_auto]">
                  <div className="min-w-0">
                    <p className="truncate font-medium text-stone-800">
                      {booking.room?.display_name || booking.room?.name || 'Chưa gán phòng'}
                    </p>
                    <p className="mt-1 truncate">
                      {formatDateRangeVN(
                        parseDisplayDate(booking.check_in),
                        parseDisplayDate(booking.check_out)
                      )}{' '}
                      · {booking.nights} đêm
                    </p>
                  </div>
                  <div className="text-stone-500 sm:text-right">
                    <p>Tạo lúc {formatDateTime(booking.created_at)}</p>
                  </div>
                </div>
              </article>
            )
          })}
        </div>

        <div className="flex flex-col gap-3 border-t border-stone-200 pt-4 text-sm text-stone-500 sm:flex-row sm:items-center sm:justify-between">
          <p>
            Trang {bookingsMeta.current_page} / {bookingsMeta.last_page} · {bookingsMeta.total} đặt
            phòng
          </p>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setBookingPage(page => Math.max(1, page - 1))}
              disabled={bookingsMeta.current_page <= 1}
              className="rounded-lg border border-stone-200 px-3 py-2 text-stone-700 transition hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Trang trước
            </button>
            <button
              type="button"
              onClick={() => setBookingPage(page => Math.min(bookingsMeta.last_page, page + 1))}
              disabled={bookingsMeta.current_page >= bookingsMeta.last_page}
              className="rounded-lg border border-stone-200 px-3 py-2 text-stone-700 transition hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Trang sau
            </button>
          </div>
        </div>
      </div>
    )
  }

  const renderTrashedPanel = () => {
    if (trashedLoading) {
      return renderLoadingCards(3)
    }

    if (trashedError) {
      return (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-5 text-sm text-red-700">
          {trashedError}
        </div>
      )
    }

    if (trashedBookings.length === 0) {
      return (
        <div className="rounded-2xl border border-dashed border-stone-300 bg-stone-50 px-4 py-8 text-center text-sm text-stone-500">
          Không có đặt phòng nào trong thùng rác.
        </div>
      )
    }

    return (
      <div className="space-y-3">
        {trashedBookings.map(booking => (
          <article
            key={booking.id}
            className="rounded-2xl border border-stone-200 bg-[#fcfbf8] p-4 shadow-sm"
          >
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="text-sm font-semibold text-stone-900">ĐP #{booking.id}</p>
                  <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">
                    Đã xóa
                  </span>
                </div>
                <p className="mt-2 text-sm font-medium text-stone-900">{booking.guest_name}</p>
                <p className="text-sm text-stone-500">{booking.guest_email}</p>
                <p className="mt-3 text-sm text-stone-600">
                  {booking.room?.display_name || booking.room?.name || 'Chưa gán phòng'} ·{' '}
                  {formatDateRangeVN(
                    parseDisplayDate(booking.check_in),
                    parseDisplayDate(booking.check_out)
                  )}
                </p>
                <p className="mt-1 text-sm text-stone-500">
                  Xóa lúc {formatDateTime(booking.deleted_at)}{' '}
                  {booking.deleted_by ? `· bởi ${booking.deleted_by.name}` : ''}
                </p>
              </div>

              <div className="flex flex-wrap items-center gap-3">
                <button
                  type="button"
                  onClick={() => handleRestore(booking.id)}
                  disabled={processingBookingId === booking.id}
                  className="rounded-lg border border-emerald-600 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Khôi phục
                </button>
                <button
                  type="button"
                  onClick={() => handleForceDelete(booking.id)}
                  disabled={processingBookingId === booking.id}
                  className="rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Xóa vĩnh viễn
                </button>
              </div>
            </div>
          </article>
        ))}
      </div>
    )
  }

  const renderContactsPanel = () => {
    if (contactsLoading) {
      return renderLoadingCards(3)
    }

    if (contactsError) {
      return (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-5 text-sm text-red-700">
          {contactsError}
        </div>
      )
    }

    if (contactMessages.length === 0) {
      return (
        <div className="rounded-2xl border border-dashed border-stone-300 bg-stone-50 px-4 py-8 text-center text-sm text-stone-500">
          Chưa có tin nhắn liên hệ mới.
        </div>
      )
    }

    return (
      <div className="space-y-3">
        {contactMessages.map(message => (
          <article
            key={message.id}
            className="rounded-2xl border border-stone-200 bg-[#fcfbf8] p-4 shadow-sm"
          >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  {!message.read_at && (
                    <span className="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">
                      Mới
                    </span>
                  )}
                  <h3 className="text-sm font-semibold text-stone-900">
                    {message.subject || 'Không có tiêu đề'}
                  </h3>
                </div>
                <p className="mt-2 text-sm text-stone-700">
                  {message.name} · {message.email}
                </p>
                <p className="mt-2 line-clamp-2 text-sm leading-6 text-stone-600">
                  {message.message}
                </p>
              </div>
              <p className="shrink-0 text-sm text-stone-500">
                {formatDateTime(message.created_at)}
              </p>
            </div>
          </article>
        ))}
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <section className="grid grid-cols-2 gap-4 xl:grid-cols-4">
        {metricCards.map(card => (
          <article
            key={card.label}
            className="rounded-xl border border-gray-200 bg-white p-4 shadow-[0_12px_30px_rgba(28,25,23,0.04)]"
          >
            <p className="text-[12px] uppercase tracking-[0.24em] text-stone-400">{card.label}</p>
            <p className="mt-3 text-[20px] font-medium text-stone-950 sm:text-[28px]">
              {card.value}
            </p>
            <span
              className={`mt-3 inline-flex items-center rounded-full px-2.5 py-1 text-[12px] font-medium ${trendClassNames[card.trendTone]}`}
            >
              {card.trend}
            </span>
          </article>
        ))}
      </section>

      <section className="rounded-[20px] border border-stone-200 bg-white/95 p-3 shadow-[0_18px_40px_rgba(28,25,23,0.06)] sm:p-4">
        <div
          role="tablist"
          aria-label="Tác vụ quản trị"
          className="flex flex-wrap gap-2 border-b border-stone-200 pb-3"
        >
          {tabs.map(tab => (
            <button
              key={tab.id}
              id={`admin-tab-${tab.id}`}
              role="tab"
              type="button"
              aria-selected={activeTab === tab.id}
              aria-controls={`admin-panel-${tab.id}`}
              onClick={() => setActiveTab(tab.id)}
              className={`rounded-full px-4 py-2 text-sm font-medium transition ${
                activeTab === tab.id
                  ? 'bg-stone-900 text-white'
                  : 'bg-stone-100 text-stone-600 hover:bg-stone-200'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        <div
          id={`admin-panel-${activeTab}`}
          role="tabpanel"
          aria-labelledby={`admin-tab-${activeTab}`}
          className="pt-4"
        >
          {activeTab === 'bookings' && renderBookingsPanel()}
          {activeTab === 'trashed' && isAdmin && renderTrashedPanel()}
          {activeTab === 'contacts' && isAdmin && renderContactsPanel()}
        </div>
      </section>
    </div>
  )
}

export default AdminDashboard
