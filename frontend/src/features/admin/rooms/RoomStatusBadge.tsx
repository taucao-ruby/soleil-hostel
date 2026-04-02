import React from 'react'
import type { RoomStatus } from './adminRoom.types'

interface RoomStatusBadgeProps {
  status: RoomStatus
  className?: string
}

const RoomStatusBadge: React.FC<RoomStatusBadgeProps> = ({ status, className = '' }) => {
  const getBadgeStyle = () => {
    switch (status) {
      case 'available':
        return 'bg-green-50 text-green-700 border-green-200'
      case 'booked':
      case 'occupied':
        return 'bg-blue-50 text-blue-700 border-blue-200'
      case 'maintenance':
        return 'bg-stone-100 text-stone-700 border-stone-200'
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200'
    }
  }

  const getLabel = () => {
    switch (status) {
      case 'available':
        return 'Còn phòng'
      case 'booked':
      case 'occupied':
        return 'Đã đặt'
      case 'maintenance':
        return 'Bảo trì'
      default:
        return status
    }
  }

  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium ${getBadgeStyle()} ${className}`}
    >
      {getLabel()}
    </span>
  )
}

export default RoomStatusBadge
