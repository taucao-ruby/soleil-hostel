# Features Layer (`src/features/`)

> Lớp tính năng - logic nghiệp vụ theo feature với UI, API và types riêng biệt

## Tổng quan

Features Layer tổ chức code theo business features:

```
src/features/
├── auth/           # Authentication feature
│   ├── AuthContext.tsx
│   ├── LoginPage.tsx
│   ├── RegisterPage.tsx
│   ├── ProtectedRoute.tsx
│   └── api.ts
├── booking/        # Booking management
│   ├── BookingForm.tsx
│   ├── BookingList.tsx
│   └── api.ts
└── rooms/          # Room listing
    ├── RoomList.tsx
    ├── RoomCard.tsx
    └── api.ts
```

Mỗi feature có:

- **UI Components**: Pages và components cho feature đó
- **API Layer**: Service functions để gọi backend
- **Types**: Feature-specific types (nếu cần)
- **Logic**: Business logic riêng cho feature

## 1. Authentication Feature (`features/auth/`)

### AuthContext.tsx

```typescript
// features/auth/AuthContext.tsx
import React, { createContext, useContext, useState, useEffect } from "react";
import { authService } from "@/services/auth";
import { User } from "@/types/api";

interface AuthContextType {
  user: User | null;
  login: (credentials: { email: string; password: string }) => Promise<void>;
  logout: () => Promise<void>;
  isAuthenticated: boolean;
  isLoading: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // Check auth status on mount
  useEffect(() => {
    const checkAuth = async () => {
      try {
        const authData = await authService.checkAuth();
        setUser(authData.user);
      } catch {
        setUser(null);
      } finally {
        setIsLoading(false);
      }
    };

    checkAuth();
  }, []);

  const login = async (credentials: { email: string; password: string }) => {
    const authData = await authService.loginHttpOnly(credentials);
    setUser(authData.user);
  };

  const logout = async () => {
    await authService.logout();
    setUser(null);
  };

  const value: AuthContextType = {
    user,
    login,
    logout,
    isAuthenticated: !!user,
    isLoading,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
};
```

### LoginPage.tsx

```typescript
// features/auth/LoginPage.tsx
import React, { useState, FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "./AuthContext";
import { toast } from "react-hot-toast";
import Button from "@/shared/components/ui/Button";
import Input from "@/shared/components/ui/Input";

const LoginPage: React.FC = () => {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    email: "",
    password: "",
  });
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    try {
      await login(formData);
      toast.success("Login successful!");
      navigate("/dashboard");
    } catch (error) {
      toast.error("Login failed. Please check your credentials.");
      console.error("Login error:", error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData((prev) => ({
      ...prev,
      [e.target.name]: e.target.value,
    }));
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
            Sign in to your account
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            Or{" "}
            <Link
              to="/register"
              className="font-medium text-blue-600 hover:text-blue-500"
            >
              create a new account
            </Link>
          </p>
        </div>

        <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
          <div className="space-y-4">
            <Input
              label="Email address"
              name="email"
              type="email"
              autoComplete="email"
              required
              value={formData.email}
              onChange={handleChange}
              placeholder="Enter your email"
            />

            <Input
              label="Password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              value={formData.password}
              onChange={handleChange}
              placeholder="Enter your password"
            />
          </div>

          <div>
            <Button
              type="submit"
              className="w-full"
              loading={isLoading}
              disabled={isLoading}
            >
              {isLoading ? "Signing in..." : "Sign in"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default LoginPage;
```

### RegisterPage.tsx

```typescript
// features/auth/RegisterPage.tsx
import React, { useState, FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import { authService } from "@/services/auth";
import { toast } from "react-hot-toast";
import { z } from "zod";
import Button from "@/shared/components/ui/Button";
import Input from "@/shared/components/ui/Input";

const registerSchema = z
  .object({
    name: z.string().min(2, "Name must be at least 2 characters"),
    email: z.string().email("Invalid email address"),
    password: z.string().min(8, "Password must be at least 8 characters"),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
  });

const RegisterPage: React.FC = () => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setErrors({});

    try {
      const validData = registerSchema.parse(formData);
      await authService.register(validData);
      toast.success("Registration successful! Please log in.");
      navigate("/login");
    } catch (error) {
      if (error instanceof z.ZodError) {
        const fieldErrors: Record<string, string> = {};
        error.errors.forEach((err) => {
          const field = err.path.join(".");
          fieldErrors[field] = err.message;
        });
        setErrors(fieldErrors);
      } else {
        toast.error("Registration failed. Please try again.");
      }
    } finally {
      setIsLoading(false);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData((prev) => ({
      ...prev,
      [e.target.name]: e.target.value,
    }));
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
            Create your account
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            Or{" "}
            <Link
              to="/login"
              className="font-medium text-blue-600 hover:text-blue-500"
            >
              sign in to existing account
            </Link>
          </p>
        </div>

        <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
          <div className="space-y-4">
            <Input
              label="Full Name"
              name="name"
              type="text"
              autoComplete="name"
              required
              value={formData.name}
              onChange={handleChange}
              error={errors.name}
              placeholder="Enter your full name"
            />

            <Input
              label="Email address"
              name="email"
              type="email"
              autoComplete="email"
              required
              value={formData.email}
              onChange={handleChange}
              error={errors.email}
              placeholder="Enter your email"
            />

            <Input
              label="Password"
              name="password"
              type="password"
              autoComplete="new-password"
              required
              value={formData.password}
              onChange={handleChange}
              error={errors.password}
              placeholder="Create a password"
            />

            <Input
              label="Confirm Password"
              name="password_confirmation"
              type="password"
              autoComplete="new-password"
              required
              value={formData.password_confirmation}
              onChange={handleChange}
              error={errors.password_confirmation}
              placeholder="Confirm your password"
            />
          </div>

          <div>
            <Button
              type="submit"
              className="w-full"
              loading={isLoading}
              disabled={isLoading}
            >
              {isLoading ? "Creating account..." : "Create account"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default RegisterPage;
```

### ProtectedRoute.tsx

```typescript
// features/auth/ProtectedRoute.tsx
import React from "react";
import { Navigate, useLocation } from "react-router-dom";
import { useAuth } from "./AuthContext";
import LoadingSpinner from "@/shared/components/feedback/LoadingSpinner";

interface ProtectedRouteProps {
  children: React.ReactNode;
  redirectTo?: string;
  requireAuth?: boolean;
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  redirectTo = "/login",
  requireAuth = true,
}) => {
  const { isAuthenticated, isLoading } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return <LoadingSpinner fullScreen />;
  }

  if (requireAuth && !isAuthenticated) {
    // Redirect to login with return url
    return <Navigate to={redirectTo} state={{ from: location }} replace />;
  }

  if (!requireAuth && isAuthenticated) {
    // Redirect authenticated users away from auth pages
    const from = location.state?.from?.pathname || "/dashboard";
    return <Navigate to={from} replace />;
  }

  return <>{children}</>;
};

export default ProtectedRoute;
```

## 2. Booking Feature (`features/booking/`)

### BookingForm.tsx

```typescript
// features/booking/BookingForm.tsx
import React, { useState, FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/features/auth/AuthContext";
import { bookingApi } from "./api";
import { toast } from "react-hot-toast";
import { z } from "zod";
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import Button from "@/shared/components/ui/Button";
import Input from "@/shared/components/ui/Input";

interface BookingFormData {
  room_id: number;
  guest_name: string;
  guest_email: string;
  check_in: Date;
  check_out: Date;
  guests: number;
}

const bookingSchema = z
  .object({
    room_id: z.number().min(1, "Please select a room"),
    guest_name: z.string().min(2, "Name must be at least 2 characters"),
    guest_email: z.string().email("Invalid email address"),
    check_in: z.date(),
    check_out: z.date(),
    guests: z.number().min(1).max(10),
  })
  .refine((data) => data.check_out > data.check_in, {
    message: "Check-out must be after check-in",
    path: ["check_out"],
  });

const BookingForm: React.FC = () => {
  const { user } = useAuth();
  const navigate = useNavigate();

  const [formData, setFormData] = useState<BookingFormData>({
    room_id: 0,
    guest_name: user?.name || "",
    guest_email: user?.email || "",
    check_in: new Date(),
    check_out: new Date(Date.now() + 86400000), // +1 day
    guests: 1,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setErrors({});

    try {
      const validData = bookingSchema.parse(formData);
      const response = await bookingApi.create(validData);
      toast.success("Booking created successfully!");
      navigate(`/booking/${response.data.id}`);
    } catch (error) {
      if (error instanceof z.ZodError) {
        const fieldErrors: Record<string, string> = {};
        error.errors.forEach((err) => {
          const field = err.path.join(".");
          fieldErrors[field] = err.message;
        });
        setErrors(fieldErrors);
      } else {
        toast.error("Failed to create booking");
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto py-8 px-4">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Create Booking</h1>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Room selection */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Select Room
          </label>
          <select
            value={formData.room_id}
            onChange={(e) =>
              setFormData((prev) => ({
                ...prev,
                room_id: parseInt(e.target.value),
              }))
            }
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value={0}>Choose a room...</option>
            {/* Room options would be populated from API */}
          </select>
          {errors.room_id && (
            <p className="mt-1 text-sm text-red-600">{errors.room_id}</p>
          )}
        </div>

        {/* Guest information */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Input
            label="Guest Name"
            name="guest_name"
            type="text"
            required
            value={formData.guest_name}
            onChange={(e) =>
              setFormData((prev) => ({
                ...prev,
                guest_name: e.target.value,
              }))
            }
            error={errors.guest_name}
            placeholder="Enter guest name"
          />

          <Input
            label="Guest Email"
            name="guest_email"
            type="email"
            required
            value={formData.guest_email}
            onChange={(e) =>
              setFormData((prev) => ({
                ...prev,
                guest_email: e.target.value,
              }))
            }
            error={errors.guest_email}
            placeholder="Enter guest email"
          />
        </div>

        {/* Date selection */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Check-in Date
            </label>
            <DatePicker
              selected={formData.check_in}
              onChange={(date) =>
                date &&
                setFormData((prev) => ({
                  ...prev,
                  check_in: date,
                }))
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              minDate={new Date()}
              dateFormat="yyyy-MM-dd"
            />
            {errors.check_in && (
              <p className="mt-1 text-sm text-red-600">{errors.check_in}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Check-out Date
            </label>
            <DatePicker
              selected={formData.check_out}
              onChange={(date) =>
                date &&
                setFormData((prev) => ({
                  ...prev,
                  check_out: date,
                }))
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              minDate={formData.check_in}
              dateFormat="yyyy-MM-dd"
            />
            {errors.check_out && (
              <p className="mt-1 text-sm text-red-600">{errors.check_out}</p>
            )}
          </div>
        </div>

        {/* Number of guests */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Number of Guests
          </label>
          <select
            value={formData.guests}
            onChange={(e) =>
              setFormData((prev) => ({
                ...prev,
                guests: parseInt(e.target.value),
              }))
            }
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((num) => (
              <option key={num} value={num}>
                {num}
              </option>
            ))}
          </select>
        </div>

        {/* Submit button */}
        <Button
          type="submit"
          className="w-full"
          loading={isLoading}
          disabled={isLoading}
        >
          {isLoading ? "Creating Booking..." : "Create Booking"}
        </Button>
      </form>
    </div>
  );
};

export default BookingForm;
```

### Booking API Service

```typescript
// features/booking/api.ts
import api from "@/services/api";
import {
  Booking,
  BookingCreate,
  BookingResponse,
  BookingsResponse,
} from "@/types/api";

export const bookingApi = {
  // Tạo booking mới
  create: async (data: BookingCreate): Promise<BookingResponse> => {
    const response = await api.post("/bookings", data);
    const result = validateApiResponse(BookingResponseSchema, response.data);
    return result;
  },

  // Lấy danh sách bookings
  getAll: async (): Promise<Booking[]> => {
    const response = await api.get("/bookings");
    const result = validateApiResponse(BookingsResponseSchema, response.data);
    return result.data;
  },

  // Lấy booking theo ID
  getById: async (id: number): Promise<Booking> => {
    const response = await api.get(`/bookings/${id}`);
    const result = validateApiResponse(BookingResponseSchema, response.data);
    return result.data;
  },

  // Cập nhật booking
  update: async (
    id: number,
    data: Partial<BookingCreate>
  ): Promise<Booking> => {
    const response = await api.put(`/bookings/${id}`, data);
    const result = validateApiResponse(BookingResponseSchema, response.data);
    return result.data;
  },

  // Hủy booking
  cancel: async (id: number): Promise<void> => {
    await api.delete(`/bookings/${id}`);
  },
};
```

## 3. Rooms Feature (`features/rooms/`)

### RoomList.tsx

```typescript
// features/rooms/RoomList.tsx
import React, { useEffect, useState } from "react";
import { roomsApi } from "./api";
import { Room } from "@/types/api";
import RoomCard from "./RoomCard";
import LoadingSpinner from "@/shared/components/feedback/LoadingSpinner";

const RoomList: React.FC = () => {
  const [rooms, setRooms] = useState<Room[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchRooms = async () => {
      try {
        const data = await roomsApi.getAll();
        setRooms(data);
      } catch (err) {
        setError("Failed to load rooms");
        console.error("Room fetch error:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchRooms();
  }, []);

  if (loading) {
    return <LoadingSpinner size="xl" fullScreen />;
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
    );
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
          {rooms.map((room) => (
            <RoomCard key={room.id} room={room} />
          ))}
        </div>
      )}
    </div>
  );
};

export default RoomList;
```

### RoomCard.tsx

```typescript
// features/rooms/RoomCard.tsx
import React from "react";
import { Link } from "react-router-dom";
import { Room } from "@/types/api";

interface RoomCardProps {
  room: Room;
}

const RoomCard: React.FC<RoomCardProps> = ({ room }) => {
  const getStatusColor = (status: Room["status"]) => {
    switch (status) {
      case "available":
        return "bg-green-100 text-green-800";
      case "booked":
        return "bg-red-100 text-red-800";
      case "maintenance":
        return "bg-yellow-100 text-yellow-800";
      default:
        return "bg-gray-100 text-gray-800";
    }
  };

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
          <span
            className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(
              room.status
            )}`}
          >
            {room.status.charAt(0).toUpperCase() + room.status.slice(1)}
          </span>
        </div>

        {/* Room Details */}
        <div className="space-y-2 mb-4">
          <p className="text-gray-600">
            <span className="font-medium">Price:</span> ${room.price}/night
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
          to={`/booking?room=${room.id}`}
          className={`w-full block text-center py-2 px-4 rounded-md font-medium transition-colors ${
            room.status === "available"
              ? "bg-blue-600 text-white hover:bg-blue-700"
              : "bg-gray-300 text-gray-500 cursor-not-allowed"
          }`}
          onClick={(e) => {
            if (room.status !== "available") {
              e.preventDefault();
            }
          }}
        >
          {room.status === "available" ? "Book Now" : "Not Available"}
        </Link>
      </div>
    </div>
  );
};

export default RoomCard;
```

### Rooms API Service

```typescript
// features/rooms/api.ts
import api from "@/services/api";
import { Room, RoomsResponse, RoomResponse } from "@/types/api";

export const roomsApi = {
  // Lấy tất cả rooms
  getAll: async (): Promise<Room[]> => {
    const response = await api.get("/rooms");
    const result = validateApiResponse(RoomsResponseSchema, response.data);
    return result.data;
  },

  // Lấy room theo ID
  getById: async (id: number): Promise<Room> => {
    const response = await api.get(`/rooms/${id}`);
    const result = validateApiResponse(RoomResponseSchema, response.data);
    return result.data;
  },

  // Lấy rooms theo status
  getByStatus: async (status: Room["status"]): Promise<Room[]> => {
    const response = await api.get(`/rooms?status=${status}`);
    const result = validateApiResponse(RoomsResponseSchema, response.data);
    return result.data;
  },

  // Tìm kiếm rooms
  search: async (query: string): Promise<Room[]> => {
    const response = await api.get(
      `/rooms/search?q=${encodeURIComponent(query)}`
    );
    const result = validateApiResponse(RoomsResponseSchema, response.data);
    return result.data;
  },
};
```

## Best Practices cho Features

### 1. Feature Isolation

- Mỗi feature có API riêng
- State management local cho feature
- Types specific cho feature

### 2. Error Handling

- Feature-level error boundaries
- User-friendly error messages
- Proper loading states

### 3. Performance

- Lazy loading cho feature components
- Memoization cho expensive operations
- Efficient re-renders

### 4. Testing

- Unit tests cho API functions
- Integration tests cho components
- E2E tests cho user flows

### 5. Code Organization

- Clear separation of concerns
- Consistent naming conventions
- Documentation cho complex logic
