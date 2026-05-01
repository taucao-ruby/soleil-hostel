import { useState } from 'react'
import api from '@/shared/lib/api'

interface RoomCard {
  id: number
  name: string
  price: number
  max_guests: number
}

interface AiRoomDiscoveryProposal {
  action_type?: string
  proposed_params?: {
    room_id?: number
    check_in?: string
    check_out?: string
    guest_count?: number
    available?: boolean
  }
  human_readable_summary?: string
  policy_refs?: string[]
  proposal_hash?: string
}

interface AiRoomDiscoveryCitation {
  source_slug: string
  verified_at: string
}

interface AiRoomDiscoveryResponse {
  canary?: boolean
  support_contact?: string
  message?: string
  content: string
  proposals: AiRoomDiscoveryProposal[]
  citations: AiRoomDiscoveryCitation[]
}

export default function RoomDiscoveryWidget() {
  const [isOpen, setIsOpen] = useState(false)
  const [checkIn, setCheckIn] = useState('')
  const [checkOut, setCheckOut] = useState('')
  const [guests, setGuests] = useState(1)
  const [loading, setLoading] = useState(false)
  const [rooms, setRooms] = useState<RoomCard[]>([])
  const [proposals, setProposals] = useState<AiRoomDiscoveryProposal[]>([])
  const [citations, setCitations] = useState<AiRoomDiscoveryCitation[]>([])
  const [description, setDescription] = useState('')
  const [error, setError] = useState('')
  const [supportContact, setSupportContact] = useState('')

  const handleSearch = async () => {
    if (!checkIn || !checkOut) return

    setLoading(true)
    setError('')
    setRooms([])
    setProposals([])
    setCitations([])
    setDescription('')

    try {
      const res = await api.post<{ data: AiRoomDiscoveryResponse }>('/v1/ai/room_discovery', {
        message: `Tìm phòng từ ${checkIn} đến ${checkOut} cho ${guests} khách`,
      })

      const data = res.data?.data

      if (data?.canary === false) {
        setSupportContact(data.support_contact ?? '')
        setDescription(data.message ?? 'Vui lòng liên hệ bộ phận hỗ trợ.')
        return
      }

      setDescription(data?.content ?? '')
      setProposals(data?.proposals ?? [])
      setCitations(data?.citations ?? [])

      const proposalRooms = (data?.proposals ?? [])
        .map(proposal => proposal.proposed_params?.room_id)
        .filter((roomId): roomId is number => typeof roomId === 'number')
        .map(roomId => ({
          id: roomId,
          name: `Phòng #${roomId}`,
          price: 0,
          max_guests: guests,
        }))

      if (proposalRooms.length > 0) {
        setRooms(proposalRooms)
      }
      if (!data?.content && proposalRooms.length === 0 && !(data?.proposals ?? []).length) {
        setDescription('Không có phòng trống cho yêu cầu này.')
      }
    } catch {
      setError('Không thể tìm phòng. Vui lòng thử lại sau.')
      setSupportContact('support@soleilhostel.vn | Hotline: 0909-123-456')
    } finally {
      setLoading(false)
    }
  }

  if (!isOpen) {
    return (
      <button
        onClick={() => setIsOpen(true)}
        className="fixed z-50 p-4 text-white transition-colors rounded-full shadow-lg bottom-6 right-6 bg-emerald-500 hover:bg-emerald-600"
        aria-label="Tìm phòng"
      >
        🏨
      </button>
    )
  }

  return (
    <div className="fixed bottom-6 right-6 z-50 w-96 rounded-lg bg-white shadow-xl border border-gray-200 flex flex-col max-h-[600px]">
      <div className="flex items-center justify-between px-4 py-3 rounded-t-lg bg-emerald-500">
        <h3 className="text-sm font-semibold text-white">Tìm phòng trống</h3>
        <button onClick={() => setIsOpen(false)} className="text-lg text-white hover:text-gray-200">
          ×
        </button>
      </div>

      <div className="p-4 space-y-3">
        <div className="grid grid-cols-2 gap-2">
          <div>
            <label htmlFor="room-discovery-check-in" className="block mb-1 text-xs text-gray-600">
              Nhận phòng
            </label>
            <input
              id="room-discovery-check-in"
              type="date"
              value={checkIn}
              onChange={e => setCheckIn(e.target.value)}
              min={new Date().toISOString().split('T')[0]}
              className="w-full border rounded px-2 py-1.5 text-sm"
            />
          </div>
          <div>
            <label htmlFor="room-discovery-check-out" className="block mb-1 text-xs text-gray-600">
              Trả phòng
            </label>
            <input
              id="room-discovery-check-out"
              type="date"
              value={checkOut}
              onChange={e => setCheckOut(e.target.value)}
              min={checkIn || new Date().toISOString().split('T')[0]}
              className="w-full border rounded px-2 py-1.5 text-sm"
            />
          </div>
        </div>
        <div>
          <label htmlFor="room-discovery-guests" className="block mb-1 text-xs text-gray-600">
            Số khách
          </label>
          <input
            id="room-discovery-guests"
            type="number"
            value={guests}
            onChange={e => setGuests(Math.max(1, parseInt(e.target.value) || 1))}
            min={1}
            max={20}
            className="w-full border rounded px-2 py-1.5 text-sm"
          />
        </div>
        <button
          onClick={handleSearch}
          disabled={loading || !checkIn || !checkOut}
          className="w-full py-2 text-sm font-medium text-white transition-colors rounded bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50"
        >
          {loading ? 'Đang tìm...' : 'Tìm phòng'}
        </button>
      </div>

      <div className="flex-1 px-4 pb-4 space-y-3 overflow-y-auto">
        {error && (
          <div className="p-2 text-sm text-red-600 rounded bg-red-50">
            {error}
            {supportContact && <p className="mt-1 text-xs text-gray-600">{supportContact}</p>}
          </div>
        )}

        {rooms.map(room => (
          <div
            key={room.id}
            className="p-3 transition-colors border rounded-lg hover:border-emerald-300"
          >
            <div className="flex items-start justify-between">
              <div>
                <h4 className="text-sm font-medium">{room.name}</h4>
                <p className="text-xs text-gray-500">Tối đa {room.max_guests} khách</p>
              </div>
              <span className="text-sm font-semibold text-emerald-600">
                {new Intl.NumberFormat('vi-VN').format(room.price)}đ
              </span>
            </div>
            <a
              href={`/booking?room_id=${room.id}&check_in=${checkIn}&check_out=${checkOut}&guests=${guests}`}
              className="mt-2 block text-center bg-emerald-50 text-emerald-700 rounded py-1.5 text-xs font-medium hover:bg-emerald-100 transition-colors"
            >
              Đặt phòng này
            </a>
          </div>
        ))}

        {description && !error && (
          <p className="p-2 text-sm text-gray-700 rounded bg-gray-50">{description}</p>
        )}

        {proposals.map(proposal => (
          <div
            key={proposal.proposal_hash ?? proposal.human_readable_summary}
            className="p-3 text-sm border rounded-lg bg-emerald-50 border-emerald-100 text-emerald-900"
          >
            <p className="font-medium">
              {proposal.human_readable_summary ?? 'Đề xuất đặt phòng cần xác nhận.'}
            </p>
            {proposal.proposed_params?.check_in && proposal.proposed_params?.check_out && (
              <p className="mt-1 text-xs text-emerald-700">
                {proposal.proposed_params.check_in} - {proposal.proposed_params.check_out}
              </p>
            )}
          </div>
        ))}

        {citations.length > 0 && !error && (
          <div className="space-y-1 text-xs text-gray-500">
            {citations.map(citation => (
              <p key={`${citation.source_slug}-${citation.verified_at}`}>
                {citation.source_slug} · {citation.verified_at}
              </p>
            ))}
          </div>
        )}

        {supportContact && !error && <p className="text-xs text-gray-500">{supportContact}</p>}
      </div>
    </div>
  )
}
