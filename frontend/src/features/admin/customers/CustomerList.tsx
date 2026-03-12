import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getCustomers, getCustomerStats } from './customer.api'
import type { CustomerSummary, CustomerStats } from './customer.api'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'

const CustomerList: React.FC = () => {
  const navigate = useNavigate()
  const [customers, setCustomers] = useState<CustomerSummary[]>([])
  const [stats, setStats] = useState<CustomerStats | null>(null)

  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)

  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    getCustomerStats()
      .then(setStats)
      .catch(() => {
        /* ignored */
      })
  }, [])

  useEffect(() => {
    const fetchList = async () => {
      setIsLoading(true)
      try {
        const res = await getCustomers(search, page)
        setCustomers(res.data)
        setTotalPages(res.last_page)
      } catch {
        // fetch error handled silently
      } finally {
        setIsLoading(false)
      }
    }

    const timeout = setTimeout(fetchList, 500)
    return () => clearTimeout(timeout)
  }, [search, page])

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Khách hàng lưu trú</h1>
      </div>

      {stats && (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="bg-white p-4 shadow-sm rounded-lg border border-gray-200">
            <p className="text-sm font-medium text-gray-500 truncate">Tổng khách hàng</p>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{stats.total_customers}</p>
          </div>
          <div className="bg-white p-4 shadow-sm rounded-lg border border-gray-200">
            <p className="text-sm font-medium text-gray-500 truncate">Khách quay lại</p>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{stats.returning_customers}</p>
          </div>
          <div className="bg-white p-4 shadow-sm rounded-lg border border-gray-200">
            <p className="text-sm font-medium text-gray-500 truncate">Tỷ lệ quay lại</p>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{stats.return_rate}%</p>
          </div>
          <div className="bg-white p-4 shadow-sm rounded-lg border border-gray-200">
            <p className="text-sm font-medium text-gray-500 truncate">Tổng chi tiêu</p>
            <p className="mt-1 text-2xl font-semibold text-green-600">
              {new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(
                Number(stats.total_revenue)
              )}
            </p>
          </div>
        </div>
      )}

      {/* Filter Bar */}
      <div className="bg-white p-4 shadow-sm rounded-lg border border-gray-200 flex items-center">
        <div className="w-full max-w-md">
          <label htmlFor="search" className="sr-only">
            Tìm kiếm
          </label>
          <input
            type="text"
            id="search"
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            placeholder="Tìm theo tên hoặc email khách hàng..."
          />
        </div>
      </div>

      {isLoading ? (
        <LoadingSpinner message="Đang tải danh sách..." />
      ) : (
        <div className="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
          <ul className="divide-y divide-gray-200">
            {customers.map(c => (
              <li key={c.email}>
                <button
                  onClick={() => navigate(`/admin/customers/${encodeURIComponent(c.email)}`)}
                  className="block hover:bg-gray-50 focus:outline-none focus:bg-gray-50 transition duration-150 ease-in-out w-full text-left"
                >
                  <div className="flex items-center px-4 py-4 sm:px-6">
                    <div className="min-w-0 flex-1 flex items-center">
                      <div className="flex-shrink-0">
                        <div className="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xl">
                          {c.name.charAt(0).toUpperCase()}
                        </div>
                      </div>
                      <div className="min-w-0 flex-1 px-4 md:grid md:grid-cols-2 md:gap-4">
                        <div>
                          <p className="text-sm font-medium text-blue-600 truncate">{c.name}</p>
                          <p className="mt-2 flex items-center text-sm text-gray-500">
                            <span className="truncate">{c.email}</span>
                          </p>
                        </div>
                        <div className="hidden md:block">
                          <div>
                            <p className="text-sm text-gray-900">
                              Tổng lưu trú:{' '}
                              <span className="font-semibold">{c.total_stays} lần</span>
                            </p>
                            <p className="mt-2 flex items-center text-sm text-gray-500">
                              Lần cuối: {c.last_visit.split('T')[0]}
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div>
                      <svg
                        className="h-5 w-5 text-gray-400"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path
                          fillRule="evenodd"
                          d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                          clipRule="evenodd"
                        />
                      </svg>
                    </div>
                  </div>
                </button>
              </li>
            ))}

            {customers.length === 0 && (
              <li className="p-8 text-center text-gray-500">Chưa có dữ liệu khách hàng.</li>
            )}
          </ul>

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="bg-white px-4 py-3 border-t border-gray-200 flex items-center justify-between sm:px-6">
              <div className="flex-1 flex justify-between">
                <button
                  onClick={() => setPage(p => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                >
                  Trước
                </button>
                <div className="flex items-center">
                  Trang {page} / {totalPages}
                </div>
                <button
                  onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                  disabled={page === totalPages}
                  className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                >
                  Tiếp
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

export default CustomerList
