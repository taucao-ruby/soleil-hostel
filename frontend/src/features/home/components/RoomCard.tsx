import React from 'react'
import { useNavigate } from 'react-router-dom'
import type { HomeRoom } from '../home.types'

interface RoomCardProps {
  room: HomeRoom
}

const VND = new Intl.NumberFormat('vi-VN')

/**
 * RoomCard — compact 2-col card per PROMPT_1A spec.
 *
 * - Image: 180px fixed height, amber gradient placeholder if imageUrl null
 * - Location badge: bottom-left overlay on image
 * - Availability badge: top-right on image (green "Còn phòng" / gray "Hết phòng")
 * - Body 12px padding: name bold 15px | price accent | "Đặt ngay" if available
 */
const RoomCard: React.FC<RoomCardProps> = ({ room }) => {
  const navigate = useNavigate()

  return (
    <article className="border border-[#E2DDD6] rounded-xl overflow-hidden bg-white">
      {/* Image — 180px fixed height */}
      <div className="relative" style={{ height: '180px' }}>
        {room.imageUrl ? (
          <img
            src={room.imageUrl}
            alt={room.name}
            className="absolute inset-0 w-full h-full object-cover"
            loading="lazy"
          />
        ) : (
          <div className="absolute inset-0 bg-gradient-to-br from-amber-200 to-amber-400" />
        )}

        {/* Location badge — bottom-left */}
        <span className="absolute bottom-2 left-2 inline-flex items-center gap-1 bg-black/50 backdrop-blur-sm text-white text-[11px] px-2 py-0.5 rounded-full">
          📍 {room.location}
        </span>

        {/* Availability badge — top-right */}
        {room.available ? (
          <span className="absolute top-2 right-2 bg-green-100 text-green-800 text-[11px] font-medium px-2 py-0.5 rounded-full border border-green-200">
            Còn phòng
          </span>
        ) : (
          <span className="absolute top-2 right-2 bg-gray-100 text-gray-600 text-[11px] font-medium px-2 py-0.5 rounded-full border border-gray-200">
            Hết phòng
          </span>
        )}
      </div>

      {/* Card body */}
      <div className="p-3">
        <h3 className="font-medium text-[15px] text-[#1C1A17] leading-snug mb-1.5 line-clamp-2">
          {room.name}
        </h3>
        <p className="text-[13px] mb-2.5">
          <span className="text-[#C9973A] font-medium">Từ {VND.format(room.price)}₫</span>
          <span className="text-[#6B6760]"> / đêm</span>
        </p>
        {room.available ? (
          <button
            aria-label="Đặt ngay"
            onClick={() => navigate('/booking')}
            className="w-full h-9 bg-[#C9973A] hover:bg-[#B8872A] text-white text-sm font-medium rounded-lg transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C9973A] focus-visible:ring-offset-1"
          >
            Đặt ngay
          </button>
        ) : (
          <p className="text-xs text-[#6B6760] text-center py-2">Không còn phòng trống</p>
        )}
      </div>
    </article>
  )
}

export default RoomCard
