# Utils Layer (`src/utils/`)

> Lớp utilities - Helper functions và constants

## Tổng quan

Utils Layer chứa các utility functions:

```
src/utils/
├── webVitals.ts      # Performance monitoring
├── constants.ts      # App constants và routes
├── storage.ts        # Local storage utilities
├── formatters.ts     # Data formatting functions
└── index.ts          # Utils exports
```

Utils bao gồm:

- **Performance Monitoring**: Web Vitals tracking
- **Constants**: App-wide constants và configuration
- **Storage**: Local/session storage helpers
- **Formatters**: Data formatting và transformation
- **Helpers**: General utility functions

## 1. Web Vitals (`utils/webVitals.ts`)

```typescript
// utils/webVitals.ts
import { onCLS, onFID, onFCP, onLCP, onTTFB, Metric } from "web-vitals";

type MetricCallback = (metric: Metric) => void;

class WebVitalsTracker {
  private metrics: Map<string, Metric> = new Map();
  private callbacks: MetricCallback[] = [];

  constructor() {
    this.init();
  }

  private init() {
    // Core Web Vitals
    onCLS(this.handleMetric.bind(this));
    onFID(this.handleMetric.bind(this));
    onFCP(this.handleMetric.bind(this));
    onLCP(this.handleMetric.bind(this));
    onTTFB(this.handleMetric.bind(this));
  }

  private handleMetric(metric: Metric) {
    this.metrics.set(metric.name, metric);

    // Call all registered callbacks
    this.callbacks.forEach((callback) => callback(metric));

    // Log to console in development
    if (import.meta.env.DEV) {
      console.log(`[WebVitals] ${metric.name}:`, metric.value);
    }

    // Send to analytics service
    this.sendToAnalytics(metric);
  }

  private sendToAnalytics(metric: Metric) {
    // Send to Google Analytics, Mixpanel, etc.
    if (typeof window !== "undefined" && window.gtag) {
      window.gtag("event", metric.name, {
        value: Math.round(metric.value * 1000) / 1000, // Round to 3 decimal places
        event_category: "Web Vitals",
        event_label: metric.id,
        non_interaction: true,
      });
    }
  }

  // Get metric value
  getMetric(name: string): Metric | undefined {
    return this.metrics.get(name);
  }

  // Get all metrics
  getAllMetrics(): Map<string, Metric> {
    return new Map(this.metrics);
  }

  // Register callback for new metrics
  onMetric(callback: MetricCallback) {
    this.callbacks.push(callback);
  }

  // Remove callback
  offMetric(callback: MetricCallback) {
    const index = this.callbacks.indexOf(callback);
    if (index > -1) {
      this.callbacks.splice(index, 1);
    }
  }
}

// Singleton instance
export const webVitals = new WebVitalsTracker();

// Convenience function to initialize web vitals
export function initWebVitals() {
  // Web vitals are automatically initialized when the module is imported
  return webVitals;
}

// Hook for React components
export function useWebVitals() {
  return webVitals;
}
```

## 2. Constants (`utils/constants.ts`)

```typescript
// utils/constants.ts
// App Configuration
export const APP_CONFIG = {
  name: "Soleil Hostel",
  version: "1.0.0",
  description: "Modern hostel booking platform",
  author: "Soleil Hostel Team",

  // API Configuration
  api: {
    baseUrl: import.meta.env.VITE_API_URL || "http://localhost:8000/api",
    timeout: 30000,
    retries: 3,
  },

  // Pagination
  pagination: {
    defaultPageSize: 20,
    maxPageSize: 100,
    pageSizeOptions: [10, 20, 50, 100],
  },

  // Booking Configuration
  booking: {
    maxGuests: 10,
    minStay: 1, // nights
    maxStay: 30, // nights
    maxAdvanceBooking: 365, // days
    cancellationDeadline: 24, // hours before check-in
  },

  // File Upload
  upload: {
    maxFileSize: 5 * 1024 * 1024, // 5MB
    allowedImageTypes: ["image/jpeg", "image/png", "image/webp"],
    maxImagesPerRoom: 10,
  },

  // Cache Configuration
  cache: {
    defaultTtl: 5 * 60 * 1000, // 5 minutes
    longTtl: 30 * 60 * 1000, // 30 minutes
  },
} as const;

// Routes Configuration
export const ROUTES = {
  home: "/",
  login: "/login",
  register: "/register",
  logout: "/logout",
  dashboard: "/dashboard",

  // Rooms
  rooms: "/rooms",
  room: (id: number | string) => `/rooms/${id}`,

  // Bookings
  bookings: "/bookings",
  booking: (id: number | string) => `/bookings/${id}`,
  createBooking: "/booking/create",

  // Admin routes
  admin: {
    dashboard: "/admin",
    rooms: "/admin/rooms",
    bookings: "/admin/bookings",
    users: "/admin/users",
    settings: "/admin/settings",
  },

  // API routes (for reference)
  api: {
    auth: {
      login: "/auth/login-httponly",
      register: "/auth/register",
      logout: "/auth/logout",
      refresh: "/auth/refresh-httponly",
      user: "/auth/user",
    },
    rooms: "/rooms",
    bookings: "/bookings",
  },
} as const;

// Status Constants
export const ROOM_STATUSES = {
  AVAILABLE: "available",
  BOOKED: "booked",
  MAINTENANCE: "maintenance",
  CLEANING: "cleaning",
} as const;

export const BOOKING_STATUSES = {
  PENDING: "pending",
  CONFIRMED: "confirmed",
  CHECKED_IN: "checked_in",
  CHECKED_OUT: "checked_out",
  CANCELLED: "cancelled",
  NO_SHOW: "no_show",
} as const;

export const PAYMENT_STATUSES = {
  PENDING: "pending",
  PAID: "paid",
  REFUNDED: "refunded",
  CANCELLED: "cancelled",
} as const;

// User Roles
export const USER_ROLES = {
  ADMIN: "admin",
  MANAGER: "manager",
  STAFF: "staff",
  USER: "user",
} as const;

// Validation Constants
export const VALIDATION = {
  password: {
    minLength: 8,
    requireUppercase: true,
    requireLowercase: true,
    requireNumbers: true,
    requireSpecialChars: false,
  },
  email: {
    maxLength: 254,
  },
  name: {
    minLength: 2,
    maxLength: 100,
  },
  phone: {
    minLength: 10,
    maxLength: 15,
  },
} as const;

// UI Constants
export const UI = {
  breakpoints: {
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
    "2xl": 1536,
  },
  zIndex: {
    dropdown: 1000,
    sticky: 1020,
    fixed: 1030,
    modal: 1040,
    popover: 1050,
    tooltip: 1060,
  },
  transitions: {
    fast: "150ms ease-in-out",
    normal: "250ms ease-in-out",
    slow: "350ms ease-in-out",
  },
} as const;

// Error Messages
export const ERROR_MESSAGES = {
  network: "Network error. Please check your connection.",
  server: "Server error. Please try again later.",
  validation: "Please check your input and try again.",
  unauthorized: "You are not authorized to perform this action.",
  notFound: "The requested resource was not found.",
  forbidden: "Access to this resource is forbidden.",
  timeout: "Request timed out. Please try again.",
} as const;

// Success Messages
export const SUCCESS_MESSAGES = {
  login: "Successfully logged in!",
  register: "Account created successfully!",
  booking: "Booking created successfully!",
  update: "Updated successfully!",
  delete: "Deleted successfully!",
} as const;
```

## 3. Storage Utilities (`utils/storage.ts`)

```typescript
// utils/storage.ts
type StorageType = "localStorage" | "sessionStorage";

class Storage {
  private storage: Storage;

  constructor(type: StorageType = "localStorage") {
    this.storage =
      type === "localStorage" ? window.localStorage : window.sessionStorage;
  }

  // Get item with type safety
  get<T>(key: string, defaultValue?: T): T | null {
    try {
      const item = this.storage.getItem(key);
      if (item === null) return defaultValue ?? null;

      return JSON.parse(item) as T;
    } catch (error) {
      console.warn(`Error reading from storage key "${key}":`, error);
      return defaultValue ?? null;
    }
  }

  // Set item with error handling
  set<T>(key: string, value: T): boolean {
    try {
      this.storage.setItem(key, JSON.stringify(value));
      return true;
    } catch (error) {
      console.error(`Error writing to storage key "${key}":`, error);
      return false;
    }
  }

  // Remove item
  remove(key: string): void {
    try {
      this.storage.removeItem(key);
    } catch (error) {
      console.warn(`Error removing storage key "${key}":`, error);
    }
  }

  // Clear all items
  clear(): void {
    try {
      this.storage.clear();
    } catch (error) {
      console.error("Error clearing storage:", error);
    }
  }

  // Check if key exists
  has(key: string): boolean {
    return this.storage.getItem(key) !== null;
  }

  // Get all keys
  keys(): string[] {
    const keys: string[] = [];
    for (let i = 0; i < this.storage.length; i++) {
      const key = this.storage.key(i);
      if (key) keys.push(key);
    }
    return keys;
  }

  // Get storage size in bytes
  getSize(): number {
    let size = 0;
    this.keys().forEach((key) => {
      const value = this.storage.getItem(key);
      if (value) {
        size += key.length + value.length;
      }
    });
    return size;
  }
}

// Storage instances
export const localStorage = new Storage("localStorage");
export const sessionStorage = new Storage("sessionStorage");

// Convenience functions
export const getFromStorage = localStorage.get.bind(localStorage);
export const setToStorage = localStorage.set.bind(localStorage);
export const removeFromStorage = localStorage.remove.bind(localStorage);

// Session-specific functions
export const getFromSession = sessionStorage.get.bind(sessionStorage);
export const setToSession = sessionStorage.set.bind(sessionStorage);
export const removeFromSession = sessionStorage.remove.bind(sessionStorage);

// Cache utilities
export class Cache {
  private storage: Storage;
  private prefix: string;

  constructor(storage: Storage = localStorage, prefix = "cache_") {
    this.storage = storage;
    this.prefix = prefix;
  }

  set<T>(key: string, value: T, ttl?: number): void {
    const item = {
      value,
      timestamp: Date.now(),
      ttl: ttl ?? 5 * 60 * 1000, // 5 minutes default
    };
    this.storage.set(this.prefix + key, item);
  }

  get<T>(key: string): T | null {
    const item = this.storage.get<{ value: T; timestamp: number; ttl: number }>(
      this.prefix + key
    );
    if (!item) return null;

    if (Date.now() - item.timestamp > item.ttl) {
      this.storage.remove(this.prefix + key);
      return null;
    }

    return item.value;
  }

  remove(key: string): void {
    this.storage.remove(this.prefix + key);
  }

  clear(): void {
    const keys = this.storage
      .keys()
      .filter((key) => key.startsWith(this.prefix));
    keys.forEach((key) => this.storage.remove(key));
  }
}

export const cache = new Cache();
```

## 4. Formatters (`utils/formatters.ts`)

```typescript
// utils/formatters.ts
import { format, formatDistance, formatRelative, isValid } from "date-fns";

// Date Formatters
export const dateFormatters = {
  // Display formats
  short: (date: Date | string) => {
    const d = typeof date === "string" ? new Date(date) : date;
    return isValid(d) ? format(d, "MMM dd, yyyy") : "";
  },

  long: (date: Date | string) => {
    const d = typeof date === "string" ? new Date(date) : date;
    return isValid(d) ? format(d, "EEEE, MMMM dd, yyyy") : "";
  },

  time: (date: Date | string) => {
    const d = typeof date === "string" ? new Date(date) : date;
    return isValid(d) ? format(d, "HH:mm") : "";
  },

  datetime: (date: Date | string) => {
    const d = typeof date === "string" ? new Date(date) : date;
    return isValid(d) ? format(d, "MMM dd, yyyy HH:mm") : "";
  },

  // Relative time
  relative: (date: Date | string) => {
    const d = typeof date === "string" ? new Date(date) : date;
    return isValid(d) ? formatDistance(d, new Date(), { addSuffix: true }) : "";
  },

  // Calendar format
  calendar: (date: Date | string) => {
    const d = typeof date === "string" ? new Date(date) : date;
    return isValid(d) ? formatRelative(d, new Date()) : "";
  },

  // ISO format for APIs
  iso: (date: Date | string) => {
    const d = typeof date === "string" ? new Date(date) : date;
    return isValid(d) ? d.toISOString() : "";
  },
};

// Currency Formatters
export const currencyFormatters = {
  usd: (amount: number) => {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
    }).format(amount);
  },

  eur: (amount: number) => {
    return new Intl.NumberFormat("en-EU", {
      style: "currency",
      currency: "EUR",
    }).format(amount);
  },

  vnd: (amount: number) => {
    return new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
    }).format(amount);
  },

  format: (amount: number, currency = "USD") => {
    const formatter =
      currencyFormatters[
        currency.toLowerCase() as keyof typeof currencyFormatters
      ];
    return formatter ? formatter(amount) : currencyFormatters.usd(amount);
  },
};

// Number Formatters
export const numberFormatters = {
  compact: (num: number) => {
    return new Intl.NumberFormat("en-US", {
      notation: "compact",
      maximumFractionDigits: 1,
    }).format(num);
  },

  decimal: (num: number, decimals = 2) => {
    return new Intl.NumberFormat("en-US", {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(num);
  },

  percentage: (num: number, decimals = 1) => {
    return new Intl.NumberFormat("en-US", {
      style: "percent",
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(num / 100);
  },

  ordinal: (num: number) => {
    const suffixes = ["th", "st", "nd", "rd"];
    const remainder = num % 100;
    return (
      num +
      (suffixes[(remainder - 20) % 10] || suffixes[remainder] || suffixes[0])
    );
  },
};

// Text Formatters
export const textFormatters = {
  capitalize: (str: string) => {
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
  },

  titleCase: (str: string) => {
    return str
      .split(" ")
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
      .join(" ");
  },

  camelCase: (str: string) => {
    return str
      .replace(/(?:^\w|[A-Z]|\b\w)/g, (word, index) =>
        index === 0 ? word.toLowerCase() : word.toUpperCase()
      )
      .replace(/\s+/g, "");
  },

  kebabCase: (str: string) => {
    return str
      .replace(/([a-z])([A-Z])/g, "$1-$2")
      .replace(/[\s_]+/g, "-")
      .toLowerCase();
  },

  truncate: (str: string, length: number, suffix = "...") => {
    return str.length <= length
      ? str
      : str.slice(0, length - suffix.length) + suffix;
  },

  slug: (str: string) => {
    return str
      .toLowerCase()
      .replace(/[^\w\s-]/g, "")
      .replace(/[\s_-]+/g, "-")
      .replace(/^-+|-+$/g, "");
  },
};

// File Formatters
export const fileFormatters = {
  size: (bytes: number) => {
    const sizes = ["Bytes", "KB", "MB", "GB", "TB"];
    if (bytes === 0) return "0 Bytes";
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round((bytes / Math.pow(1024, i)) * 100) / 100 + " " + sizes[i];
  },

  extension: (filename: string) => {
    return filename.split(".").pop()?.toLowerCase() || "";
  },

  name: (filename: string) => {
    return filename.replace(/\.[^/.]+$/, "");
  },
};
```

## 5. Utils Index (`utils/index.ts`)

```typescript
// utils/index.ts
// Web Vitals
export { initWebVitals, useWebVitals, webVitals } from "./webVitals";

// Constants
export {
  APP_CONFIG,
  ROUTES,
  ROOM_STATUSES,
  BOOKING_STATUSES,
  PAYMENT_STATUSES,
  USER_ROLES,
  VALIDATION,
  UI,
  ERROR_MESSAGES,
  SUCCESS_MESSAGES,
} from "./constants";

// Storage
export {
  localStorage,
  sessionStorage,
  getFromStorage,
  setToStorage,
  removeFromStorage,
  getFromSession,
  setToSession,
  removeFromSession,
  cache,
} from "./storage";

// Formatters
export {
  dateFormatters,
  currencyFormatters,
  numberFormatters,
  textFormatters,
  fileFormatters,
} from "./formatters";
```

## Best Practices cho Utils

### 1. Performance

- **Lazy Loading**: Import utils only when needed
- **Memoization**: Cache expensive operations
- **Constants**: Use const assertions for better tree-shaking

### 2. Error Handling

- **Graceful Degradation**: Handle storage failures
- **Validation**: Validate inputs before processing
- **Logging**: Log errors for debugging

### 3. Type Safety

- **Generic Types**: Use generics for flexible utilities
- **Type Guards**: Runtime type checking
- **Strict Types**: Avoid any types

### 4. Testing

- **Pure Functions**: Easy to test utilities
- **Mock Dependencies**: Mock storage and external APIs
- **Edge Cases**: Test error conditions

### 5. Maintainability

- **Single Responsibility**: One function, one purpose
- **Clear Naming**: Descriptive function names
- **Documentation**: JSDoc comments for complex functions

### 6. Browser Compatibility

- **Feature Detection**: Check for browser support
- **Polyfills**: Include necessary polyfills
- **Fallbacks**: Provide fallbacks for unsupported features
