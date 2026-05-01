import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import RoomDiscoveryWidget from './RoomDiscoveryWidget'

const { mockApiPost } = vi.hoisted(() => ({
  mockApiPost: vi.fn(),
}))

vi.mock('@/shared/lib/api', () => ({
  default: {
    post: mockApiPost,
  },
}))

beforeEach(() => {
  vi.clearAllMocks()
})

describe('RoomDiscoveryWidget', () => {
  it('renders the AI content, proposals, and citations from the actual response shape', async () => {
    const user = userEvent.setup()
    mockApiPost.mockResolvedValue({
      data: {
        data: {
          content: 'Có một lựa chọn phù hợp với yêu cầu của bạn.',
          proposals: [
            {
              action_type: 'suggest_booking',
              proposed_params: {
                room_id: 12,
                check_in: '2026-06-01',
                check_out: '2026-06-03',
                guest_count: 2,
                available: true,
              },
              human_readable_summary: 'Đề xuất đặt phòng #12 từ 2026-06-01 đến 2026-06-03.',
              policy_refs: ['booking-policy'],
              proposal_hash: 'proposal-12',
            },
          ],
          citations: [{ source_slug: 'booking-policy', verified_at: '2026-04-01' }],
        },
      },
    })

    render(<RoomDiscoveryWidget />)

    await user.click(screen.getByRole('button', { name: 'Tìm phòng' }))
    fireEvent.change(screen.getByLabelText('Nhận phòng'), { target: { value: '2026-06-01' } })
    fireEvent.change(screen.getByLabelText('Trả phòng'), { target: { value: '2026-06-03' } })
    fireEvent.change(screen.getByLabelText('Số khách'), { target: { value: '2' } })
    await user.click(screen.getByRole('button', { name: 'Tìm phòng' }))

    expect(
      await screen.findByText('Có một lựa chọn phù hợp với yêu cầu của bạn.')
    ).toBeInTheDocument()
    expect(
      screen.getByText('Đề xuất đặt phòng #12 từ 2026-06-01 đến 2026-06-03.')
    ).toBeInTheDocument()
    expect(screen.getByText('booking-policy · 2026-04-01')).toBeInTheDocument()
  })
})
