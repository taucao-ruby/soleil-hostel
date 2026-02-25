import { Link } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'
import Skeleton from '@/shared/components/ui/Skeleton'
import GuestDashboard from '@/features/bookings/GuestDashboard'
import AdminDashboard from '@/features/admin/AdminDashboard'

const DashboardPage: React.FC = () => {
  const { user, loading } = useAuth()

  const isAdmin = user?.role === 'admin'

  if (loading) {
    return (
      <div className="min-h-screen p-8 bg-gray-50">
        <div className="max-w-4xl mx-auto space-y-6">
          <Skeleton width="12rem" height="2rem" rounded="md" />
          <Skeleton width="20rem" height="1rem" rounded="md" />
          {[0, 1, 2].map(i => (
            <Skeleton key={i} width="100%" height="5.5rem" rounded="lg" />
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen p-8 bg-gray-50">
      <div className={isAdmin ? 'max-w-6xl mx-auto' : 'max-w-4xl mx-auto'}>
        <h1 className="mb-2 text-3xl font-bold text-gray-900">
          {isAdmin ? 'Bảng điều khiển quản trị' : 'Trang quản lý'}
        </h1>
        <p className="mb-8 text-gray-600">
          Chào mừng{user?.name ? ` ${user.name}` : ''} quay trở lại!{' '}
          {isAdmin ? 'Quản lý hệ thống tại đây.' : 'Quản lý đặt phòng của bạn tại đây.'}
        </p>

        <div className="space-y-8">
          {isAdmin ? <AdminDashboard /> : <GuestDashboard />}

          {/* Quick Actions */}
          <section>
            <h2 className="mb-4 text-xl font-semibold text-gray-800">Truy cập nhanh</h2>
            <div className="flex flex-wrap gap-4">
              <Link
                to="/rooms"
                className="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
              >
                Xem phòng
              </Link>
              <Link
                to="/locations"
                className="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
              >
                Xem chi nhánh
              </Link>
            </div>
          </section>
        </div>
      </div>
    </div>
  )
}

export default DashboardPage
