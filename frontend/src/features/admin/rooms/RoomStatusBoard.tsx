import React from 'react'
import { Link } from 'react-router-dom'
import type { AdminRoom } from './adminRoom.types'
import RoomStatusBadge from './RoomStatusBadge'

interface RoomStatusBoardProps {
  rooms: AdminRoom[]
}

const RoomStatusBoard: React.FC<RoomStatusBoardProps> = ({ rooms }) => {
  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
      {rooms.map(room => {
        // Compute border and bg color based on status
        let cardStyles = 'border-gray-200 hover:border-blue-400'

        switch (room.status) {
          case 'available':
            cardStyles = 'border-green-200 bg-green-50 shadow-sm'
            break
          case 'occupied':
            cardStyles = 'border-red-200 bg-red-50 shadow-sm'
            break
          case 'maintenance':
            cardStyles = 'border-yellow-200 bg-yellow-50 shadow-sm'
            break
        }

        return (
          <Link
            key={room.id}
            to={`/admin/rooms/${room.id}/edit`}
            className={`flex flex-col justify-between p-4 bg-white rounded-xl border-2 transition-all duration-200 group ${cardStyles}`}
          >
            <div className="flex justify-between items-start mb-4">
              <span className="text-lg font-bold text-gray-900 group-hover:text-blue-600">
                {room.room_number || room.name}
              </span>
              <RoomStatusBadge status={room.status} />
            </div>

            <div className="mt-auto space-y-2">
              <div className="text-sm text-gray-500 line-clamp-1">{room.name}</div>
              <div className="flex justify-between text-xs font-medium text-gray-400 border-t border-gray-100 pt-2">
                <span>{room.max_guests} người</span>
                <span className="text-gray-900">
                  {new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(room.price)}đ
                </span>
              </div>
            </div>
          </Link>
        )
      })}
    </div>
  )
}

export default RoomStatusBoard
