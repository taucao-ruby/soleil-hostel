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
      <div className="min-h-screen bg-[#fcfaf5] px-4 py-10 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto space-y-6">
          <Skeleton width="12rem" height="2rem" rounded="md" />
          <Skeleton width="20rem" height="1rem" rounded="md" />
          {[0, 1, 2].map(i => (
            <Skeleton key={i} width="100%" height="7rem" rounded="lg" />
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(251,191,36,0.12),_transparent_32%),linear-gradient(to_bottom,_#fffdf8,_#fcfaf5)] px-4 py-10 sm:px-6 lg:px-8">
      <div className={isAdmin ? 'mx-auto max-w-6xl' : 'mx-auto max-w-5xl'}>
        <div className="space-y-8">
          {!isAdmin && (
            <section className="space-y-2">
              <h1 className="text-4xl font-bold tracking-tight text-gray-950">Trang quản lý</h1>
              <p className="text-base text-gray-600">Quản lý đặt phòng của bạn tại đây.</p>
              <p className="text-lg font-medium text-gray-900">Xin chào, {user?.name ?? 'bạn'}!</p>
            </section>
          )}

          {isAdmin && (
            <section className="space-y-2">
              <h1 className="text-3xl font-bold text-gray-900">Bảng điều khiển quản trị</h1>
              <p className="text-gray-600">Quản lý hệ thống tại đây.</p>
            </section>
          )}

          {isAdmin ? <AdminDashboard /> : <GuestDashboard />}

          <section>
            <h2 className="mb-4 text-2xl font-semibold text-gray-900">Khám phá thêm</h2>
            <div className="grid gap-4 md:grid-cols-2">
              <Link
                to="/rooms"
                className="group flex items-center justify-between rounded-2xl border border-gray-200 bg-white px-5 py-5 text-base font-semibold text-gray-900 shadow-sm transition-all hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2"
              >
                <span>🛏️ Xem phòng →</span>
                <span
                  aria-hidden="true"
                  className="text-amber-500 transition-transform group-hover:translate-x-1"
                >
                  →
                </span>
              </Link>
              <Link
                to="/locations"
                className="group flex items-center justify-between rounded-2xl border border-gray-200 bg-white px-5 py-5 text-base font-semibold text-gray-900 shadow-sm transition-all hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2"
              >
                <span>📍 Xem chi nhánh →</span>
                <span
                  aria-hidden="true"
                  className="text-amber-500 transition-transform group-hover:translate-x-1"
                >
                  →
                </span>
              </Link>
            </div>
          </section>
        </div>
      </div>
    </div>
  )
}

export default DashboardPage
