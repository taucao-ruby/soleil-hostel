import { Link } from 'react-router-dom'

export default function NotFoundPage() {
  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh]">
      <h1 className="text-4xl font-bold text-gray-900 mb-4">404</h1>
      <p className="text-gray-600 mb-8">Trang bạn tìm kiếm không tồn tại.</p>
      <Link to="/" className="text-blue-600 hover:underline">
        Về trang chủ
      </Link>
    </div>
  )
}
