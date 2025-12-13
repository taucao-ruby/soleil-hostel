import React from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'

/**
 * HomePage Component
 *
 * Landing page with hero section and CTAs
 */

const HomePage: React.FC = () => {
  const navigate = useNavigate()
  const { isAuthenticated } = useAuth()

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-50">
      {/* Hero Section */}
      <div className="px-4 py-16 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div className="text-center">
          <h1 className="mb-6 text-5xl font-extrabold text-gray-900 md:text-6xl">
            Welcome to{' '}
            <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-pink-500">
              Soleil Hostel
            </span>
          </h1>
          <p className="max-w-3xl mx-auto mb-8 text-xl text-gray-600 md:text-2xl">
            Your sunny stay in the heart of the city. Comfortable rooms, affordable prices, and
            unforgettable experiences.
          </p>

          {/* CTA Buttons */}
          <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
            <button
              onClick={() => navigate('/rooms')}
              className="px-8 py-4 text-lg font-semibold text-white transition-all bg-blue-600 rounded-lg shadow-lg hover:bg-blue-700 hover:shadow-xl"
            >
              View Our Rooms
            </button>
            {!isAuthenticated && (
              <button
                onClick={() => navigate('/register')}
                className="px-8 py-4 text-lg font-semibold text-blue-600 transition-all bg-white border-2 border-blue-600 rounded-lg hover:bg-blue-50"
              >
                Create Account
              </button>
            )}
            {isAuthenticated && (
              <button
                onClick={() => navigate('/booking')}
                className="px-8 py-4 text-lg font-semibold text-blue-600 transition-all bg-white border-2 border-blue-600 rounded-lg hover:bg-blue-50"
              >
                Book Now
              </button>
            )}
          </div>
        </div>

        {/* Features Grid */}
        <div className="grid grid-cols-1 gap-8 mt-20 md:grid-cols-3">
          {/* Feature 1 */}
          <div className="p-8 transition-shadow bg-white shadow-lg rounded-xl hover:shadow-xl">
            <div className="flex items-center justify-center w-16 h-16 mb-4 bg-blue-100 rounded-full">
              <svg
                className="w-8 h-8 text-blue-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                />
              </svg>
            </div>
            <h3 className="mb-2 text-xl font-bold text-gray-900">Comfortable Rooms</h3>
            <p className="text-gray-600">
              Clean, modern rooms with all the amenities you need for a perfect stay.
            </p>
          </div>

          {/* Feature 2 */}
          <div className="p-8 transition-shadow bg-white shadow-lg rounded-xl hover:shadow-xl">
            <div className="flex items-center justify-center w-16 h-16 mb-4 bg-pink-100 rounded-full">
              <svg
                className="w-8 h-8 text-pink-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                />
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                />
              </svg>
            </div>
            <h3 className="mb-2 text-xl font-bold text-gray-900">Prime Location</h3>
            <p className="text-gray-600">
              Located in the heart of the city, close to major attractions and transport.
            </p>
          </div>

          {/* Feature 3 */}
          <div className="p-8 transition-shadow bg-white shadow-lg rounded-xl hover:shadow-xl">
            <div className="flex items-center justify-center w-16 h-16 mb-4 bg-yellow-100 rounded-full">
              <svg
                className="w-8 h-8 text-yellow-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <h3 className="mb-2 text-xl font-bold text-gray-900">Affordable Prices</h3>
            <p className="text-gray-600">
              Quality accommodation at prices that won't break the bank.
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}

export default HomePage
