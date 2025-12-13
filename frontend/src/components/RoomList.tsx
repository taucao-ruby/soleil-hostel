// RoomList component: Hiển thị danh sách phòng từ API, xử lý loading/error, responsive grid với Tailwind
import React, { useEffect, useState } from 'react'
import api from '../services/api' // Axios instance với baseURL đã cấu hình

// Định nghĩa type cho response của API
interface Room {
  id: number
  name: string
  price: number
  max_guests: number
  status: string
}

interface RoomApiResponse {
  data: Room[]
  message?: string
  [key: string]: unknown
}

// Skeleton loading cho UX tốt hơn
const RoomSkeleton: React.FC = () => (
  <div
    className="flex flex-col justify-between p-6 bg-gray-100 shadow-lg rounded-xl animate-pulse"
    role="status"
    aria-label="Loading room information"
  >
    <div>
      <div className="w-2/3 h-6 mb-2 bg-gray-300 rounded" aria-hidden="true" />
      <div className="w-1/2 h-4 mb-1 bg-gray-300 rounded" aria-hidden="true" />
      <div className="w-1/3 h-4 mb-1 bg-gray-300 rounded" aria-hidden="true" />
      <div className="w-1/4 h-6 mt-2 bg-gray-300 rounded" aria-hidden="true" />
    </div>
    <span className="sr-only">Loading room details...</span>
  </div>
)

// Tách card phòng thành component riêng
interface RoomCardProps {
  room: Room
}

const RoomCard: React.FC<RoomCardProps> = ({ room }) => (
  <article
    className="flex flex-col justify-between p-6 transition-transform duration-200 bg-white shadow-lg rounded-xl hover:scale-105"
    role="article"
    aria-labelledby={`room-${room.id}-name`}
  >
    <div>
      {/* Tên phòng */}
      <h3 id={`room-${room.id}-name`} className="mb-2 text-xl font-semibold text-blue-600">
        {room.name}
      </h3>
      {/* Giá phòng */}
      <p className="mb-1 text-gray-700">
        <span className="sr-only">Price:</span>
        <span aria-label={`Price: ${room.price} dollars per night`}>
          Price: <span className="font-bold text-green-600">${room.price}</span>
        </span>
      </p>
      {/* Số khách tối đa */}
      <p className="mb-1 text-gray-700">
        <span className="sr-only">Maximum guests:</span>
        <span aria-label={`Maximum ${room.max_guests} guests`}>
          Max Guests: <span className="font-bold">{room.max_guests}</span>
        </span>
      </p>
      {/* Trạng thái phòng với màu sắc động */}
      <span
        className={
          `inline-block px-3 py-1 rounded-full text-sm font-medium mt-2 ` +
          (room.status === 'available'
            ? 'bg-green-100 text-green-700'
            : room.status === 'booked'
              ? 'bg-yellow-100 text-yellow-700'
              : 'bg-red-100 text-red-700')
        }
        role="status"
        aria-label={`Room status: ${room.status}`}
      >
        {room.status.charAt(0).toUpperCase() + room.status.slice(1)}
      </span>
    </div>
  </article>
)

// Định nghĩa kiểu dữ liệu cho một phòng
interface Room {
  id: number
  name: string
  price: number
  max_guests: number
  status: string
}

const RoomList: React.FC = () => {
  // State lưu danh sách phòng
  const [rooms, setRooms] = useState<Room[]>([])
  // State loading khi đang fetch dữ liệu
  const [loading, setLoading] = useState(true)
  // State lưu thông báo lỗi nếu có
  const [error, setError] = useState<string | null>(null)

  // Chuẩn bị cho phân trang/lazy loading (có thể mở rộng sau)
  // const [page, setPage] = useState(1);
  // const [hasMore, setHasMore] = useState(true);

  // Fetch danh sách phòng khi component mount
  useEffect(() => {
    api
      .get<RoomApiResponse>('/rooms')
      .then(res => {
        setRooms(res.data.data || [])
        setLoading(false)
      })
      .catch(err => {
        // Nếu API trả về lỗi chi tiết, hiển thị lỗi cụ thể
        const msg = err?.response?.data?.message || err.message || 'Failed to fetch rooms'
        setError(msg)
        setLoading(false)
      })
  }, [])

  // Hiển thị trạng thái loading với skeleton
  if (loading) {
    return (
      <section
        className="max-w-6xl px-4 py-8 mx-auto"
        aria-labelledby="rooms-heading"
        aria-busy="true"
      >
        <h2 id="rooms-heading" className="mb-6 text-2xl font-bold text-blue-700">
          Available Rooms
        </h2>
        <div
          className="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3"
          role="list"
          aria-label="Loading room list"
        >
          {Array.from({ length: 6 }).map((_, idx) => (
            <RoomSkeleton key={idx} />
          ))}
        </div>
      </section>
    )
  }

  // Hiển thị trạng thái lỗi
  if (error) {
    return (
      <div className="py-10 text-lg text-center text-red-600" role="alert" aria-live="assertive">
        {error}
      </div>
    )
  }

  // Hiển thị danh sách phòng dạng grid responsive
  return (
    <section className="max-w-6xl px-4 py-8 mx-auto" aria-labelledby="rooms-heading">
      {/* Tiêu đề danh sách phòng */}
      <h2 id="rooms-heading" className="mb-6 text-2xl font-bold text-blue-700">
        Available Rooms
        <span className="sr-only">({rooms.length} rooms available)</span>
      </h2>
      <div
        className="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3"
        role="list"
        aria-label={`${rooms.length} available rooms`}
      >
        {/* Lặp qua từng phòng và hiển thị thông tin */}
        {rooms.map(room => (
          <RoomCard key={room.id} room={room} />
        ))}
      </div>
      {/* Phân trang/lazy loading: có thể bổ sung nút hoặc infinite scroll ở đây */}
    </section>
  )
}

export default RoomList
