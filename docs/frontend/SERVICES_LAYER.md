# Services Layer (`src/services/`)

> Lớp dịch vụ - API client, authentication service và business logic

## Tổng quan

Services Layer chứa các dịch vụ cốt lõi:

```
src/services/
├── api.ts          # Axios configuration với interceptors
├── auth.ts         # Authentication service
└── index.ts        # Service exports
```

Services handle:

- **HTTP Communication**: API calls với error handling
- **Authentication**: Login/logout với HttpOnly cookies
- **Data Transformation**: API response validation
- **Error Handling**: Centralized error management

## 1. API Service (`services/api.ts`)

### Axios Configuration

```typescript
// services/api.ts
import axios, { AxiosResponse, AxiosError } from "axios";
import { validateApiResponse } from "@/shared/lib/api";
import { ApiError } from "@/types/api";

const BASE_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

const api = axios.create({
  baseURL: BASE_URL,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
  withCredentials: true, // CRITICAL: Cho phép gửi httpOnly cookies
  timeout: 30000,
});
```

### Request Interceptor - CSRF Protection

```typescript
// Thêm X-XSRF-TOKEN header cho requests non-GET
api.interceptors.request.use((config) => {
  // Chỉ thêm CSRF token cho state-changing requests
  if (
    config.method &&
    ["post", "put", "patch", "delete"].includes(config.method.toLowerCase())
  ) {
    const csrfToken = sessionStorage.getItem("csrf_token");
    if (csrfToken) {
      config.headers["X-XSRF-TOKEN"] = csrfToken;
    }
  }

  // Add request ID cho tracking
  config.headers["X-Request-ID"] = generateRequestId();

  return config;
});
```

### Response Interceptor - Auto Token Refresh

```typescript
api.interceptors.response.use(
  (response: AxiosResponse) => {
    // Validate response format
    return response;
  },
  async (error: AxiosError) => {
    const originalRequest = error.config;

    if (!originalRequest) {
      return Promise.reject(error);
    }

    // Handle 401 Unauthorized - attempt token refresh
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;

      try {
        // Attempt to refresh token
        const refreshResponse = await api.post("/auth/refresh-httponly");

        // Update CSRF token from refresh response
        if (refreshResponse.data.csrf_token) {
          sessionStorage.setItem("csrf_token", refreshResponse.data.csrf_token);
        }

        // Retry original request với new token
        return api(originalRequest);
      } catch (refreshError) {
        // Refresh failed - redirect to login
        sessionStorage.clear();
        localStorage.clear();
        window.location.href = "/login";
        return Promise.reject(refreshError);
      }
    }

    // Handle other errors
    if (error.response?.status >= 500) {
      // Server error - show generic message
      console.error("Server Error:", error.response.data);
    }

    return Promise.reject(error);
  }
);
```

### Helper Functions

```typescript
// Generate unique request ID
function generateRequestId(): string {
  return `req_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
}

// Generic API call wrapper với validation
export async function apiCall<T>(
  method: "get" | "post" | "put" | "delete",
  url: string,
  data?: any,
  schema?: z.ZodSchema<T>
): Promise<T> {
  try {
    const response = await api.request({
      method,
      url,
      data,
    });

    if (schema) {
      return validateApiResponse(schema, response.data);
    }

    return response.data;
  } catch (error) {
    // Transform error to consistent format
    if (error instanceof AxiosError) {
      throw new ApiError(
        error.response?.data?.message || error.message,
        error.response?.status || 500,
        error.response?.data?.errors
      );
    }
    throw error;
  }
}
```

## 2. Auth Service (`services/auth.ts`)

### Authentication Service Implementation

```typescript
// services/auth.ts
import api from "./api";
import { validateApiResponse } from "@/shared/lib/api";
import { AuthResponseSchema, User } from "@/types/api";

export interface LoginCredentials {
  email: string;
  password: string;
  remember_me?: boolean;
}

export interface RegisterData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export const authService = {
  // Login với httpOnly cookie
  async loginHttpOnly(credentials: LoginCredentials): Promise<{
    user: User;
    csrf_token: string;
  }> {
    const response = await api.post("/auth/login-httponly", credentials);
    const result = validateApiResponse(AuthResponseSchema, response.data);

    // Lưu CSRF token vào sessionStorage
    if (result.csrf_token) {
      sessionStorage.setItem("csrf_token", result.csrf_token);
    }

    return {
      user: result.user,
      csrf_token: result.csrf_token,
    };
  },

  // Register new user
  async register(userData: RegisterData): Promise<{
    user: User;
    csrf_token: string;
  }> {
    const response = await api.post("/auth/register", userData);
    const result = validateApiResponse(AuthResponseSchema, response.data);

    if (result.csrf_token) {
      sessionStorage.setItem("csrf_token", result.csrf_token);
    }

    return {
      user: result.user,
      csrf_token: result.csrf_token,
    };
  },

  // Logout - clear session
  async logout(): Promise<void> {
    try {
      await api.post("/auth/logout");
    } catch (error) {
      // Even if logout fails, clear local session
      console.warn("Logout API call failed:", error);
    } finally {
      // Always clear local session
      sessionStorage.removeItem("csrf_token");
    }
  },

  // Check authentication status
  async checkAuth(): Promise<{
    user: User;
    csrf_token: string;
  } | null> {
    try {
      const response = await api.get("/auth/user");
      const result = validateApiResponse(AuthResponseSchema, response.data);

      if (result.csrf_token) {
        sessionStorage.setItem("csrf_token", result.csrf_token);
      }

      return {
        user: result.user,
        csrf_token: result.csrf_token,
      };
    } catch (error) {
      // User is not authenticated
      sessionStorage.removeItem("csrf_token");
      return null;
    }
  },

  // Refresh authentication token
  async refreshToken(): Promise<{
    user: User;
    csrf_token: string;
  }> {
    const response = await api.post("/auth/refresh-httponly");
    const result = validateApiResponse(AuthResponseSchema, response.data);

    if (result.csrf_token) {
      sessionStorage.setItem("csrf_token", result.csrf_token);
    }

    return {
      user: result.user,
      csrf_token: result.csrf_token,
    };
  },

  // Get current CSRF token
  getCsrfToken(): string | null {
    return sessionStorage.getItem("csrf_token");
  },

  // Check if user is authenticated (client-side check)
  isAuthenticated(): boolean {
    return !!this.getCsrfToken();
  },
};
```

### Security Implementation Details

#### HttpOnly Cookies

- **Server sets**: `Set-Cookie: session=abc123; HttpOnly; Secure; SameSite=Strict`
- **Client cannot read**: JavaScript không thể access cookie
- **Automatic sending**: Browser tự động gửi với mỗi request
- **XSS Protection**: Attackers không thể steal session

#### CSRF Protection

- **Token generation**: Server tạo token cho mỗi session
- **Client storage**: sessionStorage (cleared on tab close)
- **Request headers**: `X-XSRF-TOKEN` cho state-changing requests
- **Validation**: Server verify token trước khi process

#### Auto Token Refresh Flow

```
1. User makes API call
2. Request includes httpOnly cookie
3. If 401, intercept and call /auth/refresh-httponly
4. Server validates refresh token, issues new access token
5. Client receives new CSRF token
6. Retry original request với new token
7. If refresh fails, redirect to login
```

## 3. Service Architecture Patterns

### Error Handling Strategy

```typescript
// services/errorHandler.ts
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export const handleApiError = (error: unknown): string => {
  if (error instanceof ApiError) {
    if (error.errors) {
      // Field-specific errors
      return Object.values(error.errors).flat().join(", ");
    }
    return error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return "An unexpected error occurred";
};
```

### Request/Response Validation

```typescript
// services/validation.ts
import { z } from "zod";

export const withValidation = <TInput, TOutput>(
  schema: z.ZodSchema<TInput>,
  fn: (input: TInput) => Promise<TOutput>
) => {
  return async (input: unknown): Promise<TOutput> => {
    const validInput = schema.parse(input);
    return fn(validInput);
  };
};

// Usage in services
export const createBooking = withValidation(
  BookingCreateSchema,
  async (data: BookingCreate) => {
    return apiCall("post", "/bookings", data, BookingResponseSchema);
  }
);
```

### Caching Strategy

```typescript
// services/cache.ts
interface CacheEntry<T> {
  data: T;
  timestamp: number;
  ttl: number;
}

export class ApiCache {
  private cache = new Map<string, CacheEntry<any>>();

  get<T>(key: string): T | null {
    const entry = this.cache.get(key);
    if (!entry) return null;

    if (Date.now() - entry.timestamp > entry.ttl) {
      this.cache.delete(key);
      return null;
    }

    return entry.data;
  }

  set<T>(key: string, data: T, ttl = 5 * 60 * 1000): void {
    this.cache.set(key, {
      data,
      timestamp: Date.now(),
      ttl,
    });
  }

  clear(): void {
    this.cache.clear();
  }
}

export const apiCache = new ApiCache();
```

## 4. Testing Services

### Unit Tests cho API Service

```typescript
// services/__tests__/api.test.ts
import { apiCall } from "../api";
import { ApiError } from "../errorHandler";

describe("API Service", () => {
  it("should handle successful requests", async () => {
    // Mock axios
    const mockResponse = { data: { id: 1, name: "Test" } };

    // Test apiCall
    const result = await apiCall("get", "/test");
    expect(result).toEqual(mockResponse.data);
  });

  it("should handle API errors", async () => {
    // Mock failed request
    expect(async () => {
      await apiCall("get", "/error");
    }).rejects.toThrow(ApiError);
  });
});
```

### Integration Tests cho Auth Service

```typescript
// services/__tests__/auth.test.ts
describe("Auth Service", () => {
  it("should login successfully", async () => {
    const credentials = { email: "test@example.com", password: "password" };
    const result = await authService.loginHttpOnly(credentials);

    expect(result.user).toBeDefined();
    expect(result.csrf_token).toBeDefined();
    expect(sessionStorage.getItem("csrf_token")).toBe(result.csrf_token);
  });

  it("should handle login failure", async () => {
    const credentials = { email: "wrong@email.com", password: "wrong" };

    await expect(authService.loginHttpOnly(credentials)).rejects.toThrow();
  });
});
```

## Best Practices

### 1. Security First

- Always use HTTPS in production
- Validate all inputs server-side
- Implement rate limiting
- Log security events

### 2. Error Handling

- Consistent error format across API
- User-friendly error messages
- Proper HTTP status codes
- Comprehensive logging

### 3. Performance

- Implement caching where appropriate
- Use compression (gzip)
- Optimize bundle size
- Monitor API response times

### 4. Maintainability

- Clear service separation
- Comprehensive TypeScript types
- Good documentation
- Unit test coverage

### 5. Monitoring

- API response time monitoring
- Error rate tracking
- User authentication metrics
- Security incident alerts
