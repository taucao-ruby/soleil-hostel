import React from 'react'
import { Link } from 'react-router-dom'
import type { AdminRoom } from './adminRoom.types'
import RoomReadinessBadge from './RoomReadinessBadge'
import RoomStatusBadge from './RoomStatusBadge'

interface RoomTableProps {
  rooms: AdminRoom[]
  showActions?: boolean
  actionsEnabled?: boolean
  deletingRoomId?: number | null
  onDelete?: (room: AdminRoom) => void | Promise<void>
}

const formatCurrency = (amount: number) =>
  new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount)

const RoomTable: React.FC<RoomTableProps> = ({
  rooms,
  showActions = false,
  actionsEnabled = false,
  deletingRoomId = null,
  onDelete,
}) => {
  return (
    <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm shadow-stone-200/50">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-stone-200">
          <thead className="bg-stone-50/90">
            <tr>
              <th
                scope="col"
                className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500"
              >
                Tên phòng
              </th>
              <th
                scope="col"
                className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500"
              >
                Chi nhánh
              </th>
              <th
                scope="col"
                className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500"
              >
                Giá/đêm
              </th>
              <th
                scope="col"
                className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500"
              >
                Sức chứa
              </th>
              <th
                scope="col"
                className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500"
              >
                Trạng thái
              </th>
              <th
                scope="col"
                className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500"
              >
                Readiness
              </th>
              <th
                scope="col"
                className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] text-stone-500"
              >
                Hành động
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-stone-100 bg-white">
            {rooms.map(room => (
              <tr key={room.id} className="align-top hover:bg-stone-50/70">
                <td className="px-4 py-3 text-sm font-medium text-stone-900">
                  {room.room_number || room.display_name || room.name}
                </td>
                <td className="px-4 py-3 text-sm text-stone-600">
                  {room.location?.name || `Chi nhánh #${room.location_id}`}
                </td>
                <td className="px-4 py-3 text-sm text-stone-700">{formatCurrency(room.price)}</td>
                <td className="px-4 py-3 text-sm text-stone-600">{room.max_guests} khách</td>
                <td className="px-4 py-3">
                  <RoomStatusBadge status={room.status} />
                </td>
                <td className="px-4 py-3">
                  <RoomReadinessBadge status={room.readiness_status} />
                </td>
                <td className="px-4 py-3">
                  {showActions ? (
                    <div className="flex justify-end gap-2">
                      {actionsEnabled ? (
                        <Link
                          to={`/admin/rooms/${room.id}/edit`}
                          className="inline-flex items-center gap-1 rounded-md border border-stone-200 px-2.5 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-stone-50 hover:text-stone-950"
                        >
                          <svg
                            className="h-3.5 w-3.5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={2}
                              d="m16.862 4.487 2.651 2.651M7.5 20.25H4.5v-3l9.928-9.928a1.5 1.5 0 0 1 2.121 0l.701.7a1.5 1.5 0 0 1 0 2.122L7.5 20.25Z"
                            />
                          </svg>
                          Sửa
                        </Link>
                      ) : (
                        <span
                          aria-disabled="true"
                          className="inline-flex items-center gap-1 rounded-md border border-stone-200 px-2.5 py-1.5 text-xs font-medium text-stone-400"
                        >
                          <svg
                            className="h-3.5 w-3.5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={2}
                              d="m16.862 4.487 2.651 2.651M7.5 20.25H4.5v-3l9.928-9.928a1.5 1.5 0 0 1 2.121 0l.701.7a1.5 1.5 0 0 1 0 2.122L7.5 20.25Z"
                            />
                          </svg>
                          Sửa
                        </span>
                      )}

                      <button
                        type="button"
                        disabled={!actionsEnabled || deletingRoomId === room.id}
                        onClick={() => onDelete?.(room)}
                        className="inline-flex items-center gap-1 rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                      >
                        <svg
                          className="h-3.5 w-3.5"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                          aria-hidden="true"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M6 7.5h12m-9 0v-.75A2.25 2.25 0 0 1 11.25 4.5h1.5A2.25 2.25 0 0 1 15 6.75v.75m-7.5 0v10.125A2.625 2.625 0 0 0 10.125 20.25h3.75A2.625 2.625 0 0 0 16.5 17.625V7.5M10.5 10.5v6m3-6v6"
                          />
                        </svg>
                        {deletingRoomId === room.id ? 'Đang xóa' : 'Xóa'}
                      </button>
                    </div>
                  ) : (
                    <div className="text-right text-sm text-stone-300">—</div>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export default RoomTable
