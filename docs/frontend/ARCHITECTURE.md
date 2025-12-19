# 🖥️ Frontend Architecture

> React 19 + TypeScript + Vite frontend documentation

## Overview

| Technology  | Version | Purpose                 |
| ----------- | ------- | ----------------------- |
| React       | 19      | UI framework            |
| TypeScript  | 5.x     | Type safety             |
| Vite        | 6.x     | Build tool & dev server |
| TailwindCSS | 4.x     | Styling                 |
| Axios       | 1.x     | HTTP client             |
| React Query | 5.x     | Server state management |

---

## Project Structure

```
frontend/src/
├── app/                    # App configuration
├── assets/                 # Static assets
├── features/               # Feature modules
│   ├── auth/               # Authentication
│   ├── booking/            # Booking management
│   └── rooms/              # Room listing
├── lib/                    # Core utilities
│   └── api.ts              # Axios client with auto-refresh
├── pages/                  # Page components
├── services/               # API service layer
│   ├── api.ts              # Base API client
│   └── auth.ts             # Auth service
├── shared/                 # Shared components
├── types/                  # TypeScript types
├── utils/                  # Utility functions
└── main.tsx                # Entry point
```

---

## Authentication

### HttpOnly Cookie Flow (Recommended)

```typescript
// services/auth.ts

class AuthService {
  async loginHttpOnly(payload: LoginPayload): Promise<HttpOnlyAuthResponse> {
    const response = await api.post("/auth/login-httponly", {
      email: payload.email,
      password: payload.password,
      remember_me: payload.remember_me ?? false,
    });

    // Save CSRF token for X-XSRF-TOKEN header
    if (response.data.csrf_token) {
      setCsrfToken(response.data.csrf_token);
    }

    // Token automatically stored in httpOnly cookie by browser
    // JavaScript CANNOT access it (XSS protection)
    return response.data;
  }
}
```

### Security Benefits

| Storage Method  | XSS Vulnerable | CSRF Vulnerable    |
| --------------- | -------------- | ------------------ |
| localStorage    | ✅ Yes         | ❌ No              |
| sessionStorage  | ✅ Yes         | ❌ No              |
| httpOnly Cookie | ❌ No          | ✅ Yes (mitigated) |

---

## API Client

### Auto Token Refresh

```typescript
// lib/api.ts

const apiClient = axios.create({
  baseURL: process.env.REACT_APP_API_URL || "http://localhost:8000",
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
  },
});

// Request interceptor - add Authorization header
apiClient.interceptors.request.use((config) => {
  const token = sessionStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor - handle 401 & auto-refresh
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401 && !isRefreshing) {
      isRefreshing = true;

      try {
        const { data } = await axios.post("/api/auth/refresh");
        sessionStorage.setItem("token", data.token);

        // Retry failed request with new token
        error.config.headers.Authorization = `Bearer ${data.token}`;
        return apiClient(error.config);
      } catch {
        // Refresh failed - redirect to login
        window.location.href = "/login";
      } finally {
        isRefreshing = false;
      }
    }
    return Promise.reject(error);
  }
);
```

### Request Queue Pattern

```typescript
// Prevent multiple refresh calls
let failedQueue: QueueItem[] = [];

const processQueue = (token: string | null) => {
  failedQueue.forEach((prom) => {
    if (token) {
      prom.onSuccess(token);
    } else {
      prom.onFailure(new Error("Refresh failed"));
    }
  });
  failedQueue = [];
};
```

---

## Optimistic Locking (Room Updates)

```typescript
// Handle 409 Conflict
async function updateRoom(data: RoomUpdate) {
  try {
    await api.put(`/rooms/${data.id}`, {
      ...data,
      lock_version: data.lock_version, // Required!
    });
  } catch (error) {
    if (error.response?.status === 409) {
      // Resource was modified by another user
      const freshData = await api.get(`/rooms/${data.id}`);
      showNotification("Room was modified. Please review changes.");
      return freshData;
    }
    throw error;
  }
}
```

---

## CSRF Protection

```typescript
// utils/csrf.ts

export function setCsrfToken(token: string) {
  sessionStorage.setItem("csrf_token", token);
}

export function getCsrfToken(): string | null {
  return sessionStorage.getItem("csrf_token");
}

// Add to requests
api.interceptors.request.use((config) => {
  const csrfToken = getCsrfToken();
  if (csrfToken) {
    config.headers["X-XSRF-TOKEN"] = csrfToken;
  }
  return config;
});
```

---

## Type Definitions

```typescript
// types/auth.ts

export interface HttpOnlyAuthResponse {
  success: boolean;
  message: string;
  user: {
    id: number;
    name: string;
    email: string;
  };
  csrf_token: string;
  expires_in_minutes: number;
  expires_at: string;
  token_type: "short_lived" | "long_lived";
}

// types/booking.ts

export interface Booking {
  id: number;
  room_id: number;
  check_in: string;
  check_out: string;
  guest_name: string;
  status: "pending" | "confirmed" | "cancelled";
  room?: Room;
}

// types/room.ts

export interface Room {
  id: number;
  name: string;
  price: number;
  max_guests: number;
  status: "available" | "occupied" | "maintenance";
  lock_version: number; // For optimistic locking
}
```

---

## Development Commands

```bash
cd frontend

# Install dependencies
npm install

# Start dev server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview

# Run E2E tests
npm run test:e2e
```

---

## Environment Variables

```bash
# .env
VITE_API_URL=http://localhost:8000
VITE_APP_NAME=Soleil Hostel
```

```typescript
// Usage
const apiUrl = import.meta.env.VITE_API_URL;
```
