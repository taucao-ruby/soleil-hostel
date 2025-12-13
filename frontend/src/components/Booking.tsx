import React, { useState } from 'react'
import api from '../services/api'
import { escapeHtml, isValidEmail } from '../utils/security'

interface BookingProps {
  dark?: boolean
}

const Booking: React.FC<BookingProps> = ({ dark }) => {
  const [form, setForm] = useState({
    room_id: '',
    check_in: '',
    check_out: '',
    guests: 1,
    guest_name: '',
    guest_email: '',
    phone: '',
  })

  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setForm({ ...form, [e.target.name]: e.target.value })
    setError(null)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setSuccess(false)

    // Check if user is authenticated
    const token = localStorage.getItem('access_token')
    if (!token) {
      setError('Please log in first to make a booking')
      return
    }

    // Validation
    if (!form.room_id) {
      setError('Please select a room')
      return
    }

    const checkInDate = new Date(form.check_in)
    const checkOutDate = new Date(form.check_out)

    if (checkInDate >= checkOutDate) {
      setError('Check-out date must be after check-in date')
      return
    }

    if (!form.guest_name.trim() || form.guest_name.length > 255) {
      setError('Name is required and must be less than 255 characters')
      return
    }

    if (!isValidEmail(form.guest_email)) {
      setError('Valid email is required')
      return
    }

    setLoading(true)

    try {
      await api.post('/bookings', {
        room_id: parseInt(form.room_id),
        check_in: form.check_in,
        check_out: form.check_out,
        guest_name: escapeHtml(form.guest_name),
        guest_email: escapeHtml(form.guest_email),
      })

      setSuccess(true)
      setForm({
        room_id: '',
        check_in: '',
        check_out: '',
        guests: 1,
        guest_name: '',
        guest_email: '',
        phone: '',
      })

      setTimeout(() => setSuccess(false), 5000)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMsg =
        error?.response?.data?.message || error?.message || 'Failed to create booking'
      setError(errorMsg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="max-w-lg p-1 mx-auto" aria-labelledby="booking-heading">
      <div
        className={
          `rounded-2xl shadow-2xl p-1 animate-fade-in ` +
          (dark
            ? 'bg-gradient-to-br from-gray-800 via-gray-700 to-gray-900'
            : 'bg-gradient-to-br from-blue-400 via-yellow-200 to-pink-300')
        }
      >
        <div className={`rounded-2xl p-8 ` + (dark ? 'bg-gray-900 text-gray-100' : 'bg-white')}>
          <h2
            id="booking-heading"
            className={
              `text-3xl font-extrabold mb-6 text-transparent bg-clip-text drop-shadow-lg ` +
              (dark
                ? 'bg-gradient-to-r from-gray-400 via-gray-500 to-gray-700'
                : 'bg-gradient-to-r from-blue-600 via-yellow-500 to-pink-500')
            }
          >
            Book a Room
          </h2>

          {error && (
            <div
              role="alert"
              aria-live="assertive"
              id="booking-error"
              className="p-3 mb-4 text-sm text-red-700 bg-red-100 border border-red-500 rounded-lg"
            >
              {error}
            </div>
          )}

          {success && (
            <div
              role="status"
              aria-live="polite"
              className="p-3 mb-4 text-sm text-green-700 bg-green-100 border border-green-500 rounded-lg"
            >
              ✓ Booking created successfully! Check your email for confirmation.
            </div>
          )}

          <form
            onSubmit={handleSubmit}
            className="space-y-5"
            aria-labelledby="booking-heading"
            aria-describedby={error ? 'booking-error' : undefined}
          >
            <div>
              <label htmlFor="room_id" className="block mb-1 text-sm font-medium">
                Room Type
              </label>
              <select
                id="room_id"
                name="room_id"
                value={form.room_id}
                onChange={handleChange}
                className={
                  `w-full p-3 border-2 rounded-lg focus:outline-none transition ` +
                  (dark
                    ? 'border-gray-700 focus:ring-2 focus:ring-gray-500 bg-gray-800 text-gray-100'
                    : 'border-blue-200 focus:ring-2 focus:ring-blue-400')
                }
                required
                aria-required="true"
                aria-invalid={error && !form.room_id ? 'true' : 'false'}
              >
                <option value="">Select Room</option>
                <option value="1">Single Room - $50/night</option>
                <option value="2">Double Room - $75/night</option>
                <option value="3">Suite - $120/night</option>
              </select>
            </div>
            <div className="flex gap-4">
              <div className="w-1/2">
                <label htmlFor="check_in" className="block mb-1 text-sm font-medium">
                  Check-in Date
                </label>
                <input
                  type="date"
                  id="check_in"
                  name="check_in"
                  value={form.check_in}
                  onChange={handleChange}
                  className={
                    `w-full p-3 border-2 rounded-lg focus:outline-none transition ` +
                    (dark
                      ? 'border-gray-700 focus:ring-2 focus:ring-gray-500 bg-gray-800 text-gray-100'
                      : 'border-yellow-200 focus:ring-2 focus:ring-yellow-400')
                  }
                  required
                  aria-required="true"
                  aria-label="Check-in date"
                />
              </div>
              <div className="w-1/2">
                <label htmlFor="check_out" className="block mb-1 text-sm font-medium">
                  Check-out Date
                </label>
                <input
                  type="date"
                  id="check_out"
                  name="check_out"
                  value={form.check_out}
                  onChange={handleChange}
                  className={
                    `w-full p-3 border-2 rounded-lg focus:outline-none transition ` +
                    (dark
                      ? 'border-gray-700 focus:ring-2 focus:ring-gray-500 bg-gray-800 text-gray-100'
                      : 'border-pink-200 focus:ring-2 focus:ring-pink-400')
                  }
                  required
                  aria-required="true"
                  aria-label="Check-out date"
                />
              </div>
            </div>
            <div>
              <label htmlFor="guest_name" className="block mb-1 text-sm font-medium">
                Full Name
              </label>
              <input
                type="text"
                id="guest_name"
                name="guest_name"
                value={form.guest_name}
                onChange={handleChange}
                className={
                  `w-full p-3 border-2 rounded-lg focus:outline-none transition ` +
                  (dark
                    ? 'border-gray-700 focus:ring-2 focus:ring-gray-500 bg-gray-800 text-gray-100'
                    : 'border-yellow-200 focus:ring-2 focus:ring-yellow-400')
                }
                placeholder="Your Name"
                required
                aria-required="true"
                aria-invalid={error && !form.guest_name.trim() ? 'true' : 'false'}
                maxLength={255}
              />
            </div>
            <div>
              <label htmlFor="guest_email" className="block mb-1 text-sm font-medium">
                Email Address
              </label>
              <input
                type="email"
                id="guest_email"
                name="guest_email"
                value={form.guest_email}
                onChange={handleChange}
                className={
                  `w-full p-3 border-2 rounded-lg focus:outline-none transition ` +
                  (dark
                    ? 'border-gray-700 focus:ring-2 focus:ring-gray-500 bg-gray-800 text-gray-100'
                    : 'border-pink-200 focus:ring-2 focus:ring-pink-400')
                }
                placeholder="your@email.com"
                required
                aria-required="true"
                aria-invalid={error && !isValidEmail(form.guest_email) ? 'true' : 'false'}
                autoComplete="email"
              />
            </div>
            <div>
              <label htmlFor="phone" className="block mb-1 text-sm font-medium">
                Phone Number <span className="text-gray-500">(optional)</span>
              </label>
              <input
                type="tel"
                id="phone"
                name="phone"
                value={form.phone}
                onChange={handleChange}
                className={
                  `w-full p-3 border-2 rounded-lg focus:outline-none transition ` +
                  (dark
                    ? 'border-gray-700 focus:ring-2 focus:ring-gray-500 bg-gray-800 text-gray-100'
                    : 'border-blue-200 focus:ring-2 focus:ring-blue-400')
                }
                placeholder="+1 (555) 000-0000"
                aria-label="Phone number (optional)"
                autoComplete="tel"
              />
            </div>
            <button
              type="submit"
              disabled={loading}
              className={
                `w-full py-3 rounded-lg font-bold text-lg shadow-lg hover:scale-105 transition-transform duration-200 disabled:opacity-50 disabled:cursor-not-allowed ` +
                (dark
                  ? 'bg-gradient-to-r from-gray-700 via-gray-600 to-gray-900 text-yellow-300'
                  : 'bg-gradient-to-r from-blue-500 via-yellow-400 to-pink-500 text-white')
              }
              aria-label={loading ? 'Submitting booking request' : 'Submit booking form'}
              aria-busy={loading}
              aria-disabled={loading}
            >
              {loading && (
                <span className="inline-block mr-2" aria-hidden="true">
                  <svg
                    className="inline w-5 h-5 animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    ></circle>
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                  </svg>
                </span>
              )}
              {loading ? 'Booking...' : 'Book Now'}
            </button>
          </form>
        </div>
      </div>
      <style>{`
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
        .animate-fade-in { animation: fade-in 1s ease; }
      `}</style>
    </section>
  )
}

export default Booking
