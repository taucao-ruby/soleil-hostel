import React, { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { createBooking } from './booking.api'
import { getRooms } from '../rooms/room.api'
import type { Room } from '../rooms/room.types'
import type { Booking, BookingFormData } from './booking.types'
import {
  MAX_STAY_DAYS,
  type ValidationErrors,
  calculateNights,
  getMaxCheckOutDate,
  getMinCheckInDate,
  getMinCheckOutDate,
  validateBookingForm,
} from './booking.validation'
import { formatVND } from '@/shared/lib/formatCurrency'

const API_ERROR_MESSAGE =
  'Không thể đặt phòng. Phòng này có thể đã được đặt. Vui lòng thử ngày khác.'

function formatCompactVND(amount: number): string {
  return formatVND(amount).replace(/\s?₫/, '₫')
}

function formatRoomLabel(roomName: string): string {
  return roomName.trim().toLowerCase().startsWith('phòng ') ? roomName : `Phòng ${roomName}`
}

function formatDateDisplay(dateString: string): string {
  const [year, month, day] = dateString.split('-')
  if (!year || !month || !day) {
    return dateString
  }

  return `${day}/${month}/${year}`
}

function parseRoomId(value: string | null): number | null {
  if (!value) {
    return null
  }

  const parsed = Number.parseInt(value, 10)
  return Number.isNaN(parsed) || parsed <= 0 ? null : parsed
}

function parseGuests(value: string | null): number {
  const parsed = value ? Number.parseInt(value, 10) : Number.NaN
  if (Number.isNaN(parsed)) {
    return 1
  }

  return Math.min(10, Math.max(1, parsed))
}

function buildBookingReference(booking: Booking): string {
  const year = booking.created_at
    ? booking.created_at.slice(0, 4)
    : String(new Date().getFullYear())
  return `SOL-${year}-${String(booking.id).padStart(4, '0')}`
}

function InlineSpinner({ label }: { label: string }) {
  return (
    <span className="inline-flex items-center gap-2 text-xs font-medium text-hueMuted">
      <svg
        aria-hidden="true"
        className="h-3.5 w-3.5 animate-spin text-brandAmber"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          className="opacity-30"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          strokeWidth="4"
        />
        <path
          className="opacity-80"
          d="M22 12a10 10 0 00-10-10"
          stroke="currentColor"
          strokeLinecap="round"
          strokeWidth="4"
        />
      </svg>
      {label}
    </span>
  )
}

function getFieldClass(hasError: boolean, disabled = false): string {
  return `w-full rounded-xl border bg-white px-4 py-3 text-sm text-hueBlack outline-none transition focus:ring-2 ${
    hasError
      ? 'border-red-300 focus:border-red-400 focus:ring-red-200'
      : 'border-hueBorder focus:border-brandAmber focus:ring-brandAmber/20'
  } ${disabled ? 'cursor-not-allowed bg-stone-50 text-hueMuted' : ''}`
}

const BookingForm: React.FC = () => {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const [formData, setFormData] = useState(() => ({
    room_id: parseRoomId(searchParams.get('room_id')),
    guest_name: '',
    guest_email: '',
    check_in: searchParams.get('check_in') || '',
    check_out: searchParams.get('check_out') || '',
    number_of_guests: parseGuests(searchParams.get('guests')),
    special_requests: '',
  }))

  const [rooms, setRooms] = useState<Room[]>([])
  const [loadingRooms, setLoadingRooms] = useState(true)
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({})
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isSuccess, setIsSuccess] = useState(false)
  const [bookingReference, setBookingReference] = useState<string | null>(null)
  const [redirectCountdown, setRedirectCountdown] = useState(2)

  useEffect(() => {
    const controller = new AbortController()

    const fetchRooms = async () => {
      try {
        const data = await getRooms(controller.signal)
        if (!controller.signal.aborted) {
          setRooms(data.filter(room => room.status === 'available'))
        }
      } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
          return
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoadingRooms(false)
        }
      }
    }

    void fetchRooms()

    return () => controller.abort()
  }, [])

  useEffect(() => {
    if (!isSuccess) {
      return
    }

    setRedirectCountdown(2)

    const countdownTimer = window.setInterval(() => {
      setRedirectCountdown(current => (current > 0 ? current - 1 : 0))
    }, 1000)

    const redirectTimer = window.setTimeout(() => {
      navigate('/dashboard')
    }, 2000)

    return () => {
      window.clearInterval(countdownTimer)
      window.clearTimeout(redirectTimer)
    }
  }, [isSuccess, navigate])

  const selectedRoom = rooms.find(room => room.id === formData.room_id)
  const nights =
    formData.check_in && formData.check_out
      ? calculateNights(formData.check_in, formData.check_out)
      : 0
  const totalPrice = selectedRoom && nights > 0 ? selectedRoom.price * nights : 0
  const maxCheckOutDate = getMaxCheckOutDate(formData.check_in)
  const roomSelectionMissing = Boolean(formData.room_id && !loadingRooms && !selectedRoom)
  const roomSummaryLabel = loadingRooms
    ? 'Đang tải thông tin phòng...'
    : selectedRoom
      ? formatRoomLabel(selectedRoom.name)
      : roomSelectionMissing
        ? 'Phòng đã chọn không còn trống'
        : 'Chưa chọn phòng'
  const stayLabel =
    formData.check_in && formData.check_out
      ? `${formatDateDisplay(formData.check_in)} → ${formatDateDisplay(formData.check_out)}`
      : 'Chọn ngày nhận phòng và ngày trả phòng'
  const canSubmit = !isSubmitting && !loadingRooms && rooms.length > 0

  const clearFieldError = (fieldName: keyof ValidationErrors) => {
    setFieldErrors(current => {
      if (!current[fieldName]) {
        return current
      }

      const nextErrors = { ...current }
      delete nextErrors[fieldName]
      return nextErrors
    })
  }

  const handleChange = (
    event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value } = event.target

    let parsedValue: string | number | null = value
    if (name === 'room_id' || name === 'number_of_guests') {
      parsedValue = value ? Number.parseInt(value, 10) : null
    }

    setFormData(current => {
      const nextState = {
        ...current,
        [name]: parsedValue,
      }

      if (name === 'check_in' && typeof parsedValue === 'string' && parsedValue) {
        const nextMinCheckOut = getMinCheckOutDate(parsedValue)
        const nextMaxCheckOut = getMaxCheckOutDate(parsedValue)

        if (!nextState.check_out || nextState.check_out <= parsedValue) {
          nextState.check_out = nextMinCheckOut
        }

        if (nextMaxCheckOut && nextState.check_out > nextMaxCheckOut) {
          nextState.check_out = nextMaxCheckOut
        }
      }

      return nextState
    })

    clearFieldError(name as keyof ValidationErrors)

    if (name === 'check_in') {
      clearFieldError('check_out')
    }

    if (submitError) {
      setSubmitError(null)
    }
  }

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    const validationErrors = validateBookingForm(formData)
    if (Object.keys(validationErrors).length > 0) {
      setFieldErrors(validationErrors)
      setSubmitError(null)
      return
    }

    setFieldErrors({})
    setSubmitError(null)
    setIsSubmitting(true)

    try {
      const bookingData: BookingFormData = {
        room_id: formData.room_id!,
        guest_name: formData.guest_name,
        guest_email: formData.guest_email,
        check_in: formData.check_in,
        check_out: formData.check_out,
        number_of_guests: formData.number_of_guests,
        special_requests: formData.special_requests || undefined,
      }

      const booking = await createBooking(bookingData)
      setBookingReference(buildBookingReference(booking))
      setIsSuccess(true)
    } catch {
      setSubmitError(API_ERROR_MESSAGE)
    } finally {
      setIsSubmitting(false)
    }
  }

  if (isSuccess) {
    return (
      <section className="bg-hueSurface px-4 py-14 sm:px-6 sm:py-16">
        <div className="mx-auto max-w-3xl">
          <div
            data-testid="success-message"
            className="rounded-[28px] border border-hueBorder bg-white px-6 py-12 text-center shadow-[0_24px_50px_rgba(28,26,23,0.08)] sm:px-10"
          >
            <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-green-100 text-green-600">
              <svg aria-hidden="true" className="h-10 w-10" fill="none" viewBox="0 0 24 24">
                <path
                  d="M5 12.5l4.2 4.2L19 7"
                  stroke="currentColor"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2.5"
                />
              </svg>
            </div>

            <h1 className="mt-6 text-4xl font-semibold text-hueBlack">Đặt phòng thành công!</h1>

            <div className="mt-8 border-y border-hueBorder py-6">
              <p className="text-sm font-medium uppercase tracking-[0.18em] text-hueMuted">
                Mã đặt phòng
              </p>
              <p
                data-testid="booking-reference"
                className="mt-3 font-mono text-[28px] font-medium tracking-[0.08em] text-brandAmber"
              >
                {bookingReference}
              </p>
            </div>

            <p className="mt-8 text-lg text-hueBlack">
              Quay về trang quản lý sau {redirectCountdown} giây...
            </p>
          </div>
        </div>
      </section>
    )
  }

  return (
    <section className="bg-hueSurface px-4 py-10 sm:px-6 sm:py-14">
      <div className="mx-auto max-w-6xl">
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-8">
          <div
            data-testid="booking-modal"
            className="rounded-[28px] border border-hueBorder bg-white p-6 shadow-[0_24px_50px_rgba(28,26,23,0.08)] sm:p-8"
          >
            <h1 className="text-[22px] font-semibold text-hueBlack">Đặt phòng</h1>

            {submitError && (
              <div
                data-testid="error-message"
                className="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3"
                role="alert"
              >
                <p className="text-sm font-medium leading-6 text-red-800">{submitError}</p>
              </div>
            )}

            <form className="mt-6 space-y-6" noValidate onSubmit={handleSubmit}>
              <div>
                <label htmlFor="room_id" className="mb-2 block text-sm font-medium text-hueBlack">
                  Chọn phòng
                </label>
                <select
                  id="room_id"
                  name="room_id"
                  value={formData.room_id ?? ''}
                  onChange={handleChange}
                  disabled={loadingRooms || isSubmitting || rooms.length === 0}
                  className={getFieldClass(
                    Boolean(fieldErrors.room_id),
                    loadingRooms || isSubmitting || rooms.length === 0
                  )}
                  aria-describedby={fieldErrors.room_id ? 'booking-room-error' : undefined}
                  aria-invalid={fieldErrors.room_id ? 'true' : 'false'}
                >
                  {loadingRooms ? (
                    <option value="">Đang tải danh sách phòng...</option>
                  ) : rooms.length === 0 ? (
                    <option value="">—</option>
                  ) : (
                    <>
                      <option value="">Chọn phòng phù hợp</option>
                      {roomSelectionMissing && formData.room_id && (
                        <option value={formData.room_id}>Phòng đã chọn không còn trống</option>
                      )}
                      {rooms.map(room => (
                        <option key={room.id} value={room.id}>
                          {formatRoomLabel(room.name)} — {formatCompactVND(room.price)}/đêm
                        </option>
                      ))}
                    </>
                  )}
                </select>
                {loadingRooms && (
                  <div className="mt-2">
                    <InlineSpinner label="Đang tải danh sách phòng..." />
                  </div>
                )}
                {!loadingRooms && rooms.length === 0 && (
                  <p role="status" className="mt-2 text-xs font-medium text-amber-700">
                    Không có phòng nào còn trống
                  </p>
                )}
                {fieldErrors.room_id && (
                  <p id="booking-room-error" className="mt-2 text-xs font-medium text-red-700">
                    {fieldErrors.room_id}
                  </p>
                )}
              </div>

              <div className="border-t border-hueBorder pt-6">
                <p className="text-sm font-semibold text-hueBlack">Thông tin khách hàng</p>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  <div>
                    <label
                      htmlFor="guest_name"
                      className="mb-2 block text-sm font-medium text-hueBlack"
                    >
                      Họ và tên
                    </label>
                    <input
                      id="guest_name"
                      name="guest_name"
                      type="text"
                      value={formData.guest_name}
                      onChange={handleChange}
                      disabled={isSubmitting}
                      placeholder="Nguyễn Văn A"
                      className={getFieldClass(Boolean(fieldErrors.guest_name), isSubmitting)}
                      aria-describedby={fieldErrors.guest_name ? 'booking-name-error' : undefined}
                      aria-invalid={fieldErrors.guest_name ? 'true' : 'false'}
                    />
                    {fieldErrors.guest_name && (
                      <p id="booking-name-error" className="mt-2 text-xs font-medium text-red-700">
                        {fieldErrors.guest_name}
                      </p>
                    )}
                  </div>

                  <div>
                    <label
                      htmlFor="guest_email"
                      className="mb-2 block text-sm font-medium text-hueBlack"
                    >
                      Địa chỉ email
                    </label>
                    <input
                      id="guest_email"
                      name="guest_email"
                      type="email"
                      value={formData.guest_email}
                      onChange={handleChange}
                      disabled={isSubmitting}
                      placeholder="user@example.com"
                      className={getFieldClass(Boolean(fieldErrors.guest_email), isSubmitting)}
                      aria-describedby={fieldErrors.guest_email ? 'booking-email-error' : undefined}
                      aria-invalid={fieldErrors.guest_email ? 'true' : 'false'}
                    />
                    {fieldErrors.guest_email && (
                      <p id="booking-email-error" className="mt-2 text-xs font-medium text-red-700">
                        {fieldErrors.guest_email}
                      </p>
                    )}
                  </div>
                </div>
              </div>

              <div className="border-t border-hueBorder pt-6">
                <p className="text-sm font-semibold text-hueBlack">Thời gian lưu trú</p>
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                  <div>
                    <label
                      htmlFor="check_in"
                      className="mb-2 block text-sm font-medium text-hueBlack"
                    >
                      Ngày nhận phòng
                    </label>
                    <input
                      id="check_in"
                      name="check_in"
                      type="date"
                      value={formData.check_in}
                      onChange={handleChange}
                      min={getMinCheckInDate()}
                      disabled={isSubmitting}
                      className={getFieldClass(Boolean(fieldErrors.check_in), isSubmitting)}
                      aria-describedby={fieldErrors.check_in ? 'booking-checkin-error' : undefined}
                      aria-invalid={fieldErrors.check_in ? 'true' : 'false'}
                    />
                    {fieldErrors.check_in && (
                      <p
                        id="booking-checkin-error"
                        className="mt-2 text-xs font-medium text-red-700"
                      >
                        {fieldErrors.check_in}
                      </p>
                    )}
                  </div>

                  <div>
                    <label
                      htmlFor="check_out"
                      className="mb-2 block text-sm font-medium text-hueBlack"
                    >
                      Ngày trả phòng
                    </label>
                    <input
                      id="check_out"
                      name="check_out"
                      type="date"
                      value={formData.check_out}
                      onChange={handleChange}
                      min={getMinCheckOutDate(formData.check_in)}
                      max={maxCheckOutDate}
                      disabled={isSubmitting}
                      className={getFieldClass(Boolean(fieldErrors.check_out), isSubmitting)}
                      aria-describedby={
                        fieldErrors.check_out ? 'booking-checkout-error' : undefined
                      }
                      aria-invalid={fieldErrors.check_out ? 'true' : 'false'}
                    />
                    <p className="mt-2 text-xs text-hueMuted">
                      Tối đa {MAX_STAY_DAYS} ngày cho mỗi lần đặt phòng
                    </p>
                    {fieldErrors.check_out && (
                      <p
                        id="booking-checkout-error"
                        className="mt-2 text-xs font-medium text-red-700"
                      >
                        {fieldErrors.check_out}
                      </p>
                    )}
                  </div>
                </div>
              </div>

              <div>
                <label
                  htmlFor="number_of_guests"
                  className="mb-2 block text-sm font-medium text-hueBlack"
                >
                  Số khách
                </label>
                <input
                  id="number_of_guests"
                  name="number_of_guests"
                  type="number"
                  inputMode="numeric"
                  min={1}
                  max={10}
                  value={formData.number_of_guests}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className={getFieldClass(Boolean(fieldErrors.number_of_guests), isSubmitting)}
                  aria-describedby={
                    fieldErrors.number_of_guests ? 'booking-guests-error' : 'booking-guests-note'
                  }
                  aria-invalid={fieldErrors.number_of_guests ? 'true' : 'false'}
                />
                <p id="booking-guests-note" className="mt-2 text-xs text-hueMuted">
                  Thông tin tham khảo — không ảnh hưởng đặt phòng
                </p>
                {fieldErrors.number_of_guests && (
                  <p id="booking-guests-error" className="mt-2 text-xs font-medium text-red-700">
                    {fieldErrors.number_of_guests}
                  </p>
                )}
              </div>

              <div>
                <label
                  htmlFor="special_requests"
                  className="mb-2 block text-sm font-medium text-hueBlack"
                >
                  Yêu cầu đặc biệt
                </label>
                <p className="mb-2 text-xs text-hueMuted">(Tùy chọn — không đảm bảo)</p>
                <textarea
                  id="special_requests"
                  name="special_requests"
                  rows={3}
                  value={formData.special_requests}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder="Ví dụ: đến muộn, cần giường tầng thấp, hoặc ghi chú thêm"
                  className={`${getFieldClass(false, isSubmitting)} resize-none`}
                />
              </div>

              <button
                type="submit"
                disabled={!canSubmit}
                aria-busy={isSubmitting}
                className="flex w-full items-center justify-center rounded-xl bg-brandAmber px-4 py-3 text-sm font-medium text-hueBlack transition hover:bg-[#b8872a] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brandAmber/30 disabled:cursor-not-allowed disabled:bg-[#d6b173] disabled:text-hueBlack/70"
              >
                {isSubmitting ? <InlineSpinner label="Đang xử lý..." /> : 'Xác nhận đặt phòng →'}
              </button>
            </form>
          </div>

          <aside className="self-start rounded-[28px] border border-hueBorder bg-white p-6 shadow-[0_24px_50px_rgba(28,26,23,0.08)] lg:sticky lg:top-24">
            <h2 className="text-[28px] font-semibold leading-tight text-hueBlack">
              Tóm tắt đặt phòng
            </h2>

            <div className="mt-6">
              <p className="text-xs font-medium uppercase tracking-[0.18em] text-hueMuted">Phòng</p>
              <div className="mt-2">
                {loadingRooms ? (
                  <InlineSpinner label={roomSummaryLabel} />
                ) : (
                  <p className="text-base font-medium text-hueBlack">{roomSummaryLabel}</p>
                )}
              </div>
            </div>

            <div className="mt-6 flex items-start gap-3">
              <div className="mt-0.5 rounded-full bg-amber-50 p-2 text-brandAmber">
                <svg aria-hidden="true" className="h-5 w-5" fill="none" viewBox="0 0 24 24">
                  <path
                    d="M8 2v3M16 2v3M4 9h16M5 5h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"
                    stroke="currentColor"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="1.8"
                  />
                </svg>
              </div>
              <div>
                <p className="text-sm font-medium text-hueBlack">{stayLabel}</p>
                <p className="mt-1 text-sm text-hueMuted">
                  {nights > 0 ? `${nights} đêm` : 'Chọn ngày để tính số đêm'}
                </p>
              </div>
            </div>

            <div className="my-6 h-px bg-hueBorder" />

            <div className="flex items-start justify-between gap-4 text-sm text-hueBlack">
              <span>
                {nights > 0 && selectedRoom
                  ? `${nights} đêm × ${formatCompactVND(selectedRoom.price)}`
                  : selectedRoom
                    ? `1 đêm × ${formatCompactVND(selectedRoom.price)}`
                    : 'Giá phòng'}
              </span>
              <span className="font-medium text-hueMuted">
                {nights > 0 && selectedRoom ? formatCompactVND(totalPrice) : '—'}
              </span>
            </div>

            <div className="mt-6 flex items-end justify-between gap-4">
              <span className="text-base font-medium text-hueBlack">Tổng cộng</span>
              <span className="text-[22px] font-medium leading-none text-brandAmber">
                {totalPrice > 0 ? formatCompactVND(totalPrice) : '—'}
              </span>
            </div>

            <p className="mt-6 text-sm text-hueMuted">Thanh toán tại chỗ · Không cần thẻ</p>
          </aside>
        </div>
      </div>
    </section>
  )
}

export default BookingForm
