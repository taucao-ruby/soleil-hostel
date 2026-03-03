import React, { useState, useEffect, useCallback, useRef } from 'react'
import {
  fetchAdminBookings,
  fetchTrashedBookings,
  fetchContactMessages,
  restoreBooking,
  forceDeleteBooking,
} from './admin.api'
import type { AdminBookingRaw, ContactMessageRaw, PaginationMeta } from './admin.types'
import { getStatusConfig, formatDateRangeVN, formatDateVN } from '@/shared/lib/booking.utils'
import Skeleton from '@/shared/components/ui/Skeleton'
import Button from '@/shared/components/ui/Button'
import ConfirmDialog from '@/shared/components/ui/ConfirmDialog'
import { showToast, getErrorMessage } from '@/shared/utils/toast'

// ── Types ───────────────────────────────────────────────

type AdminTab = 'bookings' | 'trashed' | 'contacts'

interface PaginatedFetchState<T> {
  data: T[]
  meta: PaginationMeta | null
  isLoading: boolean
  isError: boolean
  hasFetched: boolean
}

interface FetchState<T> {
  data: T[]
  isLoading: boolean
  isError: boolean
  hasFetched: boolean
}

// ── Generic fetch hook (non-paginated, for contacts) ──

function useAdminFetch<T>(fetchFn: (signal?: AbortSignal) => Promise<T[]>, shouldFetch: boolean) {
  const [state, setState] = useState<FetchState<T>>({
    data: [],
    isLoading: false,
    isError: false,
    hasFetched: false,
  })
  const mountedRef = useRef(true)

  const fetchData = useCallback(
    async (signal?: AbortSignal) => {
      setState(s => ({ ...s, isLoading: true, isError: false }))
      try {
        const data = await fetchFn(signal)
        if (mountedRef.current) {
          setState({ data, isLoading: false, isError: false, hasFetched: true })
        }
      } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') return
        if (mountedRef.current) {
          setState(s => ({ ...s, isLoading: false, isError: true, hasFetched: true }))
        }
      }
    },
    [fetchFn]
  )

  useEffect(() => {
    if (!shouldFetch || state.hasFetched) return
    mountedRef.current = true
    const controller = new AbortController()
    fetchData(controller.signal)
    return () => {
      mountedRef.current = false
      controller.abort()
    }
  }, [shouldFetch, state.hasFetched, fetchData])

  const refetch = useCallback(() => {
    setState(s => ({ ...s, hasFetched: false }))
  }, [])

  return { ...state, refetch }
}

// ── Paginated fetch hook (for bookings + trashed) ──

function useAdminPaginatedFetch<T>(
  fetchFn: (page: number, signal?: AbortSignal) => Promise<{ bookings: T[]; meta: PaginationMeta }>,
  shouldFetch: boolean
) {
  const [page, setPage] = useState(1)
  const [state, setState] = useState<PaginatedFetchState<T>>({
    data: [],
    meta: null,
    isLoading: false,
    isError: false,
    hasFetched: false,
  })
  const mountedRef = useRef(true)

  const fetchData = useCallback(
    async (targetPage: number, signal?: AbortSignal) => {
      setState(s => ({ ...s, isLoading: true, isError: false }))
      try {
        const result = await fetchFn(targetPage, signal)
        if (mountedRef.current) {
          setState({
            data: result.bookings,
            meta: result.meta,
            isLoading: false,
            isError: false,
            hasFetched: true,
          })
        }
      } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') return
        if (mountedRef.current) {
          setState(s => ({ ...s, isLoading: false, isError: true, hasFetched: true }))
        }
      }
    },
    [fetchFn]
  )

  useEffect(() => {
    if (!shouldFetch) return
    mountedRef.current = true
    const controller = new AbortController()
    fetchData(page, controller.signal)
    return () => {
      mountedRef.current = false
      controller.abort()
    }
  }, [shouldFetch, page, fetchData])

  const refetch = useCallback(() => {
    setState(s => ({ ...s, hasFetched: false }))
    // Trigger re-fetch via useEffect by resetting hasFetched
    // The effect depends on shouldFetch/page/fetchData, so we force via state change
    setPage(p => p)
  }, [])

  const goToPage = useCallback((newPage: number) => {
    setPage(newPage)
  }, [])

  return { ...state, page, goToPage, refetch }
}

// ── Trashed fetch wrapper (adapts non-paginated trashed to paginated shape) ──

function useTrashedFetch(shouldFetch: boolean) {
  return useAdminPaginatedFetch(
    useCallback((_page: number, signal?: AbortSignal) => fetchTrashedBookings(signal), []),
    shouldFetch
  )
}

// ── Tab config ──────────────────────────────────────────

const TABS: { key: AdminTab; label: string }[] = [
  { key: 'bookings', label: 'Đặt phòng' },
  { key: 'trashed', label: 'Đã xóa' },
  { key: 'contacts', label: 'Liên hệ' },
]

// ── Skeleton ────────────────────────────────────────────

const ListSkeleton: React.FC = () => (
  <div className="space-y-3">
    {[0, 1, 2, 3].map(i => (
      <Skeleton key={i} width="100%" height="5rem" rounded="lg" />
    ))}
  </div>
)

// ── Empty state ─────────────────────────────────────────

const EmptyState: React.FC<{ message: string }> = ({ message }) => (
  <div className="p-8 text-center bg-white border border-gray-200 rounded-xl">
    <p className="text-gray-500">{message}</p>
  </div>
)

// ── Error state ─────────────────────────────────────────

const ErrorState: React.FC<{ message: string; onRetry: () => void }> = ({ message, onRetry }) => (
  <div className="p-6 text-center bg-white border border-red-200 rounded-xl">
    <p className="text-red-600 mb-3">{message}</p>
    <Button variant="outline" size="sm" onClick={onRetry}>
      Thử lại
    </Button>
  </div>
)

// ── Pagination controls ─────────────────────────────────

const PaginationControls: React.FC<{
  meta: PaginationMeta
  onPageChange: (page: number) => void
}> = ({ meta, onPageChange }) => {
  if (meta.last_page <= 1) return null

  return (
    <div className="flex items-center justify-center gap-4 mt-4">
      <Button
        variant="outline"
        size="sm"
        disabled={meta.current_page <= 1}
        onClick={() => onPageChange(meta.current_page - 1)}
      >
        Trước
      </Button>
      <span className="text-sm text-gray-600">
        Trang {meta.current_page} / {meta.last_page}
      </span>
      <Button
        variant="outline"
        size="sm"
        disabled={meta.current_page >= meta.last_page}
        onClick={() => onPageChange(meta.current_page + 1)}
      >
        Sau
      </Button>
    </div>
  )
}

// ── Booking card (admin) ────────────────────────────────

interface AdminBookingCardProps {
  booking: AdminBookingRaw
  onRestore?: (id: number) => void
  onForceDelete?: (id: number) => void
  isActionPending?: boolean
}

const AdminBookingCard: React.FC<AdminBookingCardProps> = ({
  booking,
  onRestore,
  onForceDelete,
  isActionPending = false,
}) => {
  const statusConfig = getStatusConfig(booking.status)
  const checkIn = new Date(booking.check_in)
  const checkOut = new Date(booking.check_out)

  return (
    <div className="p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="text-sm font-medium text-gray-900">{booking.guest_name}</p>
          <p className="mt-1 text-sm text-gray-500">
            {formatDateRangeVN(checkIn, checkOut)} · {booking.nights} đêm
          </p>
          {booking.amount_formatted && (
            <p className="mt-0.5 text-sm text-gray-500">{booking.amount_formatted}</p>
          )}
        </div>
        <span
          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap ${statusConfig.colorClass}`}
        >
          {statusConfig.label}
        </span>
      </div>
      {booking.is_trashed && booking.deleted_at && (
        <div className="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-400">
          Xóa lúc {formatDateVN(new Date(booking.deleted_at))}
          {booking.deleted_by && ` bởi ${booking.deleted_by.name}`}
        </div>
      )}
      {(onRestore || onForceDelete) && (
        <div className="mt-3 pt-3 border-t border-gray-100 flex gap-2">
          {onRestore && (
            <Button
              variant="outline"
              size="sm"
              disabled={isActionPending}
              onClick={() => onRestore(booking.id)}
            >
              Khôi phục
            </Button>
          )}
          {onForceDelete && (
            <Button
              variant="danger"
              size="sm"
              disabled={isActionPending}
              onClick={() => onForceDelete(booking.id)}
            >
              Xóa vĩnh viễn
            </Button>
          )}
        </div>
      )}
    </div>
  )
}

// ── Contact card ────────────────────────────────────────

const ContactCard: React.FC<{ contact: ContactMessageRaw }> = ({ contact }) => {
  const isUnread = contact.read_at === null

  return (
    <div
      className={`p-4 bg-white border rounded-xl shadow-sm ${isUnread ? 'border-blue-200 bg-blue-50/30' : 'border-gray-200'}`}
    >
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="text-sm font-medium text-gray-900">
            {contact.name}
            {isUnread && (
              <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                Mới
              </span>
            )}
          </p>
          <p className="mt-0.5 text-sm text-gray-600">{contact.email}</p>
          {contact.subject && (
            <p className="mt-1 text-sm font-medium text-gray-700">{contact.subject}</p>
          )}
          <p className="mt-1 text-sm text-gray-500 line-clamp-2">{contact.message}</p>
        </div>
        <span className="text-xs text-gray-400 whitespace-nowrap">
          {formatDateVN(new Date(contact.created_at))}
        </span>
      </div>
    </div>
  )
}

// ── Main component ──────────────────────────────────────

const AdminDashboard: React.FC = () => {
  const [activeTab, setActiveTab] = useState<AdminTab>('bookings')

  // Data fetching
  const bookings = useAdminPaginatedFetch(fetchAdminBookings, activeTab === 'bookings')
  const trashed = useTrashedFetch(activeTab === 'trashed')
  const contacts = useAdminFetch(fetchContactMessages, activeTab === 'contacts')

  // Trashed actions state
  const [actionPendingId, setActionPendingId] = useState<number | null>(null)
  const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null)

  const handleRestore = useCallback(
    async (id: number) => {
      setActionPendingId(id)
      try {
        await restoreBooking(id)
        showToast.success('Đã khôi phục đặt phòng.')
        trashed.refetch()
      } catch (err) {
        showToast.error(getErrorMessage(err))
      } finally {
        setActionPendingId(null)
      }
    },
    [trashed]
  )

  const handleForceDelete = useCallback(
    async (id: number) => {
      setActionPendingId(id)
      try {
        await forceDeleteBooking(id)
        showToast.success('Đã xóa vĩnh viễn.')
        setConfirmDeleteId(null)
        trashed.refetch()
      } catch (err) {
        showToast.error(getErrorMessage(err))
      } finally {
        setActionPendingId(null)
      }
    },
    [trashed]
  )

  return (
    <section>
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-bold text-gray-900">Quản trị hệ thống</h2>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 mb-6" role="tablist">
        {TABS.map(tab => (
          <button
            key={tab.key}
            role="tab"
            aria-selected={activeTab === tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-1.5 text-sm font-medium rounded-full transition-colors ${
              activeTab === tab.key
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Bookings tab */}
      {activeTab === 'bookings' && (
        <div role="tabpanel" aria-label="Đặt phòng" className="min-h-[22rem]">
          {bookings.isLoading && <ListSkeleton />}
          {!bookings.isLoading && bookings.isError && (
            <ErrorState message="Không thể tải danh sách đặt phòng." onRetry={bookings.refetch} />
          )}
          {!bookings.isLoading && !bookings.isError && bookings.data.length === 0 && (
            <EmptyState message="Chưa có đặt phòng nào." />
          )}
          {!bookings.isLoading && !bookings.isError && bookings.data.length > 0 && (
            <>
              <div className="space-y-3">
                {bookings.data.map(b => (
                  <AdminBookingCard key={b.id} booking={b} />
                ))}
              </div>
              {bookings.meta && (
                <PaginationControls meta={bookings.meta} onPageChange={bookings.goToPage} />
              )}
            </>
          )}
        </div>
      )}

      {/* Trashed tab */}
      {activeTab === 'trashed' && (
        <div role="tabpanel" aria-label="Đã xóa">
          {trashed.isLoading && <ListSkeleton />}
          {!trashed.isLoading && trashed.isError && (
            <ErrorState message="Không thể tải danh sách đã xóa." onRetry={trashed.refetch} />
          )}
          {!trashed.isLoading && !trashed.isError && trashed.data.length === 0 && (
            <EmptyState message="Không có đặt phòng nào trong thùng rác." />
          )}
          {!trashed.isLoading && !trashed.isError && trashed.data.length > 0 && (
            <div className="space-y-3">
              {trashed.data.map(b => (
                <AdminBookingCard
                  key={b.id}
                  booking={b}
                  onRestore={handleRestore}
                  onForceDelete={(id: number) => setConfirmDeleteId(id)}
                  isActionPending={actionPendingId !== null}
                />
              ))}
            </div>
          )}
        </div>
      )}

      {/* Contacts tab */}
      {activeTab === 'contacts' && (
        <div role="tabpanel" aria-label="Liên hệ">
          {contacts.isLoading && <ListSkeleton />}
          {!contacts.isLoading && contacts.isError && (
            <ErrorState message="Không thể tải tin nhắn liên hệ." onRetry={contacts.refetch} />
          )}
          {!contacts.isLoading && !contacts.isError && contacts.data.length === 0 && (
            <EmptyState message="Chưa có tin nhắn liên hệ nào." />
          )}
          {!contacts.isLoading && !contacts.isError && contacts.data.length > 0 && (
            <div className="space-y-3">
              {contacts.data.map(c => (
                <ContactCard key={c.id} contact={c} />
              ))}
            </div>
          )}
        </div>
      )}

      {/* Force delete confirmation dialog */}
      <ConfirmDialog
        open={confirmDeleteId !== null}
        title="Xóa vĩnh viễn đặt phòng?"
        description="Hành động này không thể hoàn tác. Dữ liệu đặt phòng sẽ bị xóa hoàn toàn khỏi hệ thống."
        confirmLabel="Xóa vĩnh viễn"
        cancelLabel="Quay lại"
        isPending={actionPendingId !== null}
        onConfirm={() => {
          if (confirmDeleteId !== null) {
            handleForceDelete(confirmDeleteId)
          }
        }}
        onCancel={() => setConfirmDeleteId(null)}
      />
    </section>
  )
}

export default AdminDashboard
