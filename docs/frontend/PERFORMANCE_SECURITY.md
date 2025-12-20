# Performance & Security Best Practices

> Performance optimization và security hardening cho production deployment

## Tổng quan

Performance và security là hai trụ cột quan trọng cho production-ready application. Document này cover các best practices, optimization techniques, và security measures được implement trong Soleil Hostel frontend.

## 1. Performance Optimization

### Bundle Size Optimization

#### Code Splitting Strategy

```typescript
// src/app/router.tsx - Lazy loading cho routes
import { lazy, Suspense } from "react";
import { createBrowserRouter } from "react-router-dom";

const HomePage = lazy(() => import("../pages/HomePage"));
const RoomsPage = lazy(() => import("../features/rooms/pages/RoomsPage"));
const BookingPage = lazy(() => import("../features/booking/pages/BookingPage"));
const LoginPage = lazy(() => import("../features/auth/pages/LoginPage"));
const DashboardPage = lazy(
  () => import("../features/auth/pages/DashboardPage")
);

export const router = createBrowserRouter([
  {
    path: "/",
    element: (
      <Suspense fallback={<div>Loading...</div>}>
        <HomePage />
      </Suspense>
    ),
  },
  {
    path: "/rooms",
    element: (
      <Suspense fallback={<div>Loading...</div>}>
        <RoomsPage />
      </Suspense>
    ),
  },
  // ... other routes
]);
```

#### Dynamic Imports cho Heavy Components

```typescript
// src/features/booking/components/BookingCalendar.tsx
import { useState, lazy, Suspense } from "react";

const DatePicker = lazy(() => import("react-datepicker"));

export function BookingCalendar() {
  const [showCalendar, setShowCalendar] = useState(false);

  return (
    <div>
      <button onClick={() => setShowCalendar(true)}>Select Dates</button>

      {showCalendar && (
        <Suspense fallback={<div>Loading calendar...</div>}>
          <DatePicker
            selected={selectedDate}
            onChange={setSelectedDate}
            inline
          />
        </Suspense>
      )}
    </div>
  );
}
```

#### Vendor Chunk Separation

```typescript
// vite.config.ts
export default defineConfig({
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          // React ecosystem
          "react-vendor": ["react", "react-dom", "react-router-dom"],

          // UI libraries
          "ui-vendor": ["framer-motion", "react-datepicker", "react-toastify"],

          // Utilities
          "utils-vendor": ["axios", "zod", "date-fns"],
        },
      },
    },
  },
});
```

### Image Optimization

#### Responsive Images với Modern Formats

```typescript
// src/shared/components/Image.tsx
import { useState } from "react";

interface OptimizedImageProps {
  src: string;
  alt: string;
  width?: number;
  height?: number;
  className?: string;
}

export function OptimizedImage({
  src,
  alt,
  width,
  height,
  className,
}: OptimizedImageProps) {
  const [isLoaded, setIsLoaded] = useState(false);
  const [hasError, setHasError] = useState(false);

  // Generate responsive image URLs
  const webpSrc = src.replace(/\.(jpg|jpeg|png)$/, ".webp");
  const avifSrc = src.replace(/\.(jpg|jpeg|png)$/, ".avif");

  return (
    <div className={`relative ${className}`}>
      {!isLoaded && !hasError && (
        <div className="absolute inset-0 bg-gray-200 animate-pulse rounded" />
      )}

      <picture>
        <source srcSet={avifSrc} type="image/avif" />
        <source srcSet={webpSrc} type="image/webp" />
        <img
          src={src}
          alt={alt}
          width={width}
          height={height}
          loading="lazy"
          decoding="async"
          onLoad={() => setIsLoaded(true)}
          onError={() => setHasError(true)}
          className={`transition-opacity duration-300 ${
            isLoaded ? "opacity-100" : "opacity-0"
          }`}
        />
      </picture>
    </div>
  );
}
```

#### Image CDN Integration

```typescript
// src/utils/cdn.ts
export const CDN_CONFIG = {
  baseUrl: process.env.VITE_CDN_URL || "",
  transforms: {
    thumbnail: "w_300,h_200,c_fill",
    medium: "w_800,h_600,c_fill",
    large: "w_1200,h_800,c_fill",
  },
};

export function getOptimizedImageUrl(
  imagePath: string,
  transform: keyof typeof CDN_CONFIG.transforms = "medium"
): string {
  if (!CDN_CONFIG.baseUrl) return imagePath;

  const transformParams = CDN_CONFIG.transforms[transform];
  return `${CDN_CONFIG.baseUrl}/${transformParams}/${imagePath}`;
}
```

### Caching Strategy

#### HTTP Caching Headers

```typescript
// src/services/cache.ts
export const CACHE_CONFIG = {
  // Static assets - cache aggressively
  static: {
    maxAge: 31536000, // 1 year
    immutable: true,
  },

  // API responses - cache briefly
  api: {
    maxAge: 300, // 5 minutes
    staleWhileRevalidate: 600, // 10 minutes
  },

  // User-specific data - no cache
  user: {
    maxAge: 0,
    noCache: true,
  },
};

export function getCacheHeaders(type: keyof typeof CACHE_CONFIG) {
  const config = CACHE_CONFIG[type];

  const headers: Record<string, string> = {
    "Cache-Control": `max-age=${config.maxAge}`,
  };

  if (config.immutable) {
    headers["Cache-Control"] += ", immutable";
  }

  if (config.staleWhileRevalidate) {
    headers[
      "Cache-Control"
    ] += `, stale-while-revalidate=${config.staleWhileRevalidate}`;
  }

  if (config.noCache) {
    headers["Cache-Control"] = "no-cache, no-store, must-revalidate";
  }

  return headers;
}
```

#### Service Worker cho Offline Support

```typescript
// public/sw.js
const CACHE_NAME = "soleil-hostel-v1";
const STATIC_CACHE = "soleil-static-v1";
const API_CACHE = "soleil-api-v1";

// Install event - cache static assets
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      return cache.addAll([
        "/",
        "/manifest.json",
        "/favicon.ico",
        // Add other critical assets
      ]);
    })
  );
});

// Fetch event - serve from cache when offline
self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Handle API requests
  if (url.pathname.startsWith("/api/")) {
    event.respondWith(
      caches.open(API_CACHE).then((cache) => {
        return fetch(request)
          .then((response) => {
            // Cache successful GET requests
            if (request.method === "GET" && response.status === 200) {
              cache.put(request, response.clone());
            }
            return response;
          })
          .catch(() => {
            // Return cached version if offline
            return cache.match(request);
          });
      })
    );
    return;
  }

  // Handle static assets
  event.respondWith(
    caches.match(request).then((response) => {
      return (
        response ||
        fetch(request).then((response) => {
          // Cache static assets
          if (response.status === 200 && request.destination === "document") {
            const responseClone = response.clone();
            caches.open(STATIC_CACHE).then((cache) => {
              cache.put(request, responseClone);
            });
          }
          return response;
        })
      );
    })
  );
});
```

### Web Vitals Monitoring

#### Core Web Vitals Tracking

```typescript
// src/utils/webVitals.ts
import { onCLS, onFID, onFCP, onLCP, onTTFB } from "web-vitals";

export interface WebVitalsMetrics {
  cls?: number;
  fid?: number;
  fcp?: number;
  lcp?: number;
  ttfb?: number;
}

class WebVitalsTracker {
  private metrics: WebVitalsMetrics = {};

  init() {
    // Cumulative Layout Shift
    onCLS((metric) => {
      this.metrics.cls = metric.value;
      this.reportMetric("CLS", metric);
    });

    // First Input Delay
    onFID((metric) => {
      this.metrics.fid = metric.value;
      this.reportMetric("FID", metric);
    });

    // First Contentful Paint
    onFCP((metric) => {
      this.metrics.fcp = metric.value;
      this.reportMetric("FCP", metric);
    });

    // Largest Contentful Paint
    onLCP((metric) => {
      this.metrics.lcp = metric.value;
      this.reportMetric("LCP", metric);
    });

    // Time to First Byte
    onTTFB((metric) => {
      this.metrics.ttfb = metric.value;
      this.reportMetric("TTFB", metric);
    });
  }

  private reportMetric(name: string, metric: any) {
    // Send to analytics service
    if (process.env.NODE_ENV === "production") {
      // Example: Send to Google Analytics
      gtag("event", "web_vitals", {
        event_category: "Web Vitals",
        event_label: name,
        value: Math.round(metric.value),
        custom_map: { metric_value: metric.value },
      });

      // Send to custom analytics endpoint
      fetch("/api/analytics/web-vitals", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name,
          value: metric.value,
          timestamp: Date.now(),
          userAgent: navigator.userAgent,
        }),
      }).catch(console.error);
    }

    // Log to console in development
    console.log(`Web Vital ${name}:`, metric.value);
  }

  getMetrics(): WebVitalsMetrics {
    return { ...this.metrics };
  }
}

export const webVitalsTracker = new WebVitalsTracker();
```

#### Performance Budget Monitoring

```typescript
// src/utils/performanceBudget.ts
export interface PerformanceBudget {
  lcp: number; // Largest Contentful Paint
  fid: number; // First Input Delay
  cls: number; // Cumulative Layout Shift
  fcp: number; // First Contentful Paint
  ttfb: number; // Time to First Byte
  bundleSize: number; // Bundle size in bytes
}

export const PERFORMANCE_BUDGET: PerformanceBudget = {
  lcp: 2500, // 2.5s
  fid: 100, // 100ms
  cls: 0.1, // 0.1
  fcp: 1800, // 1.8s
  ttfb: 800, // 800ms
  bundleSize: 500 * 1024, // 500KB
};

export function checkPerformanceBudget(
  metrics: Partial<PerformanceBudget>
): boolean {
  const violations: string[] = [];

  if (metrics.lcp && metrics.lcp > PERFORMANCE_BUDGET.lcp) {
    violations.push(`LCP: ${metrics.lcp}ms > ${PERFORMANCE_BUDGET.lcp}ms`);
  }

  if (metrics.fid && metrics.fid > PERFORMANCE_BUDGET.fid) {
    violations.push(`FID: ${metrics.fid}ms > ${PERFORMANCE_BUDGET.fid}ms`);
  }

  if (metrics.cls && metrics.cls > PERFORMANCE_BUDGET.cls) {
    violations.push(`CLS: ${metrics.cls} > ${PERFORMANCE_BUDGET.cls}`);
  }

  if (metrics.fcp && metrics.fcp > PERFORMANCE_BUDGET.fcp) {
    violations.push(`FCP: ${metrics.fcp}ms > ${PERFORMANCE_BUDGET.fcp}ms`);
  }

  if (metrics.ttfb && metrics.ttfb > PERFORMANCE_BUDGET.ttfb) {
    violations.push(`TTFB: ${metrics.ttfb}ms > ${PERFORMANCE_BUDGET.ttfb}ms`);
  }

  if (violations.length > 0) {
    console.warn("Performance budget violations:", violations);

    // Report to monitoring service
    if (process.env.NODE_ENV === "production") {
      fetch("/api/analytics/performance-violations", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ violations, metrics }),
      }).catch(console.error);
    }

    return false;
  }

  return true;
}
```

## 2. Security Hardening

### Content Security Policy (CSP)

#### Vite Plugin cho CSP Nonce Injection

```typescript
// vite-plugin-csp-nonce.js
export default function cspNoncePlugin() {
  let nonce = "";

  return {
    name: "csp-nonce",
    configureServer(server) {
      // Generate nonce for development
      server.middlewares.use((req, res, next) => {
        nonce = Math.random().toString(36).substring(2);
        res.locals.nonce = nonce;
        next();
      });
    },

    transformIndexHtml(html) {
      // Inject nonce into script and style tags
      return html.replace(
        /<(script|style)([^>]*)>/g,
        `<$1$2 nonce="${nonce}">`
      );
    },

    generateBundle(options, bundle) {
      // For production builds, add nonce placeholder
      for (const [fileName, chunk] of Object.entries(bundle)) {
        if (chunk.type === "chunk" && fileName.endsWith(".js")) {
          chunk.code = chunk.code.replace(
            /__CSP_NONCE__/g,
            "${nonce}" // Will be replaced by server
          );
        }
      }
    },
  };
}
```

#### CSP Headers Configuration

```typescript
// src/utils/security.ts
export const CSP_CONFIG = {
  development: {
    "default-src": ["'self'"],
    "script-src": ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
    "style-src": ["'self'", "'unsafe-inline'"],
    "img-src": ["'self'", "data:", "https:"],
    "font-src": ["'self'", "data:"],
    "connect-src": ["'self'", "ws:", "wss:"],
  },

  production: {
    "default-src": ["'self'"],
    "script-src": ["'self'", "'nonce-${nonce}'"],
    "style-src": ["'self'", "'nonce-${nonce}'"],
    "img-src": ["'self'", "data:", "https://cdn.your-domain.com"],
    "font-src": ["'self'", "data:", "https://fonts.gstatic.com"],
    "connect-src": ["'self'", "https://api.your-domain.com"],
    "frame-src": ["'none'"],
    "object-src": ["'none'"],
    "base-uri": ["'self'"],
    "form-action": ["'self'"],
  },
};

export function generateCSPHeader(nonce?: string): string {
  const config =
    process.env.NODE_ENV === "production"
      ? CSP_CONFIG.production
      : CSP_CONFIG.development;

  const directives = Object.entries(config).map(([directive, values]) => {
    const processedValues = values.map((value) =>
      nonce && value.includes("${nonce}")
        ? value.replace("${nonce}", nonce)
        : value
    );
    return `${directive} ${processedValues.join(" ")}`;
  });

  return directives.join("; ");
}
```

### Input Validation và Sanitization

#### XSS Prevention với DOMPurify

```typescript
// src/utils/sanitization.ts
import DOMPurify from "dompurify";

export const sanitize = {
  // Sanitize HTML content
  html: (dirty: string): string => {
    return DOMPurify.sanitize(dirty, {
      ALLOWED_TAGS: [
        "p",
        "br",
        "strong",
        "em",
        "u",
        "h1",
        "h2",
        "h3",
        "h4",
        "h5",
        "h6",
      ],
      ALLOWED_ATTR: [],
    });
  },

  // Sanitize text input (remove HTML tags)
  text: (input: string): string => {
    return input.replace(/<[^>]*>/g, "").trim();
  },

  // Sanitize URL
  url: (url: string): string => {
    try {
      const parsed = new URL(url);
      // Only allow http/https protocols
      if (!["http:", "https:"].includes(parsed.protocol)) {
        throw new Error("Invalid protocol");
      }
      return parsed.toString();
    } catch {
      return "";
    }
  },

  // Sanitize email
  email: (email: string): string => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email) ? email.toLowerCase().trim() : "";
  },

  // Sanitize phone number
  phone: (phone: string): string => {
    // Remove all non-digit characters except + and spaces
    const cleaned = phone.replace(/[^\d+\s-()]/g, "");
    return cleaned.length > 0 && cleaned.length <= 20 ? cleaned : "";
  },
};
```

#### Form Validation với Zod

```typescript
// src/features/booking/schemas/bookingSchema.ts
import { z } from "zod";
import { sanitize } from "../../../utils/sanitization";

export const bookingSchema = z
  .object({
    guestName: z
      .string()
      .min(2, "Name must be at least 2 characters")
      .max(100, "Name must be less than 100 characters")
      .transform(sanitize.text),

    guestEmail: z
      .string()
      .email("Invalid email address")
      .transform(sanitize.email),

    guestPhone: z
      .string()
      .min(10, "Phone number must be at least 10 digits")
      .transform(sanitize.phone),

    checkInDate: z
      .date()
      .refine(
        (date) => date >= new Date(),
        "Check-in date must be in the future"
      ),

    checkOutDate: z
      .date()
      .refine(
        (date) => date >= new Date(),
        "Check-out date must be in the future"
      ),

    numberOfGuests: z
      .number()
      .min(1, "At least 1 guest required")
      .max(10, "Maximum 10 guests allowed"),

    specialRequests: z
      .string()
      .max(500, "Special requests must be less than 500 characters")
      .optional()
      .transform((val) => (val ? sanitize.text(val) : undefined)),
  })
  .refine((data) => data.checkOutDate > data.checkInDate, {
    message: "Check-out date must be after check-in date",
    path: ["checkOutDate"],
  });
```

### Authentication Security

#### HttpOnly Cookie Management

```typescript
// src/services/auth.ts
export class AuthService {
  private readonly API_URL = import.meta.env.VITE_API_URL;

  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      const response = await axios.post(`${this.API_URL}/login`, credentials, {
        withCredentials: true, // Important for HttpOnly cookies
      });

      // CSRF token will be set in HttpOnly cookie
      // No need to store tokens in localStorage/sessionStorage

      return response.data;
    } catch (error) {
      throw this.handleAuthError(error);
    }
  }

  async logout(): Promise<void> {
    try {
      await axios.post(
        `${this.API_URL}/logout`,
        {},
        {
          withCredentials: true,
        }
      );
    } catch (error) {
      // Even if logout fails, clear client state
      console.warn("Logout request failed:", error);
    }
  }

  async refreshToken(): Promise<void> {
    try {
      // Laravel Sanctum will automatically refresh the token
      await axios.get(`${this.API_URL}/user`, {
        withCredentials: true,
      });
    } catch (error) {
      // Token refresh failed, user needs to login again
      throw new Error("Session expired");
    }
  }

  async getCurrentUser(): Promise<User | null> {
    try {
      const response = await axios.get(`${this.API_URL}/user`, {
        withCredentials: true,
      });
      return response.data;
    } catch (error) {
      return null;
    }
  }
}
```

#### CSRF Protection

```typescript
// src/services/api.ts
import axios from "axios";

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true, // Required for CSRF cookie
});

// Request interceptor to add CSRF token
api.interceptors.request.use((config) => {
  // Laravel Sanctum automatically includes XSRF-TOKEN cookie
  // Axios will automatically send it with requests
  return config;
});

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 419) {
      // CSRF token mismatch - try to refresh
      try {
        await axios.get("/sanctum/csrf-cookie", { withCredentials: true });
        // Retry the original request
        return api.request(error.config);
      } catch (refreshError) {
        // Redirect to login if refresh fails
        window.location.href = "/login";
        return Promise.reject(refreshError);
      }
    }

    if (error.response?.status === 401) {
      // Unauthorized - redirect to login
      window.location.href = "/login";
      return Promise.reject(error);
    }

    return Promise.reject(error);
  }
);

export default api;
```

### Security Headers

#### Security Headers Middleware (Backend)

```php
// backend/app/Http/Middleware/SecurityHeaders.php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // CSP Header (generated by frontend utility)
        $nonce = $request->session()->get('csp_nonce', bin2hex(random_bytes(16)));
        $request->session()->put('csp_nonce', $nonce);

        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'nonce-{$nonce}'; ";
        $csp .= "style-src 'self' 'nonce-{$nonce}'; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "font-src 'self' data:; ";
        $csp .= "connect-src 'self'; ";
        $csp .= "frame-src 'none'; ";
        $csp .= "object-src 'none'; ";
        $csp .= "base-uri 'self'; ";
        $csp .= "form-action 'self'";

        return $response
            ->header('Content-Security-Policy', $csp)
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-XSS-Protection', '1; mode=block')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
            ->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
```

### Secure Error Handling

#### Error Boundary với Security

```typescript
// src/shared/components/ErrorBoundary.tsx
import React, { Component, ErrorInfo, ReactNode } from "react";

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  error?: Error;
}

export class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Log error securely - don't expose sensitive information
    const safeError = {
      message: error.message,
      stack: this.sanitizeStackTrace(error.stack),
      componentStack: errorInfo.componentStack,
      timestamp: new Date().toISOString(),
      userAgent: navigator.userAgent,
      url: window.location.href,
    };

    // Send to error reporting service
    this.reportError(safeError);

    // Log to console in development
    if (process.env.NODE_ENV === "development") {
      console.error("Error Boundary caught an error:", error, errorInfo);
    }
  }

  private sanitizeStackTrace(stack?: string): string {
    if (!stack) return "";

    // Remove file paths that might contain sensitive information
    return stack
      .split("\n")
      .map((line) => {
        // Remove absolute paths, keep relative paths
        return line.replace(/\(.*\/([^\/]+\.tsx?)\)/g, "($1)");
      })
      .join("\n");
  }

  private reportError(error: any) {
    // Send to error reporting service (e.g., Sentry)
    fetch("/api/errors", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(error),
    }).catch(() => {
      // Silently fail if error reporting fails
    });
  }

  public render() {
    if (this.state.hasError) {
      return (
        this.props.fallback || (
          <div className="min-h-screen flex items-center justify-center bg-gray-50">
            <div className="max-w-md w-full bg-white shadow-lg rounded-lg p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg
                    className="h-5 w-5 text-red-400"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                      clipRule="evenodd"
                    />
                  </svg>
                </div>
                <div className="ml-3">
                  <h3 className="text-sm font-medium text-gray-800">
                    Something went wrong
                  </h3>
                  <div className="mt-2 text-sm text-gray-700">
                    <p>
                      We apologize for the inconvenience. Please try refreshing
                      the page.
                    </p>
                  </div>
                  <div className="mt-4">
                    <button
                      onClick={() => window.location.reload()}
                      className="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium"
                    >
                      Refresh Page
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )
      );
    }

    return this.props.children;
  }
}
```

## Best Practices Summary

### Performance Best Practices

1. **Bundle Optimization**

   - Code splitting với React.lazy
   - Vendor chunk separation
   - Tree shaking và minification

2. **Asset Optimization**

   - Modern image formats (WebP, AVIF)
   - Responsive images
   - CDN integration

3. **Caching Strategy**

   - HTTP caching headers
   - Service Worker cho offline support
   - Browser cache optimization

4. **Monitoring**
   - Web Vitals tracking
   - Performance budgets
   - Real-time monitoring

### Security Best Practices

1. **Content Security**

   - Strict CSP headers
   - Nonce-based script injection
   - XSS prevention

2. **Authentication**

   - HttpOnly cookie storage
   - CSRF token protection
   - Secure session management

3. **Input Validation**

   - Runtime type checking với Zod
   - Input sanitization
   - SQL injection prevention

4. **Error Handling**

   - Secure error boundaries
   - Safe error logging
   - User-friendly error messages

5. **Network Security**
   - HTTPS enforcement
   - Secure headers
   - Rate limiting

### Implementation Checklist

- [ ] Code splitting implemented
- [ ] Image optimization configured
- [ ] CSP headers deployed
- [ ] HttpOnly cookies enabled
- [ ] Input validation active
- [ ] Error boundaries in place
- [ ] Web Vitals monitoring
- [ ] Performance budgets set
- [ ] Security headers configured
- [ ] HTTPS enforced
- [ ] Regular security audits
