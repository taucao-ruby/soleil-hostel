import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import type { HomeRoom } from '../home.types'

interface RoomCardProps {
  room: HomeRoom
}

const VND = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' })

const AMENITY_ICONS: Record<string, string> = {
  AC: '🌬️',
  Bath: '🛁',
  Wifi: '📶',
}

/**
 * RoomCard — displays a single room per spec § 04.
 * Wishlist button: aria-label="Lưu phòng" (FIX for regression test).
 * Badge: bg-[#FAE5D8] text-[#D4622A] (spec-correct, not bg-orangeCTA).
 */
const RoomCard: React.FC<RoomCardProps> = ({ room }) => {
  const navigate = useNavigate()
  const [wishlisted, setWishlisted] = useState(false)

  return (
    <article className="bg-[#FDFAF3] rounded-2xl border border-[#E2D5C3] overflow-hidden shadow-[0_1px_3px_rgba(92,61,30,0.12)] hover:shadow-md transition-shadow duration-200">
      {/* Photo — aspect 4/3 */}
      <div className="relative aspect-[4/3]">
        <img
          src={room.imageUrl}
          alt={room.name}
          className="absolute inset-0 w-full h-full object-cover"
          loading="lazy"
        />

        {/* Badge — top-left */}
        <span className="absolute top-3 left-3 bg-[#FAE5D8] text-[#D4622A] text-xs font-medium rounded-full px-3 py-1 m-0">
          {room.badge}
        </span>

        {/* Wishlist — top-right (FIX: aria-label="Lưu phòng") */}
        <button
          aria-pressed={wishlisted}
          aria-label="Lưu phòng"
          onClick={() => setWishlisted(w => !w)}
          className="absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full bg-white/80 backdrop-blur-sm shadow transition-colors duration-150 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D4622A]"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            className="w-4 h-4"
            fill={wishlisted ? '#D4622A' : 'none'}
            stroke={wishlisted ? '#D4622A' : '#5C3D1E'}
            strokeWidth={2}
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
            />
          </svg>
        </button>
      </div>

      {/* Card body */}
      <div className="p-4">
        {/* Room name */}
        <h3 className="font-sans font-semibold text-[#5C3D1E] text-base leading-snug mb-2">
          {room.name}
        </h3>

        {/* Amenity icons row */}
        <div className="flex gap-3 text-xs text-[#5C3D1E]/60 mb-2">
          {room.amenities.map(a => (
            <span key={a}>
              {AMENITY_ICONS[a] ?? '•'}{' '}
              {a === 'AC' ? 'Điều hòa' : a === 'Bath' ? 'Phòng tắm' : 'Wifi'}
            </span>
          ))}
          <span>👥 {room.maxGuests} khách</span>
        </div>

        {/* Rating row */}
        <p className="text-xs text-[#5C3D1E]/60 mb-3">
          ⭐ {room.rating} · {room.reviewCount} đánh giá
        </p>

        {/* Price + CTA */}
        <div className="flex items-center justify-between mt-3">
          <div>
            <span className="font-sans font-bold text-[#D4622A] text-xl">
              {VND.format(room.price)}
            </span>
            <span className="text-xs text-[#5C3D1E]/60">/đêm</span>
          </div>
          <button
            aria-label="Đặt ngay"
            onClick={() => navigate('/booking')}
            className="bg-[#D4622A] hover:bg-[#E8845A] text-white font-sans font-semibold text-sm h-11 px-5 rounded-xl transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D4622A] focus-visible:ring-offset-1"
          >
            Đặt ngay
          </button>
        </div>
      </div>
    </article>
  )
}

export default RoomCard
