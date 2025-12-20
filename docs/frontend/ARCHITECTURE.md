#  Frontend Architecture Documentation

> Chi tiết kiến trúc và tài liệu cho từng phần của frontend React 19 + TypeScript

## Tổng quan Kiến trúc

### Feature-Sliced Architecture
Frontend sử dụng **Feature-Sliced Design (FSD)** - một kiến trúc hiện đại để tổ chức code:

`
src/
 app/           # Lớp ứng dụng - cấu hình global
 features/      # Lớp tính năng - logic nghiệp vụ
 shared/        # Lớp chia sẻ - components và utils tái sử dụng
 entities/      # Lớp thực thể - business entities (future)
 widgets/       # Lớp widget - composite components (future)
`

### Nguyên tắc Thiết kế
- **Separation of Concerns**: Mỗi layer có trách nhiệm riêng biệt
- **Dependency Direction**: Các layer cao không phụ thuộc vào layer thấp
- **Reusability**: Shared components có thể tái sử dụng
- **Testability**: Mỗi feature có thể test độc lập

---

## 1. App Layer (src/app/)

### 1.1 App.tsx - Component Gốc
`	ypescript
// src/app/App.tsx
import React from 'react'
import ErrorBoundary from '@/shared/components/ErrorBoundary'
import Providers from './providers'
import Router from './router'

const App: React.FC = () => {
  return (
    <ErrorBoundary>
      <Providers>
        <Router />
      </Providers>
    </ErrorBoundary>
  )
}
`

**Trách nhiệm:**
- Cung cấp Error Boundary cho toàn bộ ứng dụng
- Wrap các Context Providers
- Khởi tạo React Router

**Error Boundary Pattern:**
`	ypescript
// src/shared/components/ErrorBoundary.tsx
class ErrorBoundary extends React.Component {
  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Log error to monitoring service
    console.error('Application Error:', error, errorInfo)
  }

  render() {
    if (this.state.hasError) {
      return <FallbackUI />
    }
    return this.props.children
  }
}
`

### 1.2 Providers (providers.tsx)
`	ypescript
// src/app/providers.tsx
import React from 'react'
import { AuthProvider } from '@/features/auth/AuthContext'

const Providers: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  return <AuthProvider>{children}</AuthProvider>
}
`

**Current Providers:**
- AuthProvider: Quản lý authentication state
- **Future:** ThemeProvider, I18nProvider, QueryClientProvider

### 1.3 Router (outer.tsx)
`	ypescript
// src/app/router.tsx
import { createBrowserRouter } from 'react-router-dom'
import Layout from './Layout'
import HomePage from '@/pages/HomePage'

// Lazy-loaded features
const LoginPage = lazy(() => import('@/features/auth/LoginPage'))
const RoomList = lazy(() => import('@/features/rooms/RoomList'))
const BookingForm = lazy(() => import('@/features/booking/BookingForm'))

export const router = createBrowserRouter([
  {
    path: '/',
    element: <Layout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'login', element: withSuspense(LoginPage) },
      // ... more routes
    ]
  }
])
`

**Tính năng:**
- React Router v7 với nested routes
- Code splitting với React.lazy
- Suspense boundaries cho loading states
- Protected routes với authentication guards

### 1.4 Layout (Layout.tsx)
`	ypescript
// src/app/Layout.tsx
import React from 'react'
import { Outlet } from 'react-router-dom'
import Header from '@/shared/components/layout/Header'
import Footer from '@/shared/components/layout/Footer'

const Layout: React.FC = () => {
  return (
    <div className="min-h-screen flex flex-col">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  )
}
`

**Cấu trúc Layout:**
- Sticky header với navigation
- Main content area với <Outlet />
- Footer với thông tin liên hệ
- Responsive design

---

## 2. Features Layer (src/features/)

### 2.1 Authentication Feature (eatures/auth/)

#### AuthContext.tsx
`	ypescript
// features/auth/AuthContext.tsx
interface AuthContextType {
  user: User | null
  login: (credentials: LoginCredentials) => Promise<void>
  logout: () => Promise<void>
  isAuthenticated: boolean
  isLoading: boolean
}

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  // Check auth status on mount
  useEffect(() => {
    checkAuthStatus()
  }, [])

  return (
    <AuthContext.Provider value={{ user, login, logout, isAuthenticated, isLoading }}>
      {children}
    </AuthContext.Provider>
  )
}
`

**Authentication Flow:**
1. **Login**: Gửi credentials  Nhận httpOnly cookie + CSRF token
2. **Auto-refresh**: Interceptor tự động refresh token khi 401
3. **Logout**: Clear session + redirect to login
4. **Protected Routes**: Kiểm tra auth status trước khi render

#### LoginPage.tsx
`	ypescript
// features/auth/LoginPage.tsx
const LoginPage: React.FC = () => {
  const { login } = useAuth()
  const [formData, setFormData] = useState({ email: '', password: '' })

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    try {
      await login(formData)
      navigate('/dashboard')
    } catch (error) {
      toast.error('Login failed')
    }
  }

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="email"
        value={formData.email}
        onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
      />
      {/* ... password field, submit button */}
    </form>
  )
}
`

#### RegisterPage.tsx
`	ypescript
// features/auth/RegisterPage.tsx
const RegisterPage: React.FC = () => {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: ''
  })

  // Form validation với Zod
  const schema = z.object({
    name: z.string().min(2),
    email: z.string().email(),
    password: z.string().min(8),
    password_confirmation: z.string()
  }).refine(data => data.password === data.password_confirmation, {
    message: "Passwords don't match"
  })

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    try {
      const validData = schema.parse(formData)
      await register(validData)
      toast.success('Registration successful!')
      navigate('/login')
    } catch (error) {
      if (error instanceof z.ZodError) {
        // Handle validation errors
      }
    }
  }
}
`

#### ProtectedRoute.tsx
`	ypescript
// features/auth/ProtectedRoute.tsx
interface ProtectedRouteProps {
  children: React.ReactNode
  redirectTo?: string
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  redirectTo = '/login'
}) => {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) {
    return <LoadingSpinner />
  }

  if (!isAuthenticated) {
    return <Navigate to={redirectTo} replace />
  }

  return <>{children}</>
}
`

### 2.2 Booking Feature (eatures/booking/)

#### BookingForm.tsx
`	ypescript
// features/booking/BookingForm.tsx
interface BookingFormData {
  room_id: number
  guest_name: string
  guest_email: string
  check_in: Date
  check_out: Date
  guests: number
}

const BookingForm: React.FC = () => {
  const { user } = useAuth()
  const navigate = useNavigate()

  const [formData, setFormData] = useState<BookingFormData>({
    room_id: 0,
    guest_name: user?.name || '',
    guest_email: user?.email || '',
    check_in: new Date(),
    check_out: new Date(Date.now() + 86400000), // +1 day
    guests: 1
  })

  // Zod validation schema
  const bookingSchema = z.object({
    room_id: z.number().min(1, 'Please select a room'),
    guest_name: z.string().min(2, 'Name must be at least 2 characters'),
    guest_email: z.string().email('Invalid email address'),
    check_in: z.date(),
    check_out: z.date(),
    guests: z.number().min(1).max(10)
  }).refine(data => data.check_out > data.check_in, {
    message: 'Check-out must be after check-in',
    path: ['check_out']
  })

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    try {
      const validData = bookingSchema.parse(formData)
      const response = await bookingApi.create(validData)
      toast.success('Booking created successfully!')
      navigate(/booking/)
    } catch (error) {
      if (error instanceof z.ZodError) {
        // Handle validation errors
        error.errors.forEach(err => {
          toast.error(err.message)
        })
      }
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Room selection */}
      <div>
        <label className="block text-sm font-medium text-gray-700">
          Select Room
        </label>
        <select
          value={formData.room_id}
          onChange={(e) => setFormData(prev => ({
            ...prev,
            room_id: parseInt(e.target.value)
          }))}
          className="mt-1 block w-full rounded-md border-gray-300"
        >
          <option value={0}>Choose a room...</option>
          {/* Room options */}
        </select>
      </div>

      {/* Guest information */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700">
            Guest Name
          </label>
          <input
            type="text"
            value={formData.guest_name}
            onChange={(e) => setFormData(prev => ({
              ...prev,
              guest_name: e.target.value
            }))}
            className="mt-1 block w-full rounded-md border-gray-300"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            Guest Email
          </label>
          <input
            type="email"
            value={formData.guest_email}
            onChange={(e) => setFormData(prev => ({
              ...prev,
              guest_email: e.target.value
            }))}
            className="mt-1 block w-full rounded-md border-gray-300"
          />
        </div>
      </div>

      {/* Date selection */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700">
            Check-in Date
          </label>
          <DatePicker
            selected={formData.check_in}
            onChange={(date) => date && setFormData(prev => ({
              ...prev,
              check_in: date
            }))}
            className="mt-1 block w-full rounded-md border-gray-300"
            minDate={new Date()}
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            Check-out Date
          </label>
          <DatePicker
            selected={formData.check_out}
            onChange={(date) => date && setFormData(prev => ({
              ...prev,
              check_out: date
            }))}
            className="mt-1 block w-full rounded-md border-gray-300"
            minDate={formData.check_in}
          />
        </div>
      </div>

      {/* Number of guests */}
      <div>
        <label className="block text-sm font-medium text-gray-700">
          Number of Guests
        </label>
        <select
          value={formData.guests}
          onChange={(e) => setFormData(prev => ({
            ...prev,
            guests: parseInt(e.target.value)
          }))}
          className="mt-1 block w-full rounded-md border-gray-300"
        >
          {[1,2,3,4,5,6,7,8,9,10].map(num => (
            <option key={num} value={num}>{num}</option>
          ))}
        </select>
      </div>

      {/* Submit button */}
      <button
        type="submit"
        className="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700"
      >
        Create Booking
      </button>
    </form>
  )
}
`

**Tính năng Booking Form:**
- **Validation**: Zod schema với custom validation rules
- **Date Handling**: React DatePicker với min/max constraints
- **Form State**: Controlled components với TypeScript
- **Error Handling**: Toast notifications cho validation errors
- **Navigation**: Redirect sau khi tạo booking thành công

#### Booking API Service
`	ypescript
// features/booking/api.ts
import api from '@/services/api'
import { Booking, BookingCreate, BookingResponse } from '@/types/api'

export const bookingApi = {
  // Tạo booking mới
  create: async (data: BookingCreate): Promise<BookingResponse> => {
    const response = await api.post('/bookings', data)
    const result = validateApiResponse(BookingResponseSchema, response.data)
    return result
  },

  // Lấy danh sách bookings
  getAll: async (): Promise<Booking[]> => {
    const response = await api.get('/bookings')
    const result = validateApiResponse(BookingsResponseSchema, response.data)
    return result.data
  },

  // Lấy booking theo ID
  getById: async (id: number): Promise<Booking> => {
    const response = await api.get(/bookings/)
    const result = validateApiResponse(BookingResponseSchema, response.data)
    return result.data
  },

  // Cập nhật booking
  update: async (id: number, data: Partial<BookingCreate>): Promise<Booking> => {
    const response = await api.put(/bookings/, data)
    const result = validateApiResponse(BookingResponseSchema, response.data)
    return result.data
  },

  // Hủy booking
  cancel: async (id: number): Promise<void> => {
    await api.delete(/bookings/)
  }
}
`

### 2.3 Rooms Feature (eatures/rooms/)

#### RoomList.tsx
`	ypescript
// features/rooms/RoomList.tsx
import React, { useEffect, useState } from 'react'
import { roomsApi } from './api'
import { Room } from '@/types/api'
import RoomCard from './RoomCard'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'

const RoomList: React.FC = () => {
  const [rooms, setRooms] = useState<Room[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const fetchRooms = async () => {
      try {
        const data = await roomsApi.getAll()
        setRooms(data)
      } catch (err) {
        setError('Failed to load rooms')
        console.error('Room fetch error:', err)
      } finally {
        setLoading(false)
      }
    }

    fetchRooms()
  }, [])

  if (loading) {
    return <LoadingSpinner size="xl" fullScreen />
  }

  if (error) {
    return (
      <div className="text-center py-12">
        <p className="text-red-600 mb-4">{error}</p>
        <button
          onClick={() => window.location.reload()}
          className="bg-blue-600 text-white px-4 py-2 rounded"
        >
          Try Again
        </button>
      </div>
    )
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Available Rooms</h1>

      {rooms.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-gray-500">No rooms available at the moment.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {rooms.map(room => (
            <RoomCard key={room.id} room={room} />
          ))}
        </div>
      )}
    </div>
  )
}

export default RoomList
`

#### RoomCard.tsx
`	ypescript
// features/rooms/RoomCard.tsx
import React from 'react'
import { Link } from 'react-router-dom'
import { Room } from '@/types/api'

interface RoomCardProps {
  room: Room
}

const RoomCard: React.FC<RoomCardProps> = ({ room }) => {
  const getStatusColor = (status: Room['status']) => {
    switch (status) {
      case 'available':
        return 'bg-green-100 text-green-800'
      case 'booked':
        return 'bg-red-100 text-red-800'
      case 'maintenance':
        return 'bg-yellow-100 text-yellow-800'
      default:
        return 'bg-gray-100 text-gray-800'
    }
  }

  return (
    <div className="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
      {/* Room Image */}
      {room.image_url && (
        <img
          src={room.image_url}
          alt={room.name}
          className="w-full h-48 object-cover"
        />
      )}

      <div className="p-6">
        {/* Room Header */}
        <div className="flex justify-between items-start mb-4">
          <h3 className="text-xl font-semibold text-gray-900">{room.name}</h3>
          <span className={px-2 py-1 rounded-full text-xs font-medium }>
            {room.status.charAt(0).toUpperCase() + room.status.slice(1)}
          </span>
        </div>

        {/* Room Details */}
        <div className="space-y-2 mb-4">
          <p className="text-gray-600">
            <span className="font-medium">Price:</span> /night
          </p>
          <p className="text-gray-600">
            <span className="font-medium">Max Guests:</span> {room.max_guests}
          </p>
          {room.description && (
            <p className="text-gray-600 text-sm">{room.description}</p>
          )}
        </div>

        {/* Action Button */}
        <Link
          to={/booking?room=}
          className={w-full block text-center py-2 px-4 rounded-md font-medium transition-colors }
          onClick={(e) => {
            if (room.status !== 'available') {
              e.preventDefault()
            }
          }}
        >
          {room.status === 'available' ? 'Book Now' : 'Not Available'}
        </Link>
      </div>
    </div>
  )
}

export default RoomCard
`

#### Rooms API Service
`	ypescript
// features/rooms/api.ts
import api from '@/services/api'
import { Room, RoomsResponse } from '@/types/api'

export const roomsApi = {
  // Lấy tất cả rooms
  getAll: async (): Promise<Room[]> => {
    const response = await api.get('/rooms')
    const result = validateApiResponse(RoomsResponseSchema, response.data)
    return result.data
  },

  // Lấy room theo ID
  getById: async (id: number): Promise<Room> => {
    const response = await api.get(/rooms/)
    const result = validateApiResponse(RoomResponseSchema, response.data)
    return result.data
  },

  // Lấy rooms theo status
  getByStatus: async (status: Room['status']): Promise<Room[]> => {
    const response = await api.get(/rooms?status=)
    const result = validateApiResponse(RoomsResponseSchema, response.data)
    return result.data
  },

  // Tìm kiếm rooms
  search: async (query: string): Promise<Room[]> => {
    const response = await api.get(/rooms/search?q=)
    const result = validateApiResponse(RoomsResponseSchema, response.data)
    return result.data
  }
}
`

---

## 3. Services Layer (src/services/)

### 3.1 API Service (services/api.ts)

**Axios Configuration:**
`	ypescript
// services/api.ts
import axios from 'axios'

const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

const api = axios.create({
  baseURL: BASE_URL,
  headers: {
    Accept: 'application/json',
  },
  withCredentials: true, //  CRITICAL: Cho phép gửi httpOnly cookies
})
`

**Request Interceptor - CSRF Protection:**
`	ypescript
// Thêm X-XSRF-TOKEN header cho requests non-GET
api.interceptors.request.use((config) => {
  if (config.method && ['post', 'put', 'patch', 'delete'].includes(config.method)) {
    const csrfToken = sessionStorage.getItem('csrf_token')
    if (csrfToken) {
      config.headers['X-XSRF-TOKEN'] = csrfToken
    }
  }
  return config
})
`

**Response Interceptor - Auto Token Refresh:**
`	ypescript
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config

    // Chỉ retry khi 401 Unauthorized và chưa retry
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true

      try {
        // ========== REFRESH TOKEN ==========
        // Browser tự động gửi httpOnly cookie
        const refreshResponse = await api.post('/auth/refresh-httponly')

        // Cập nhật CSRF token từ refresh response
        if (refreshResponse.data.csrf_token) {
          sessionStorage.setItem('csrf_token', refreshResponse.data.csrf_token)
        }

        // ========== RETRY ORIGINAL REQUEST ==========
        return api(originalRequest)
      } catch (refreshError) {
        // ========== REFRESH FAILED ==========
        sessionStorage.clear()
        localStorage.clear()
        window.location.href = '/login'
        return Promise.reject(refreshError)
      }
    }

    return Promise.reject(error)
  }
)
`

### 3.2 Auth Service (services/auth.ts)

`	ypescript
// services/auth.ts
import api from './api'
import { validateApiResponse, AuthResponseSchema } from '@/types/api'

export const authService = {
  // Login với httpOnly cookie
  async loginHttpOnly(credentials: {
    email: string
    password: string
    remember_me?: boolean
  }) {
    const response = await api.post('/auth/login-httponly', credentials)
    const result = validateApiResponse(AuthResponseSchema, response.data)

    // Lưu CSRF token
    if (result.csrf_token) {
      sessionStorage.setItem('csrf_token', result.csrf_token)
    }

    return result
  },

  // Register
  async register(userData: {
    name: string
    email: string
    password: string
    password_confirmation: string
  }) {
    const response = await api.post('/auth/register', userData)
    return validateApiResponse(AuthResponseSchema, response.data)
  },

  // Logout
  async logout() {
    await api.post('/auth/logout')
    sessionStorage.clear()
    localStorage.clear()
  },

  // Kiểm tra auth status
  async checkAuth() {
    try {
      const response = await api.get('/auth/user')
      return validateApiResponse(AuthResponseSchema, response.data)
    } catch {
      return null
    }
  },

  // Refresh token
  async refreshToken() {
    const response = await api.post('/auth/refresh-httponly')
    const result = validateApiResponse(AuthResponseSchema, response.data)

    if (result.csrf_token) {
      sessionStorage.setItem('csrf_token', result.csrf_token)
    }

    return result
  }
}
`

---

## 4. Shared Layer (src/shared/)

### 4.1 Components (shared/components/)

#### UI Components (ui/)
`
shared/components/ui/
 Button.tsx          # Button component với variants
 Input.tsx           # Input field với validation
 Modal.tsx           # Modal dialog
 Card.tsx            # Card container
 Badge.tsx           # Status badges
 Spinner.tsx         # Loading spinner
`

**Button Component Example:**
`	ypescript
// shared/components/ui/Button.tsx
interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
}

const Button: React.FC<ButtonProps> = ({
  variant = 'primary',
  size = 'md',
  loading = false,
  children,
  className = '',
  ...props
}) => {
  const baseClasses = 'inline-flex items-center justify-center rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2'

  const variantClasses = {
    primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
    secondary: 'bg-gray-200 text-gray-900 hover:bg-gray-300 focus:ring-gray-500',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500'
  }

  const sizeClasses = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-base',
    lg: 'px-6 py-3 text-lg'
  }

  return (
    <button
      className={${baseClasses}   }
      disabled={loading || props.disabled}
      {...props}
    >
      {loading && <Spinner size="sm" className="mr-2" />}
      {children}
    </button>
  )
}
`

#### Layout Components (layout/)
`
shared/components/layout/
 Header.tsx          # Main navigation header
 Footer.tsx          # Site footer
 Sidebar.tsx         # Admin sidebar (future)
 Breadcrumbs.tsx     # Navigation breadcrumbs
`

**Header Component:**
`	ypescript
// shared/components/layout/Header.tsx
import React from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'
import Button from '@/shared/components/ui/Button'

const Header: React.FC = () => {
  const { user, logout, isAuthenticated } = useAuth()
  const navigate = useNavigate()

  const handleLogout = async () => {
    try {
      await logout()
      navigate('/login')
    } catch (error) {
      console.error('Logout error:', error)
    }
  }

  return (
    <header className="bg-white shadow-sm border-b">
      <div className="container mx-auto px-4">
        <div className="flex justify-between items-center h-16">
          {/* Logo */}
          <Link to="/" className="text-xl font-bold text-gray-900">
            Soleil Hostel
          </Link>

          {/* Navigation */}
          <nav className="hidden md:flex space-x-8">
            <Link to="/" className="text-gray-700 hover:text-gray-900">
              Home
            </Link>
            <Link to="/rooms" className="text-gray-700 hover:text-gray-900">
              Rooms
            </Link>
            {isAuthenticated && (
              <Link to="/dashboard" className="text-gray-700 hover:text-gray-900">
                Dashboard
              </Link>
            )}
          </nav>

          {/* Auth Buttons */}
          <div className="flex items-center space-x-4">
            {isAuthenticated ? (
              <div className="flex items-center space-x-4">
                <span className="text-gray-700">Welcome, {user?.name}</span>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={handleLogout}
                >
                  Logout
                </Button>
              </div>
            ) : (
              <div className="space-x-2">
                <Link to="/login">
                  <Button variant="secondary" size="sm">
                    Login
                  </Button>
                </Link>
                <Link to="/register">
                  <Button variant="primary" size="sm">
                    Register
                  </Button>
                </Link>
              </div>
            )}
          </div>
        </div>
      </div>
    </header>
  )
}
`

#### Feedback Components (eedback/)
`
shared/components/feedback/
 LoadingSpinner.tsx   # Loading states
 ErrorMessage.tsx     # Error display
 SuccessMessage.tsx   # Success notifications
 EmptyState.tsx       # Empty state UI
`

**LoadingSpinner Component:**
`	ypescript
// shared/components/feedback/LoadingSpinner.tsx
interface LoadingSpinnerProps {
  size?: 'sm' | 'md' | 'lg' | 'xl'
  fullScreen?: boolean
  message?: string
}

const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({
  size = 'md',
  fullScreen = false,
  message = 'Loading...'
}) => {
  const sizeClasses = {
    sm: 'w-4 h-4',
    md: 'w-8 h-8',
    lg: 'w-12 h-12',
    xl: 'w-16 h-16'
  }

  const spinner = (
    <div className="flex flex-col items-center justify-center space-y-4">
      <div
        className={${sizeClasses[size]} border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin}
      />
      {message && (
        <p className="text-gray-600 text-sm">{message}</p>
      )}
    </div>
  )

  if (fullScreen) {
    return (
      <div className="fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-50">
        {spinner}
      </div>
    )
  }

  return spinner
}
`

### 4.2 Utilities (shared/lib/ & shared/utils/)

#### API Utilities (lib/api.ts)
`	ypescript
// shared/lib/api.ts
import { z } from 'zod'

// Generic API response validator
export function validateApiResponse<T>(
  schema: z.ZodSchema<T>,
  data: unknown
): T {
  try {
    return schema.parse(data)
  } catch (error) {
    if (error instanceof z.ZodError) {
      console.error('[API Validation Error]', error.issues)
      throw new Error(
        API response validation failed: 
      )
    }
    throw error
  }
}

// Safe validator that returns null on failure
export function safeValidateApiResponse<T>(
  schema: z.ZodSchema<T>,
  data: unknown
): T | null {
  const result = schema.safeParse(data)
  if (result.success) {
    return result.data
  }
  console.warn('[API Validation Warning]', result.error.issues)
  return null
}
`

#### Date Utilities (utils/date.ts)
`	ypescript
// shared/utils/date.ts
import { format, differenceInDays, isAfter, isBefore } from 'date-fns'

export const dateUtils = {
  // Format date for display
  formatDate: (date: Date | string, formatStr = 'PPP') => {
    const d = typeof date === 'string' ? new Date(date) : date
    return format(d, formatStr)
  },

  // Calculate number of nights between check-in and check-out
  calculateNights: (checkIn: Date | string, checkOut: Date | string) => {
    const checkInDate = typeof checkIn === 'string' ? new Date(checkIn) : checkIn
    const checkOutDate = typeof checkOut === 'string' ? new Date(checkOut) : checkOut
    return differenceInDays(checkOutDate, checkInDate)
  },

  // Check if date is in the past
  isPast: (date: Date | string) => {
    const d = typeof date === 'string' ? new Date(date) : date
    return isBefore(d, new Date())
  },

  // Check if date is in the future
  isFuture: (date: Date | string) => {
    const d = typeof date === 'string' ? new Date(date) : date
    return isAfter(d, new Date())
  },

  // Get minimum check-out date (day after check-in)
  getMinCheckOut: (checkIn: Date | string) => {
    const checkInDate = typeof checkIn === 'string' ? new Date(checkIn) : checkIn
    const minCheckOut = new Date(checkInDate)
    minCheckOut.setDate(minCheckOut.getDate() + 1)
    return minCheckOut
  }
}
`

#### Form Utilities (utils/form.ts)
`	ypescript
// shared/utils/form.ts
import { z } from 'zod'

export const formUtils = {
  // Extract field errors from Zod error
  getFieldErrors: (error: z.ZodError) => {
    const fieldErrors: Record<string, string[]> = {}
    error.errors.forEach(err => {
      const field = err.path.join('.')
      if (!fieldErrors[field]) {
        fieldErrors[field] = []
      }
      fieldErrors[field].push(err.message)
    })
    return fieldErrors
  },

  // Check if field has errors
  hasFieldError: (fieldErrors: Record<string, string[]>, field: string) => {
    return fieldErrors[field] && fieldErrors[field].length > 0
  },

  // Get first error message for field
  getFieldError: (fieldErrors: Record<string, string[]>, field: string) => {
    return fieldErrors[field]?.[0] || ''
  }
}
`

---

## 5. Types Layer (src/types/)

### 5.1 API Types (	ypes/api.ts)

**Base Schemas:**
`	ypescript
// types/api.ts
import { z } from 'zod'

// Base API Response
export const ApiResponseSchema = z.object({
  message: z.string().optional(),
  success: z.boolean().optional(),
})

// Error Response
export const ApiErrorSchema = z.object({
  message: z.string(),
  errors: z.record(z.string(), z.array(z.string())).optional(),
  exception: z.string().optional(),
  file: z.string().optional(),
  line: z.number().optional(),
})
`

**Authentication Types:**
`	ypescript
// User Schema
export const UserSchema = z.object({
  id: z.number(),
  name: z.string(),
  email: z.string().email(),
  email_verified_at: z.string().nullable().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
})

// Auth Response Schema
export const AuthResponseSchema = ApiResponseSchema.extend({
  user: UserSchema,
  csrf_token: z.string(),
  token: z.string().optional(),
  expires_in_minutes: z.number().optional(),
  expires_at: z.string().optional(),
})
`

**Room Types:**
`	ypescript
// Room Schema
export const RoomSchema = z.object({
  id: z.number(),
  name: z.string(),
  price: z.number(),
  max_guests: z.number(),
  status: z.enum(['available', 'booked', 'maintenance']),
  description: z.string().optional(),
  image_url: z.string().url().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
})

// Rooms Response
export const RoomsResponseSchema = ApiResponseSchema.extend({
  data: z.array(RoomSchema),
})
`

**Booking Types:**
`	ypescript
// Booking Schema
export const BookingSchema = z.object({
  id: z.number(),
  room_id: z.number(),
  user_id: z.number().optional(),
  guest_name: z.string(),
  guest_email: z.string().email(),
  check_in: z.string(),
  check_out: z.string(),
  guests: z.number().optional(),
  status: z.enum(['pending', 'confirmed', 'cancelled', 'completed']).optional(),
  total_price: z.number().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
})

// Booking Response
export const BookingResponseSchema = ApiResponseSchema.extend({
  data: BookingSchema,
})

// Bookings Response
export const BookingsResponseSchema = ApiResponseSchema.extend({
  data: z.array(BookingSchema),
})
`

**TypeScript Types (Inferred):**
`	ypescript
// Inferred types from Zod schemas
export type User = z.infer<typeof UserSchema>
export type AuthResponse = z.infer<typeof AuthResponseSchema>
export type Room = z.infer<typeof RoomSchema>
export type RoomsResponse = z.infer<typeof RoomsResponseSchema>
export type Booking = z.infer<typeof BookingSchema>
export type BookingResponse = z.infer<typeof BookingResponseSchema>
export type BookingsResponse = z.infer<typeof BookingsResponseSchema>
export type ApiError = z.infer<typeof ApiErrorSchema>
`

---

## 6. Utils Layer (src/utils/)

### 6.1 Web Vitals (utils/webVitals.ts)

`	ypescript
// utils/webVitals.ts
import { onCLS, onFID, onFCP, onLCP, onTTFB } from 'web-vitals'

export function initWebVitals() {
  // Core Web Vitals monitoring
  onCLS(console.log)
  onFID(console.log)
  onFCP(console.log)
  onLCP(console.log)
  onTTFB(console.log)
}
`

### 6.2 Constants (utils/constants.ts)

`	ypescript
// utils/constants.ts
export const APP_CONFIG = {
  name: 'Soleil Hostel',
  version: '1.0.0',
  api: {
    baseUrl: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
    timeout: 30000,
  },
  pagination: {
    defaultPageSize: 20,
    maxPageSize: 100,
  },
  booking: {
    maxGuests: 10,
    minStay: 1, // nights
    maxStay: 30, // nights
  },
} as const

export const ROUTES = {
  home: '/',
  login: '/login',
  register: '/register',
  rooms: '/rooms',
  booking: '/booking',
  dashboard: '/dashboard',
} as const
`

---

## 7. Configuration Files

### 7.1 Vite Configuration (ite.config.ts)

`	ypescript
// vite.config.ts
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import vitePluginCspNonce from './vite-plugin-csp-nonce'
import path from 'path'

export default defineConfig({
  plugins: [
    react(),
    vitePluginCspNonce(), // Inject CSP nonce
  ],

  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },

  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    manifest: true,
    target: 'esnext',
    minify: 'terser',
    rollupOptions: {
      output: {
        manualChunks: {
          'react-vendor': ['react', 'react-dom'],
        },
      },
    },
  },

  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://backend:8000',
        changeOrigin: true,
        secure: false,
      },
    },
    open: false,
    cors: true,
    hmr: {
      overlay: true,
    },
  },
})
`

### 7.2 CSP Nonce Plugin (ite-plugin-csp-nonce.js)

`javascript
// vite-plugin-csp-nonce.js
export default function vitePluginCspNonce() {
  return {
    name: 'vite-plugin-csp-nonce',

    transformIndexHtml: (html) => {
      // Inject nonce vào <script> tags
      html = html.replace(
        /<script([^>]*)>/g,
        (match, attrs) => {
          if (attrs.includes('src=')) return match
          return <script nonce="{{ csp_nonce() }}">
        }
      )

      // Inject nonce vào <style> tags
      html = html.replace(
        /<style([^>]*)>/g,
        (match, attrs) => {
          return <style nonce="{{ csp_nonce() }}">
        }
      )

      // Inject CSP meta tag fallback
      const cspMeta = 
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';">
      

      if (html.includes('</head>')) {
        html = html.replace('</head>', cspMeta + '</head>')
      }

      return html
    },
  }
}
`

### 7.3 TypeScript Configuration (	sconfig.json)

`json
{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "skipLibCheck": true,
    "moduleResolution": "bundler",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "react-jsx",
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true,
    "baseUrl": ".",
    "paths": {
      "@/*": ["./src/*"]
    }
  },
  "include": ["src"],
  "references": [{ "path": "./tsconfig.node.json" }]
}
`

### 7.4 ESLint Configuration (eslint.config.js)

`javascript
// eslint.config.js
import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import tseslint from 'typescript-eslint'

export default tseslint.config(
  { ignores: ['dist'] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2020,
      globals: globals.browser,
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': [
        'warn',
        { allowConstantExport: true },
      ],
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      '@typescript-eslint/no-explicit-any': 'warn',
    },
  }
)
`

### 7.5 Tailwind Configuration (	ailwind.config.js)

`javascript
// tailwind.config.js
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        serif: ['Playfair Display', 'serif'],
      },
      colors: {
        primary: {
          50: '#eff6ff',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
        },
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
      },
    },
  },
  plugins: [],
}
`

---

## 8. Testing Configuration

### 8.1 Playwright Configuration (playwright.config.ts)

`	ypescript
// playwright.config.ts
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',

  use: {
    baseURL: 'http://localhost:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
  ],

  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5173',
    reuseExistingServer: !process.env.CI,
  },
})
`

### 8.2 Sample E2E Test

`	ypescript
// tests/booking.spec.ts
import { test, expect } from '@playwright/test'

test.describe('Booking Flow', () => {
  test('should create a booking successfully', async ({ page }) => {
    // Navigate to rooms page
    await page.goto('/rooms')

    // Click on first available room
    await page.click('[data-testid="room-card"]:first-child [data-testid="book-now"]')

    // Fill booking form
    await page.fill('[data-testid="guest-name"]', 'John Doe')
    await page.fill('[data-testid="guest-email"]', 'john@example.com')

    // Select dates
    await page.click('[data-testid="check-in"]')
    await page.click('[data-testid="check-in"] + .react-datepicker__day--today + .react-datepicker__day')

    await page.click('[data-testid="check-out"]')
    await page.click('[data-testid="check-out"] + .react-datepicker__day--today + .react-datepicker__day + .react-datepicker__day')

    // Submit booking
    await page.click('[data-testid="submit-booking"]')

    // Verify success message
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })
})
`

---

## 9. Performance & Security Best Practices

### 9.1 Performance Optimizations

**Code Splitting:**
- React.lazy cho route components
- Dynamic imports cho heavy components
- Vendor chunk separation

**Bundle Analysis:**
`ash
npm install --save-dev webpack-bundle-analyzer
npx vite-bundle-analyzer dist
`

**Image Optimization:**
- WebP format cho modern browsers
- Responsive images với srcset
- Lazy loading cho below-the-fold images

### 9.2 Security Measures

**Content Security Policy:**
- Nonce injection cho inline scripts/styles
- Strict CSP headers từ server
- Fallback meta tag cho development

**Input Validation:**
- Zod schemas cho tất cả API responses
- Client-side validation với error messages
- Server-side validation as final defense

**Authentication Security:**
- HttpOnly cookies (XSS protection)
- CSRF tokens cho state-changing requests
- Automatic token refresh
- Secure logout (clear all sessions)

### 9.3 Accessibility (WCAG 2.1 AA)

**Semantic HTML:**
- Proper heading hierarchy (h1  h6)
- ARIA labels cho interactive elements
- Focus management cho modals

**Keyboard Navigation:**
- Tab order logic
- Skip links cho screen readers
- Focus indicators

**Color Contrast:**
- Minimum 4.5:1 ratio cho text
- Focus states với sufficient contrast
- Color-blind friendly color schemes

---

## 10. Deployment & DevOps

### 10.1 Docker Configuration

**Dockerfile:**
`dockerfile
FROM node:18-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --only=production

FROM nginx:alpine
COPY --from=builder /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/nginx.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
`

**docker-compose.yml:**
`yaml
version: '3.8'
services:
  frontend:
    build: .
    ports:
      - "80:80"
    environment:
      - VITE_API_URL=https://api.soleil-hostel.com
    depends_on:
      - backend
`

### 10.2 CI/CD Pipeline

**.github/workflows/deploy.yml:**
`yaml
name: Deploy Frontend
on:
  push:
    branches: [ main ]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm ci
      - run: npm run build
      - run: npm run test
      - uses: aws-actions/configure-aws-credentials@v2
      - run: aws s3 sync dist/ s3://soleil-hostel-frontend --delete
`

### 10.3 Environment Management

**Environment Variables:**
`ash
# .env.local (development)
VITE_API_URL=http://localhost:8000/api
VITE_APP_ENV=development

# .env.production
VITE_API_URL=https://api.soleil-hostel.com
VITE_APP_ENV=production
VITE_ANALYTICS_ID=GA_MEASUREMENT_ID
`

**Build Scripts:**
`json
{
  "scripts": {
    "build:dev": "vite build --mode development",
    "build:staging": "vite build --mode staging",
    "build:prod": "vite build --mode production",
    "preview": "vite preview"
  }
}
`

---

## Tổng kết

Frontend của **Soleil Hostel** được xây dựng với kiến trúc hiện đại, bảo mật cao và performance tối ưu:

- **Feature-Sliced Architecture** cho scalability
- **TypeScript + React 19** cho type safety và performance
- **HttpOnly Authentication** cho security
- **CSP Protection** chống XSS attacks
- **Code Splitting** và lazy loading cho performance
- **Comprehensive Testing** với Playwright E2E
- **Production Ready** với Docker và CI/CD

Tất cả components đều được document chi tiết với code examples, API patterns, và best practices để đảm bảo maintainability và scalability lâu dài.
