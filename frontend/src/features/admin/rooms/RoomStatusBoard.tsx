import React from 'react'
import type { AdminRoom } from './adminRoom.types'
import RoomReadinessBadge from './RoomReadinessBadge'

interface RoomStatusBoardProps {
  rooms: AdminRoom[]
}

const RoomStatusBoard: React.FC<RoomStatusBoardProps> = ({ rooms }) => {
  return (
    <div className="mb-8 grid grid-cols-2 gap-3 md:grid-cols-4">
      {rooms.map(room => (
        <div
          key={room.id}
          className="rounded-lg border border-stone-200 bg-white p-3 shadow-sm shadow-stone-200/40"
        >
          <div className="flex flex-col gap-2">
            <p className="text-[13px] font-medium text-stone-900">
              {room.room_number || room.display_name || room.name}
            </p>
            <RoomReadinessBadge status={room.readiness_status} />
          </div>
        </div>
      ))}
    </div>
  )
}

export default RoomStatusBoard
