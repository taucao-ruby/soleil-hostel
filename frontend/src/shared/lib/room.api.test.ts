import { describe, it, expect, vi, beforeEach } from 'vitest'

const { mockGet, mockPatch } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPatch: vi.fn(),
}))

vi.mock('@/shared/lib/api', () => ({
  default: {
    get: mockGet,
    patch: mockPatch,
  },
}))

import { roomApi } from './room.api'

describe('roomApi.updateReadiness', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('PATCHes the canonical /v1/rooms/{id}/readiness route with readiness_status only', async () => {
    mockPatch.mockResolvedValue({ data: { data: { id: 7, readiness_status: 'occupied' } } })

    const result = await roomApi.updateReadiness(7, 'occupied')

    expect(mockPatch).toHaveBeenCalledTimes(1)
    expect(mockPatch).toHaveBeenCalledWith('/v1/rooms/7/readiness', {
      readiness_status: 'occupied',
    })
    // No deprecated /status route and no full-room read-modify-write GET.
    expect(mockGet).not.toHaveBeenCalled()
    expect(mockPatch).not.toHaveBeenCalledWith('/v1/rooms/7', expect.anything())
    expect(mockPatch).not.toHaveBeenCalledWith('/v1/rooms/7/status', expect.anything())
    expect(result).toEqual({ id: 7, readiness_status: 'occupied' })
  })
})
