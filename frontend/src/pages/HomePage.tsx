import React, { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'
import { getRooms } from '@/features/rooms/room.api'
import { Room } from '@/features/rooms/room.types'
import Button from '@/shared/components/ui/Button'
import Card from '@/shared/components/ui/Card'
import { SkeletonCard } from '@/shared/components/ui/Skeleton'

/**
 * HomePage Component
 *
 * Professional landing page with:
 * - Hero section with background overlay
 * - Featured rooms preview
 * - Feature highlights
 * - Reviews/testimonials
 * - Call to action
 */

const HomePage: React.FC = () => {
  const navigate = useNavigate()
  const { isAuthenticated } = useAuth()
  const [featuredRooms, setFeaturedRooms] = useState<Room[]>([])
  const [loadingRooms, setLoadingRooms] = useState(true)

  useEffect(() => {
    const fetchFeaturedRooms = async () => {
      try {
        const rooms = await getRooms()
        // Show first 3 available rooms
        setFeaturedRooms(rooms.filter(r => r.status === 'available').slice(0, 3))
      } catch (err) {
        console.error('Failed to fetch rooms:', err)
      } finally {
        setLoadingRooms(false)
      }
    }

    fetchFeaturedRooms()
  }, [])

  return (
    <div className="min-h-screen">
      {/* Hero Section */}
      <section
        className="relative h-[600px] flex items-center justify-center bg-cover bg-center"
        style={{
          backgroundImage:
            'url(https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80)',
        }}
      >
        {/* Overlay */}
        <div className="absolute inset-0 bg-gradient-to-r from-blue-900/80 to-blue-600/70"></div>

        {/* Content */}
        <div className="relative z-10 px-4 mx-auto text-center max-w-4xl">
          <h1 className="mb-6 text-5xl font-extrabold text-white drop-shadow-lg animate-fade-in md:text-7xl">
            Welcome to <span className="text-yellow-400">Soleil Hostel</span>
          </h1>
          <p className="mb-10 text-xl text-white drop-shadow-md md:text-2xl">
            Your sunny stay in the heart of the city. Comfortable rooms, affordable prices, and
            unforgettable experiences.
          </p>

          <div className="flex flex-col justify-center gap-4 sm:flex-row">
            <Button
              variant="secondary"
              size="lg"
              onClick={() => navigate('/rooms')}
              className="transition-transform transform shadow-2xl hover:scale-105"
            >
              Explore Our Rooms
            </Button>
            {!isAuthenticated ? (
              <Button
                variant="outline"
                size="lg"
                onClick={() => navigate('/register')}
                className="text-white border-white shadow-2xl bg-white/10 backdrop-blur-sm hover:bg-white/20"
              >
                Get Started
              </Button>
            ) : (
              <Button
                variant="primary"
                size="lg"
                onClick={() => navigate('/booking')}
                className="transition-transform transform shadow-2xl hover:scale-105"
              >
                Book Your Stay
              </Button>
            )}
          </div>
        </div>
      </section>

      {/* Featured Rooms Section */}
      <section className="px-4 py-20 bg-gray-50">
        <div className="mx-auto max-w-7xl">
          <div className="mb-12 text-center">
            <h2 className="mb-4 text-4xl font-bold text-gray-900">Featured Rooms</h2>
            <p className="text-lg text-gray-600">Discover our most popular accommodations</p>
          </div>

          <div className="grid grid-cols-1 gap-8 md:grid-cols-3">
            {loadingRooms
              ? Array.from({ length: 3 }).map((_, i) => <SkeletonCard key={i} />)
              : featuredRooms.map(room => (
                  <Card key={room.id} hover>
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
                    <Card.Content>
                      <h3 className="mb-2 text-xl font-bold text-gray-900">{room.name}</h3>
                      <p className="mb-4 text-sm text-gray-600 line-clamp-2">
                        {room.description || 'Comfortable room with all amenities'}
                      </p>
                      <div className="flex items-center justify-between">
                        <div className="text-2xl font-bold text-blue-600">
                          ${room.price}
                          <span className="text-sm font-normal text-gray-500">/night</span>
                        </div>
                        <Button
                          size="sm"
                          onClick={() => navigate(isAuthenticated ? '/booking' : '/login')}
                        >
                          Book Now
                        </Button>
                      </div>
                    </Card.Content>
                  </Card>
                ))}
          </div>

          <div className="mt-10 text-center">
            <Button variant="outline" size="lg" onClick={() => navigate('/rooms')}>
              View All Rooms
            </Button>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="px-4 py-20 bg-white">
        <div className="mx-auto max-w-7xl">
          <div className="mb-12 text-center">
            <h2 className="mb-4 text-4xl font-bold text-gray-900">Why Choose Us</h2>
            <p className="text-lg text-gray-600">Experience the best hostel stay</p>
          </div>

          <div className="grid grid-cols-1 gap-8 md:grid-cols-3">
            <Card className="text-center">
              <Card.Content className="py-8">
                <div className="flex items-center justify-center w-20 h-20 mx-auto mb-4 bg-blue-100 rounded-full">
                  <svg
                    className="w-10 h-10 text-blue-600"
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
              </Card.Content>
            </Card>

            <Card className="text-center">
              <Card.Content className="py-8">
                <div className="flex items-center justify-center w-20 h-20 mx-auto mb-4 bg-pink-100 rounded-full">
                  <svg
                    className="w-10 h-10 text-pink-600"
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
              </Card.Content>
            </Card>

            <Card className="text-center">
              <Card.Content className="py-8">
                <div className="flex items-center justify-center w-20 h-20 mx-auto mb-4 bg-yellow-100 rounded-full">
                  <svg
                    className="w-10 h-10 text-yellow-600"
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
              </Card.Content>
            </Card>
          </div>
        </div>
      </section>

      {/* Testimonials Section */}
      <section className="px-4 py-20 bg-gradient-to-br from-blue-50 to-white">
        <div className="mx-auto max-w-7xl">
          <div className="mb-12 text-center">
            <h2 className="mb-4 text-4xl font-bold text-gray-900">Guest Reviews</h2>
            <p className="text-lg text-gray-600">See what our guests are saying</p>
          </div>

          <div className="grid grid-cols-1 gap-8 md:grid-cols-3">
            {[
              {
                name: 'Sarah Johnson',
                rating: 5,
                text: 'Amazing stay! The rooms were spotless and the staff was incredibly friendly. Will definitely come back!',
              },
              {
                name: 'Mike Chen',
                rating: 5,
                text: 'Perfect location and great value for money. The hostel has everything you need for a comfortable stay.',
              },
              {
                name: 'Emma Wilson',
                rating: 5,
                text: 'Loved the modern design and the community vibe. Met so many interesting travelers here!',
              },
            ].map((review, index) => (
              <Card key={index}>
                <Card.Content className="py-6">
                  <div className="flex items-center mb-4">
                    {Array.from({ length: review.rating }).map((_, i) => (
                      <svg
                        key={i}
                        className="w-5 h-5 text-yellow-400"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                      </svg>
                    ))}
                  </div>
                  <p className="mb-4 italic text-gray-600">"{review.text}"</p>
                  <p className="font-semibold text-gray-900">— {review.name}</p>
                </Card.Content>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="px-4 py-20 text-white bg-gradient-to-r from-blue-600 to-blue-700">
        <div className="mx-auto text-center max-w-4xl">
          <h2 className="mb-6 text-4xl font-bold">Ready to Book Your Stay?</h2>
          <p className="mb-8 text-xl text-blue-100">
            Join thousands of happy guests who chose Soleil Hostel for their perfect getaway.
          </p>
          <Button
            variant="secondary"
            size="lg"
            onClick={() => navigate(isAuthenticated ? '/booking' : '/register')}
            className="transition-transform transform shadow-2xl hover:scale-105"
          >
            {isAuthenticated ? 'Book Now' : 'Get Started Today'}
          </Button>
        </div>
      </section>
    </div>
  )
}

export default HomePage
