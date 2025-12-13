import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { createBooking } from './booking.api'
import { getRooms } from '../rooms/room.api'
import { Room } from '../rooms/room.types'
import { BookingFormData } from './booking.types'
import {
  validateBookingForm,
  getMinCheckInDate,
  getMinCheckOutDate,
  calculateNights,
} from './booking.validation'
import { sanitizeInput } from '@/shared/utils/security'

/**
 * BookingForm Component
 *
 * Complete booking form with:
 * - Room selection dropdown
 * - Guest name and email
 * - Check-in/out date pickers
 * - Number of guests
 * - Special requests (optional)
 * - Price calculation
 * - Full validation
 */

const BookingForm: React.FC = () => {
  const navigate = useNavigate()

  // Form state
  const [formData, setFormData] = useState({
    room_id: null as number | null,
    guest_name: '',
    guest_email: '',
    check_in: '',
    check_out: '',
    number_of_guests: 1,
    special_requests: '',
  })

  // UI state
  const [rooms, setRooms] = useState<Room[]>([])
  const [loadingRooms, setLoadingRooms] = useState(true)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(false)
  const [success, setSuccess] = useState(false)

  // Fetch available rooms on mount
  useEffect(() => {
    const fetchRooms = async () => {
      try {
        const data = await getRooms()
        // Filter only available rooms
        const availableRooms = data.filter(room => room.status === 'available')
        setRooms(availableRooms)
      } catch (err) {
        console.error('Failed to fetch rooms:', err)
      } finally {
        setLoadingRooms(false)
      }
    }

    fetchRooms()
  }, [])

  // Calculate selected room price
  const selectedRoom = rooms.find(r => r.id === formData.room_id)
  const nights =
    formData.check_in && formData.check_out
      ? calculateNights(formData.check_in, formData.check_out)
      : 0
  const totalPrice = selectedRoom && nights > 0 ? selectedRoom.price * nights : 0

  /**
   * Handle form submission
   */
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    // Validate form
    const validationErrors = validateBookingForm(formData)
    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors as Record<string, string>)
      return
    }

    setLoading(true)
    setSuccess(false)

    try {
      // Sanitize text inputs
      const bookingData: BookingFormData = {
        room_id: formData.room_id!,
        guest_name: sanitizeInput(formData.guest_name),
        guest_email: sanitizeInput(formData.guest_email),
        check_in: formData.check_in,
        check_out: formData.check_out,
        number_of_guests: formData.number_of_guests,
        special_requests: formData.special_requests
          ? sanitizeInput(formData.special_requests)
          : undefined,
      }

      await createBooking(bookingData)

      setSuccess(true)

      // Redirect to success page or bookings list
      setTimeout(() => {
        navigate('/dashboard')
      }, 2000)
    } catch (err) {
      console.error('Booking failed:', err)
      const errorMessage =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'Failed to create booking. Please try again.'
      setErrors({ submit: errorMessage })
    } finally {
      setLoading(false)
    }
  }

  /**
   * Handle input changes
   */
  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value } = e.target

    // Convert to appropriate type
    let parsedValue: string | number | null = value
    if (name === 'room_id' || name === 'number_of_guests') {
      parsedValue = value ? parseInt(value, 10) : null
    }

    setFormData(prev => ({
      ...prev,
      [name]: parsedValue,
    }))

    // Clear error for this field
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: '' }))
    }
  }

  return (
    <div className="min-h-screen px-4 py-12 bg-gray-50">
      <div className="max-w-3xl mx-auto">
        {/* Header */}
        <div className="mb-8 text-center">
          <h1 className="mb-2 text-4xl font-bold text-gray-900">Book Your Stay</h1>
          <p className="text-gray-600">Fill in the details to reserve your room</p>
        </div>

        {/* Booking Form Card */}
        <div className="p-8 bg-white shadow-xl rounded-2xl">
          <form onSubmit={handleSubmit} noValidate>
            {/* Success Message */}
            {success && (
              <div className="p-4 mb-6 border border-green-200 rounded-lg bg-green-50">
                <p className="text-sm font-medium text-green-800">
                  ✓ Booking created successfully! Redirecting...
                </p>
              </div>
            )}

            {/* Error Message */}
            {errors.submit && (
              <div className="p-4 mb-6 border border-red-200 rounded-lg bg-red-50">
                <p className="text-sm font-medium text-red-800">{errors.submit}</p>
              </div>
            )}

            {/* Room Selection */}
            <div className="mb-6">
              <label htmlFor="room_id" className="block mb-2 text-sm font-semibold text-gray-700">
                Select Room *
              </label>
              {loadingRooms ? (
                <div className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50">
                  Loading rooms...
                </div>
              ) : (
                <select
                  id="room_id"
                  name="room_id"
                  value={formData.room_id || ''}
                  onChange={handleChange}
                  disabled={loading}
                  className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                    errors.room_id
                      ? 'border-red-300 focus:ring-red-500'
                      : 'border-gray-300 focus:ring-blue-500'
                  } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                  aria-required="true"
                  aria-invalid={!!errors.room_id}
                  aria-describedby={errors.room_id ? 'room-error' : undefined}
                >
                  <option value="">Choose a room...</option>
                  {rooms.map(room => (
                    <option key={room.id} value={room.id}>
                      {room.name} - ${room.price}/night
                    </option>
                  ))}
                </select>
              )}
              {errors.room_id && (
                <p id="room-error" className="mt-2 text-sm text-red-600">
                  {errors.room_id}
                </p>
              )}
            </div>

            {/* Guest Name */}
            <div className="mb-6">
              <label
                htmlFor="guest_name"
                className="block mb-2 text-sm font-semibold text-gray-700"
              >
                Guest Name *
              </label>
              <input
                type="text"
                id="guest_name"
                name="guest_name"
                value={formData.guest_name}
                onChange={handleChange}
                disabled={loading}
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                  errors.guest_name
                    ? 'border-red-300 focus:ring-red-500'
                    : 'border-gray-300 focus:ring-blue-500'
                } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                placeholder="John Doe"
                aria-required="true"
                aria-invalid={!!errors.guest_name}
                aria-describedby={errors.guest_name ? 'name-error' : undefined}
              />
              {errors.guest_name && (
                <p id="name-error" className="mt-2 text-sm text-red-600">
                  {errors.guest_name}
                </p>
              )}
            </div>

            {/* Guest Email */}
            <div className="mb-6">
              <label
                htmlFor="guest_email"
                className="block mb-2 text-sm font-semibold text-gray-700"
              >
                Email Address *
              </label>
              <input
                type="email"
                id="guest_email"
                name="guest_email"
                value={formData.guest_email}
                onChange={handleChange}
                disabled={loading}
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                  errors.guest_email
                    ? 'border-red-300 focus:ring-red-500'
                    : 'border-gray-300 focus:ring-blue-500'
                } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                placeholder="you@example.com"
                aria-required="true"
                aria-invalid={!!errors.guest_email}
                aria-describedby={errors.guest_email ? 'email-error' : undefined}
              />
              {errors.guest_email && (
                <p id="email-error" className="mt-2 text-sm text-red-600">
                  {errors.guest_email}
                </p>
              )}
            </div>

            {/* Check-in and Check-out Dates */}
            <div className="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2">
              {/* Check-in */}
              <div>
                <label
                  htmlFor="check_in"
                  className="block mb-2 text-sm font-semibold text-gray-700"
                >
                  Check-in Date *
                </label>
                <input
                  type="date"
                  id="check_in"
                  name="check_in"
                  value={formData.check_in}
                  onChange={handleChange}
                  min={getMinCheckInDate()}
                  disabled={loading}
                  className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                    errors.check_in
                      ? 'border-red-300 focus:ring-red-500'
                      : 'border-gray-300 focus:ring-blue-500'
                  } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                  aria-required="true"
                  aria-invalid={!!errors.check_in}
                  aria-describedby={errors.check_in ? 'checkin-error' : undefined}
                />
                {errors.check_in && (
                  <p id="checkin-error" className="mt-2 text-sm text-red-600">
                    {errors.check_in}
                  </p>
                )}
              </div>

              {/* Check-out */}
              <div>
                <label
                  htmlFor="check_out"
                  className="block mb-2 text-sm font-semibold text-gray-700"
                >
                  Check-out Date *
                </label>
                <input
                  type="date"
                  id="check_out"
                  name="check_out"
                  value={formData.check_out}
                  onChange={handleChange}
                  min={getMinCheckOutDate(formData.check_in)}
                  disabled={loading}
                  className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                    errors.check_out
                      ? 'border-red-300 focus:ring-red-500'
                      : 'border-gray-300 focus:ring-blue-500'
                  } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                  aria-required="true"
                  aria-invalid={!!errors.check_out}
                  aria-describedby={errors.check_out ? 'checkout-error' : undefined}
                />
                {errors.check_out && (
                  <p id="checkout-error" className="mt-2 text-sm text-red-600">
                    {errors.check_out}
                  </p>
                )}
              </div>
            </div>

            {/* Number of Guests */}
            <div className="mb-6">
              <label
                htmlFor="number_of_guests"
                className="block mb-2 text-sm font-semibold text-gray-700"
              >
                Number of Guests *
              </label>
              <input
                type="number"
                id="number_of_guests"
                name="number_of_guests"
                value={formData.number_of_guests}
                onChange={handleChange}
                min="1"
                max="10"
                disabled={loading}
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                  errors.number_of_guests
                    ? 'border-red-300 focus:ring-red-500'
                    : 'border-gray-300 focus:ring-blue-500'
                } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                aria-required="true"
                aria-invalid={!!errors.number_of_guests}
                aria-describedby={errors.number_of_guests ? 'guests-error' : undefined}
              />
              {errors.number_of_guests && (
                <p id="guests-error" className="mt-2 text-sm text-red-600">
                  {errors.number_of_guests}
                </p>
              )}
            </div>

            {/* Special Requests */}
            <div className="mb-6">
              <label
                htmlFor="special_requests"
                className="block mb-2 text-sm font-semibold text-gray-700"
              >
                Special Requests (Optional)
              </label>
              <textarea
                id="special_requests"
                name="special_requests"
                value={formData.special_requests}
                onChange={handleChange}
                rows={3}
                disabled={loading}
                className={`w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors resize-none ${
                  loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'
                }`}
                placeholder="Any special requirements or preferences..."
              />
            </div>

            {/* Price Summary */}
            {totalPrice > 0 && (
              <div className="p-4 mb-6 border border-blue-200 rounded-lg bg-blue-50">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-600">
                      {nights} night{nights > 1 ? 's' : ''} × ${selectedRoom?.price}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="text-2xl font-bold text-blue-600">${totalPrice}</p>
                    <p className="text-xs text-gray-500">Total Price</p>
                  </div>
                </div>
              </div>
            )}

            {/* Submit Button */}
            <button
              type="submit"
              disabled={loading}
              className={`w-full py-3 px-4 rounded-lg font-semibold text-white transition-all ${
                loading
                  ? 'bg-blue-400 cursor-not-allowed'
                  : 'bg-blue-600 hover:bg-blue-700 hover:shadow-lg'
              }`}
              aria-busy={loading}
            >
              {loading ? (
                <span className="flex items-center justify-center">
                  <svg
                    className="w-5 h-5 mr-3 -ml-1 text-white animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
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
                  Creating booking...
                </span>
              ) : (
                'Complete Booking'
              )}
            </button>
          </form>
        </div>

        {/* Back Button */}
        <div className="mt-6 text-center">
          <button
            onClick={() => navigate(-1)}
            className="text-sm text-gray-600 transition-colors hover:text-gray-900"
          >
            ← Back
          </button>
        </div>
      </div>
    </div>
  )
}

export default BookingForm
