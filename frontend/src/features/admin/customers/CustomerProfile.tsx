import React, { useState, useEffect } from 'react'
import { Link, useParams, useNavigate } from 'react-router-dom'
import { getCustomerProfile, getCustomerBookings } from './customer.api'
import type { CustomerProfile as TCustomerProfile } from './customer.api'
import type { BookingDetailRaw } from '@/features/booking/booking.types'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import { BookingStatusBadge } from '@/shared/components/ui/StatusBadge'
import StayJournal from './StayJournal'

// ─── Helpers ────────────────────────────────────────────────────────────────

const VND = new Intl.NumberFormat('vi-VN')

/** DD/MM/YYYY from ISO string */
function fmtDate(iso: string): string {
  if (!iso) return '---'
  try {
    const d = new Date(iso.split('T')[0] + 'T00:00:00')
    return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
  } catch {
    return iso
  }
}

/** Two-letter initials from a Vietnamese full name */
function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

// ─── Compact booking card ────────────────────────────────────────────────────

interface BookingRowProps {
  booking: BookingDetailRaw
}
const BookingRow: React.FC<BookingRowProps> = ({ booking }) => {
  const amount =
    booking.amount_formatted ?? (booking.amount != null ? `${VND.format(booking.amount)}₫` : null)
  const roomName = booking.room?.name ?? '---'
  const nights = booking.nights ?? 1

  return (
    <li className="py-3 border-b border-gray-100 last:border-0">
      <div className="flex items-start justify-between gap-2">
        <Link
          to={`/admin/bookings/${booking.id}`}
          className="text-[13px] font-medium text-[#1C1A17] hover:text-[#C9973A] transition-colors line-clamp-1 flex-1"
        >
          {roomName}
        </Link>
        <BookingStatusBadge status={booking.status} />
      </div>
      <div className="mt-1 flex items-center justify-between gap-2">
        <span className="text-[12px] text-[#6B6760]">
          {fmtDate(booking.check_in)} → {fmtDate(booking.check_out)}
          <span className="ml-1">· {nights} đêm</span>
        </span>
        {amount && (
          <span className="text-[12px] font-medium text-[#C9973A] flex-shrink-0">{amount}</span>
        )}
      </div>
    </li>
  )
}

// ─── Main component ──────────────────────────────────────────────────────────

const CustomerProfile: React.FC = () => {
  const { email } = useParams<{ email: string }>()
  const navigate = useNavigate()

  const [profile, setProfile] = useState<TCustomerProfile | null>(null)
  const [bookings, setBookings] = useState<BookingDetailRaw[]>([])
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    if (!email) {
      navigate('/admin/customers')
      return
    }

    const controller = new AbortController()

    const loadData = async () => {
      setIsLoading(true)
      try {
        const [profData, bookingsData] = await Promise.all([
          getCustomerProfile(email),
          getCustomerBookings(email),
        ])
        if (!controller.signal.aborted) {
          setProfile(profData)
          setBookings(bookingsData)
        }
      } catch {
        // fetch error handled silently
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false)
        }
      }
    }

    void loadData()
    return () => controller.abort()
  }, [email, navigate])

  // ── Loading ────────────────────────────────────────────────────────────────
  if (isLoading) {
    return (
      <div className="py-16 flex justify-center">
        <LoadingSpinner size="lg" message="Đang tải hồ sơ khách hàng..." />
      </div>
    )
  }

  // ── Not found ──────────────────────────────────────────────────────────────
  if (!profile) {
    return (
      <div className="rounded-2xl border border-gray-200 bg-white p-12 text-center text-[#6B6760]">
        <p className="mb-4 text-sm">Không tìm thấy thông tin khách hàng.</p>
        <Link to="/admin/customers" className="text-[#C9973A] text-sm hover:underline">
          ← Quay lại danh sách
        </Link>
      </div>
    )
  }

  // ── Render ─────────────────────────────────────────────────────────────────
  return (
    <div className="space-y-5">
      {/* Breadcrumb */}
      <nav
        className="text-[13px] text-[#6B6760] flex items-center gap-1"
        aria-label="Điều hướng breadcrumb"
      >
        <Link to="/admin/customers" className="hover:text-[#1C1A17] transition-colors">
          Khách hàng
        </Link>
        <span aria-hidden="true" className="text-[#E2DDD6]">
          /
        </span>
        <span className="text-[#1C1A17] font-medium">{profile.name}</span>
      </nav>

      {/* Profile header card */}
      <div className="rounded-2xl border border-[#E2DDD6] bg-white p-6 flex gap-4">
        {/* Avatar */}
        <div
          className="w-16 h-16 rounded-full bg-amber-100 text-amber-800 flex-shrink-0 flex items-center justify-center"
          style={{ fontSize: '20px', fontWeight: 500 }}
          aria-hidden="true"
        >
          {getInitials(profile.name)}
        </div>

        {/* Info */}
        <div className="min-w-0 flex-1">
          <h2 className="text-[#1C1A17] leading-snug" style={{ fontSize: '20px', fontWeight: 500 }}>
            {profile.name}
          </h2>
          <p className="font-mono text-[14px] text-[#6B6760] mt-0.5 truncate">{profile.email}</p>

          {/* Role badge */}
          <span className="mt-1.5 inline-flex items-center px-2.5 py-0.5 rounded-full text-[12px] font-medium bg-green-50 text-green-800 border border-green-200">
            Khách hàng
          </span>

          {/* Stats row */}
          <p className="mt-2 text-[13px] text-[#6B6760]">
            Tham gia {fmtDate(profile.first_visit)}
            <span className="mx-1.5 text-[#E2DDD6]" aria-hidden="true">
              ·
            </span>
            Tổng {profile.total_stays} đặt phòng
          </p>
        </div>
      </div>

      {/* 2-col layout */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
        {/* LEFT — Lịch sử đặt phòng */}
        <section aria-label="Lịch sử đặt phòng">
          <h3 className="text-[13px] font-medium text-[#6B6760] uppercase tracking-wider mb-3">
            Đặt phòng
          </h3>
          <div className="rounded-2xl border border-[#E2DDD6] bg-white px-4">
            {bookings.length === 0 ? (
              <p className="py-8 text-center text-[13px] text-[#6B6760]">
                Chưa có lịch sử đặt phòng.
              </p>
            ) : (
              <ul>
                {bookings.map(b => (
                  <BookingRow key={b.id} booking={b} />
                ))}
              </ul>
            )}
          </div>
        </section>

        {/* RIGHT — Nhật ký lưu trú */}
        <section aria-label="Nhật ký lưu trú">
          <h3 className="text-[13px] font-medium text-[#6B6760] uppercase tracking-wider mb-3">
            Nhật ký lưu trú
          </h3>
          <StayJournal bookings={bookings} />
        </section>
      </div>
    </div>
  )
}

export default CustomerProfile
