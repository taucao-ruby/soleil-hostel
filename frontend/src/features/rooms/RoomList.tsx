import React, { useEffect, useState } from 'react'
import { getRooms } from './room.api'
import { Room } from './room.types'

/**
 * RoomList Component
 *
 * Displays all available rooms in a responsive grid.
 *
 * Features:
 * - Fetches rooms from /rooms endpoint
 * - Skeleton loading state
 * - Error handling
 * - Responsive card grid
 * - Room status badges
 * - Price display
 */

const RoomList: React.FC = () => {
  const [rooms, setRooms] = useState<Room[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const fetchRooms = async () => {
      try {
        setLoading(true)
        setError(null)
        const data = await getRooms()
        setRooms(data)
      } catch (err) {
        console.error('Failed to fetch rooms:', err)
        setError('Failed to load rooms. Please try again later.')
      } finally {
        setLoading(false)
      }
    }

    fetchRooms()
  }, [])

  /**
   * Get status badge color
   */
  const getStatusColor = (status: Room['status']) => {
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

  /**
   * Skeleton loader component
   */
  const SkeletonCard = () => (
    <div className="overflow-hidden bg-white shadow-md rounded-xl animate-pulse">
      <div className="h-48 bg-gray-200"></div>
      <div className="p-6">
        <div className="h-6 mb-4 bg-gray-200 rounded"></div>
        <div className="h-4 mb-2 bg-gray-200 rounded"></div>
        <div className="w-3/4 h-4 mb-4 bg-gray-200 rounded"></div>
        <div className="flex items-center justify-between">
          <div className="w-20 h-8 bg-gray-200 rounded"></div>
          <div className="w-24 h-6 bg-gray-200 rounded"></div>
        </div>
      </div>
    </div>
  )

  return (
    <section className="min-h-screen px-4 py-12 bg-gray-50">
      <div className="mx-auto max-w-7xl">
        {/* Header */}
        <div className="mb-12 text-center">
          <h1 className="mb-4 text-4xl font-bold text-gray-900">Our Rooms</h1>
          <p className="text-lg text-gray-600">
            Choose from our selection of comfortable and affordable rooms
          </p>
        </div>

        {/* Error State */}
        {error && (
          <div className="max-w-2xl p-6 mx-auto mb-8 border border-red-200 bg-red-50 rounded-xl">
            <div className="flex items-center">
              <svg
                className="w-6 h-6 mr-3 text-red-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <p className="font-medium text-red-800">{error}</p>
            </div>
          </div>
        )}

        {/* Loading State - Skeleton Cards */}
        {loading && (
          <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
            {[1, 2, 3, 4, 5, 6].map(i => (
              <SkeletonCard key={i} />
            ))}
          </div>
        )}

        {/* Rooms Grid */}
        {!loading && !error && rooms.length > 0 && (
          <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
            {rooms.map(room => (
              <article
                key={room.id}
                className="overflow-hidden transition-shadow duration-300 bg-white shadow-md rounded-xl hover:shadow-xl"
              >
                {/* Room Image */}
                <div className="relative h-48 overflow-hidden bg-gradient-to-br from-blue-400 to-blue-600">
                  {room.image_url ? (
                    <img
                      src={room.image_url}
                      alt={room.name}
                      className="object-cover w-full h-full"
                    />
                  ) : (
                    <div className="flex items-center justify-center w-full h-full">
                      <svg
                        className="w-16 h-16 text-white opacity-50"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={1.5}
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                        />
                      </svg>
                    </div>
                  )}
                </div>

                {/* Room Details */}
                <div className="p-6">
                  {/* Room Name */}
                  <h3 className="mb-2 text-xl font-bold text-gray-900">{room.name}</h3>

                  {/* Room Description */}
                  <p className="mb-4 text-sm text-gray-600 line-clamp-2">
                    {room.description || 'Comfortable room with all amenities'}
                  </p>

                  {/* Price and Status */}
                  <div className="flex items-center justify-between">
                    {/* Price */}
                    <div className="text-2xl font-bold text-blue-600">
                      ${room.price}
                      <span className="text-sm font-normal text-gray-500">/night</span>
                    </div>

                    {/* Status Badge */}
                    <span
                      className={`px-3 py-1 rounded-full text-xs font-semibold uppercase ${getStatusColor(
                        room.status
                      )}`}
                    >
                      {room.status}
                    </span>
                  </div>

                  {/* Book Button (if available) */}
                  {room.status === 'available' && (
                    <button className="w-full px-4 py-2 mt-4 font-semibold text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700">
                      Book Now
                    </button>
                  )}
                </div>
              </article>
            ))}
          </div>
        )}

        {/* Empty State */}
        {!loading && !error && rooms.length === 0 && (
          <div className="py-16 text-center">
            <svg
              className="w-24 h-24 mx-auto mb-4 text-gray-300"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.5}
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
              />
            </svg>
            <h3 className="mb-2 text-xl font-semibold text-gray-900">No rooms available</h3>
            <p className="text-gray-600">Check back later for available rooms</p>
          </div>
        )}
      </div>
    </section>
  )
}

export default RoomList
