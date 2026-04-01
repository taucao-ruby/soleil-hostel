import React from 'react'
import type { RoomReadinessStatus } from './adminRoom.types'

interface RoomReadinessBadgeProps {
  status: RoomReadinessStatus
  className?: string
}

const readinessStyles: Record<RoomReadinessStatus, string> = {
  ready: 'bg-green-50 text-green-700 border-green-200',
  occupied: 'bg-blue-50 text-blue-700',
  dirty: 'bg-orange-50 text-orange-700',
  cleaning: 'bg-yellow-50 text-yellow-800',
  inspected: 'bg-teal-50 text-teal-700',
  out_of_service: 'bg-red-50 text-red-700',
}

const readinessLabels: Record<RoomReadinessStatus, string> = {
  ready: 'Sẵn sàng',
  occupied: 'Đang có khách',
  dirty: 'Bẩn',
  cleaning: 'Đang dọn',
  inspected: 'Đã kiểm tra',
  out_of_service: 'Ngưng khai thác',
}

const RoomReadinessBadge: React.FC<RoomReadinessBadgeProps> = ({ status, className = '' }) => {
  return (
    <span
      className={`inline-flex items-center rounded-full border border-transparent px-2.5 py-1 text-xs font-medium ${readinessStyles[status]} ${className}`}
    >
      {readinessLabels[status]}
    </span>
  )
}

export default RoomReadinessBadge
