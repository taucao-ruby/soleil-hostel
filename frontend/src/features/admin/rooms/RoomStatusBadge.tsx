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
        return 'bg-green-100 text-green-800'
      case 'occupied':
        return 'bg-red-100 text-red-800'
      case 'maintenance':
        return 'bg-yellow-100 text-yellow-800'
      default:
        return 'bg-gray-100 text-gray-800'
    }
  }

  const getLabel = () => {
    switch (status) {
      case 'available':
        return 'Trống'
      case 'occupied':
        return 'Có khách'
      case 'maintenance':
        return 'Đang bảo trì'
      default:
        return status
    }
  }

  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getBadgeStyle()} ${className}`}
    >
      {getLabel()}
    </span>
  )
}

export default RoomStatusBadge
