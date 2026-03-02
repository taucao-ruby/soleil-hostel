import React, { useEffect, useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { getLocations } from './location.api'
import type { Location } from './location.types'
import LocationCard from './LocationCard'
import SkeletonCard from '@/shared/components/ui/SkeletonCard'

/**
 * LocationList Page
 *
 * Displays all active Soleil locations in a responsive grid.
 *
 * Features:
 * - Fetches locations from /v1/locations endpoint
 * - City filter dropdown
 * - Skeleton loading state
 * - Error handling
 * - Responsive card grid (1/2/3 columns)
 * - Click to navigate to location detail
 */
const LocationList: React.FC = () => {
  const [locations, setLocations] = useState<Location[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedCity, setSelectedCity] = useState<string>('')
  const navigate = useNavigate()

  useEffect(() => {
    const fetchLocations = async () => {
      try {
        setLoading(true)
        setError(null)
        const data = await getLocations()
        setLocations(data)
      } catch {
        setError('Failed to load locations. Please try again later.')
      } finally {
        setLoading(false)
      }
    }

    fetchLocations()
  }, [])

  const cities = useMemo(() => {
    return [...new Set(locations.map(l => l.address.city))].sort()
  }, [locations])

  const filteredLocations = useMemo(() => {
    if (!selectedCity) return locations
    return locations.filter(l => l.address.city === selectedCity)
  }, [locations, selectedCity])

  const handleLocationClick = (slug: string) => {
    navigate(`/locations/${slug}`)
  }

  return (
    <section className="min-h-screen px-4 py-12 bg-gray-50">
      <div className="mx-auto max-w-7xl">
        {/* Header */}
        <div className="mb-12 text-center">
          <h1 className="mb-4 text-4xl font-bold text-gray-900">Our Locations</h1>
          <p className="text-lg text-gray-600">
            Discover our collection of unique stays across Hue City
          </p>
        </div>

        {/* City Filter */}
        {cities.length > 1 && !loading && (
          <div className="flex justify-center mb-8">
            <div>
              <label htmlFor="city-filter" className="block mb-2 text-sm font-medium text-gray-700">
                Filter by City
              </label>
              <select
                id="city-filter"
                value={selectedCity}
                onChange={e => setSelectedCity(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Cities</option>
                {cities.map(city => (
                  <option key={city} value={city}>
                    {city}
                  </option>
                ))}
              </select>
            </div>
          </div>
        )}

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

        {/* Loading State */}
        {loading && (
          <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
            {[1, 2, 3, 4, 5].map(i => (
              <SkeletonCard key={i} />
            ))}
          </div>
        )}

        {/* Locations Grid */}
        {!loading && !error && filteredLocations.length > 0 && (
          <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
            {filteredLocations.map(location => (
              <LocationCard
                key={location.id}
                location={location}
                onClick={() => handleLocationClick(location.slug)}
              />
            ))}
          </div>
        )}

        {/* Empty State */}
        {!loading && !error && filteredLocations.length === 0 && (
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
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
              />
            </svg>
            <h3 className="mb-2 text-xl font-semibold text-gray-700">No locations found</h3>
            <p className="text-gray-500">
              {selectedCity
                ? `No locations in ${selectedCity}. Try selecting a different city.`
                : 'No locations available at the moment.'}
            </p>
          </div>
        )}
      </div>
    </section>
  )
}

export default LocationList
