import { describe, expect, it } from 'vitest'
import type { BookingFormData, BookingUpdateData } from './booking.types'

const validCreatePayload: BookingFormData = {
  room_id: 10,
  guest_name: 'Nguyen Van A',
  guest_email: 'nguyen@example.com',
  check_in: '2026-06-01',
  check_out: '2026-06-03',
  number_of_guests: 2,
  special_requests: null,
}

const validUpdatePayload: BookingUpdateData = {
  guest_name: 'Nguyen Van A',
  guest_email: 'nguyen@example.com',
  check_in: '2026-06-01',
  check_out: '2026-06-03',
  special_requests: 'Den muon',
}

describe('booking request type contracts', () => {
  it('requires create payload fields persisted by the backend', () => {
    expect(validCreatePayload).toMatchObject({
      room_id: 10,
      number_of_guests: 2,
      special_requests: null,
    })
  })

  it('keeps generic update payload scoped away from room movement and guest-count changes', () => {
    expect('room_id' in validUpdatePayload).toBe(false)
    expect('number_of_guests' in validUpdatePayload).toBe(false)
  })

  it('lets TypeScript reject invalid booking payload shapes', () => {
    // @ts-expect-error room_id and special_requests are required for create payload construction.
    const invalidCreatePayload: BookingFormData = {
      guest_name: 'Nguyen Van A',
      guest_email: 'nguyen@example.com',
      check_in: '2026-06-01',
      check_out: '2026-06-03',
      number_of_guests: 2,
    }

    const invalidUpdatePayload: BookingUpdateData = {
      ...validUpdatePayload,
      // @ts-expect-error room_id is not part of the generic update contract.
      room_id: 11,
    }

    expect(invalidCreatePayload).toBeDefined()
    expect(invalidUpdatePayload).toBeDefined()
  })
})
