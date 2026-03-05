/**
 * Shared Booking Types
 *
 * Cross-feature booking types used by admin, bookings, and booking features.
 */

/**
 * Booking API raw shape from GET /v1/bookings (BookingResource)
 *
 * Matches BookingResource::toArray — only fields confirmed in the resource.
 * Optional fields use `$this->when()` in Laravel and may be absent.
 */
export interface BookingApiRaw {
  id: number
  room_id: number
  user_id: number
  check_in: string
  check_out: string
  guest_name: string
  guest_email: string
  status: string
  status_label: string | null
  nights: number
  amount?: number
  amount_formatted?: string
  created_at: string
  updated_at: string
}
