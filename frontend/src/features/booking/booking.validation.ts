/**
 * Booking Validation Utilities
 *
 * Client-side validation functions for booking forms
 */

import { isValidEmail } from '@/shared/utils/security'

export interface ValidationErrors {
  room_id?: string
  guest_name?: string
  guest_email?: string
  check_in?: string
  check_out?: string
  number_of_guests?: string
}

/**
 * Validate booking form data
 */
export function validateBookingForm(data: {
  room_id: number | null
  guest_name: string
  guest_email: string
  check_in: string
  check_out: string
  number_of_guests: number
}): ValidationErrors {
  const errors: ValidationErrors = {}

  // Room selection
  if (!data.room_id || data.room_id <= 0) {
    errors.room_id = 'Please select a room'
  }

  // Guest name
  if (!data.guest_name.trim()) {
    errors.guest_name = 'Guest name is required'
  } else if (data.guest_name.trim().length < 2) {
    errors.guest_name = 'Guest name must be at least 2 characters'
  }

  // Guest email
  if (!data.guest_email.trim()) {
    errors.guest_email = 'Email is required'
  } else if (!isValidEmail(data.guest_email)) {
    errors.guest_email = 'Invalid email format'
  }

  // Check-in date
  if (!data.check_in) {
    errors.check_in = 'Check-in date is required'
  } else {
    const checkInDate = new Date(data.check_in)
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    if (checkInDate < today) {
      errors.check_in = 'Check-in date cannot be in the past'
    }
  }

  // Check-out date
  if (!data.check_out) {
    errors.check_out = 'Check-out date is required'
  } else if (data.check_in) {
    const checkInDate = new Date(data.check_in)
    const checkOutDate = new Date(data.check_out)

    if (checkOutDate <= checkInDate) {
      errors.check_out = 'Check-out must be after check-in'
    }

    // Maximum stay validation (e.g., 30 days)
    const diffTime = Math.abs(checkOutDate.getTime() - checkInDate.getTime())
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

    if (diffDays > 30) {
      errors.check_out = 'Maximum stay is 30 days'
    }
  }

  // Number of guests
  if (!data.number_of_guests || data.number_of_guests < 1) {
    errors.number_of_guests = 'At least 1 guest is required'
  } else if (data.number_of_guests > 10) {
    errors.number_of_guests = 'Maximum 10 guests per room'
  }

  return errors
}

/**
 * Calculate number of nights between two dates
 */
export function calculateNights(checkIn: string, checkOut: string): number {
  const checkInDate = new Date(checkIn)
  const checkOutDate = new Date(checkOut)
  const diffTime = Math.abs(checkOutDate.getTime() - checkInDate.getTime())
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
}

/**
 * Format date for input[type="date"]
 * Returns YYYY-MM-DD format
 */
export function formatDateForInput(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

/**
 * Get minimum check-in date (today)
 */
export function getMinCheckInDate(): string {
  return formatDateForInput(new Date())
}

/**
 * Get minimum check-out date (tomorrow)
 */
export function getMinCheckOutDate(checkInDate?: string): string {
  if (checkInDate) {
    const date = new Date(checkInDate)
    date.setDate(date.getDate() + 1)
    return formatDateForInput(date)
  }
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  return formatDateForInput(tomorrow)
}
