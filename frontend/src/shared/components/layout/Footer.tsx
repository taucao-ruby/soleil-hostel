import React from 'react'
import { Link } from 'react-router-dom'

/**
 * Footer Component
 *
 * Simple footer with contact information and links.
 */

const Footer: React.FC = () => {
  const currentYear = new Date().getFullYear()

  return (
    <footer className="text-gray-300 bg-gray-900">
      <div className="px-4 py-12 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 gap-8 md:grid-cols-4">
          {/* Brand */}
          <div className="col-span-1 md:col-span-2">
            <h3 className="mb-4 text-2xl font-bold text-white">Soleil Hostel</h3>
            <p className="mb-4 text-gray-400">
              Your sunny stay in the heart of the city. Comfortable rooms, affordable prices, and
              unforgettable experiences.
            </p>
            <div className="flex items-center space-x-2 text-yellow-400">
              <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z" />
              </svg>
              <span className="text-sm">Premium hostel experience since 2024</span>
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <h4 className="mb-4 font-semibold text-white">Quick Links</h4>
            <ul className="space-y-2">
              <li>
                <Link to="/" className="transition-colors hover:text-white">
                  Home
                </Link>
              </li>
              <li>
                <Link to="/rooms" className="transition-colors hover:text-white">
                  Our Rooms
                </Link>
              </li>
              <li>
                <Link to="/booking" className="transition-colors hover:text-white">
                  Book Now
                </Link>
              </li>
              <li>
                <Link to="/dashboard" className="transition-colors hover:text-white">
                  Dashboard
                </Link>
              </li>
            </ul>
          </div>

          {/* Contact */}
          <div>
            <h4 className="mb-4 font-semibold text-white">Contact Us</h4>
            <ul className="space-y-2 text-sm">
              <li className="flex items-start space-x-2">
                <svg
                  className="w-5 h-5 text-blue-400 mt-0.5"
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
                <span>123 Sunshine Street, Downtown</span>
              </li>
              <li className="flex items-center space-x-2">
                <svg
                  className="w-5 h-5 text-blue-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                  />
                </svg>
                <a
                  href="mailto:info@soleilhostel.com"
                  className="transition-colors hover:text-white"
                >
                  info@soleilhostel.com
                </a>
              </li>
              <li className="flex items-center space-x-2">
                <svg
                  className="w-5 h-5 text-blue-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                  />
                </svg>
                <a href="tel:+1234567890" className="transition-colors hover:text-white">
                  +1 (234) 567-890
                </a>
              </li>
            </ul>
          </div>
        </div>

        {/* Bottom Bar */}
        <div className="flex flex-col items-center justify-between pt-8 mt-8 border-t border-gray-800 md:flex-row">
          <p className="text-sm text-gray-400">
            © {currentYear} Soleil Hostel. All rights reserved.
          </p>
          <div className="flex mt-4 space-x-6 md:mt-0">
            <a href="#" className="text-gray-400 transition-colors hover:text-white">
              Privacy Policy
            </a>
            <a href="#" className="text-gray-400 transition-colors hover:text-white">
              Terms of Service
            </a>
          </div>
        </div>
      </div>
    </footer>
  )
}

export default Footer
