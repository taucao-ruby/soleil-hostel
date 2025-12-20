# Shared Layer (`src/shared/`)

> Lớp chia sẻ - Components và utilities tái sử dụng

## Tổng quan

Shared Layer chứa code có thể tái sử dụng:

```
src/shared/
├── components/         # Reusable UI components
│   ├── ui/            # Basic UI components
│   ├── layout/        # Layout components
│   ├── feedback/      # User feedback components
│   └── index.ts       # Component exports
├── lib/               # Utility libraries
│   ├── api.ts         # API utilities
│   └── index.ts       # Library exports
├── utils/             # Helper functions
│   ├── date.ts        # Date utilities
│   ├── form.ts        # Form utilities
│   └── index.ts       # Utils exports
└── index.ts           # Shared layer exports
```

## 1. Components (`shared/components/`)

### UI Components (`ui/`)

#### Button Component

```typescript
// shared/components/ui/Button.tsx
import React from "react";
import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@/shared/lib/utils";

const buttonVariants = cva(
  "inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none ring-offset-background",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground hover:bg-primary/90",
        destructive:
          "bg-destructive text-destructive-foreground hover:bg-destructive/90",
        outline:
          "border border-input hover:bg-accent hover:text-accent-foreground",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-secondary/80",
        ghost: "hover:bg-accent hover:text-accent-foreground",
        link: "underline-offset-4 hover:underline text-primary",
      },
      size: {
        default: "h-10 py-2 px-4",
        sm: "h-9 px-3 rounded-md",
        lg: "h-11 px-8 rounded-md",
        icon: "h-10 w-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  loading?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  (
    { className, variant, size, loading, children, disabled, ...props },
    ref
  ) => {
    return (
      <button
        className={cn(buttonVariants({ variant, size, className }))}
        ref={ref}
        disabled={disabled || loading}
        {...props}
      >
        {loading && (
          <svg
            className="mr-2 h-4 w-4 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              className="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              strokeWidth="4"
            />
            <path
              className="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
        )}
        {children}
      </button>
    );
  }
);

Button.displayName = "Button";

export { Button, buttonVariants };
```

#### Input Component

```typescript
// shared/components/ui/Input.tsx
import React from "react";
import { cn } from "@/shared/lib/utils";

export interface InputProps
  extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  helperText?: string;
}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, type, label, error, helperText, ...props }, ref) => {
    const inputId =
      props.id || `input-${Math.random().toString(36).substr(2, 9)}`;

    return (
      <div className="space-y-1">
        {label && (
          <label
            htmlFor={inputId}
            className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
          >
            {label}
          </label>
        )}
        <input
          type={type}
          className={cn(
            "flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50",
            error && "border-red-500 focus-visible:ring-red-500",
            className
          )}
          ref={ref}
          id={inputId}
          {...props}
        />
        {error && <p className="text-sm text-red-500">{error}</p>}
        {helperText && !error && (
          <p className="text-sm text-muted-foreground">{helperText}</p>
        )}
      </div>
    );
  }
);

Input.displayName = "Input";

export { Input };
```

#### Card Component

```typescript
// shared/components/ui/Card.tsx
import React from "react";
import { cn } from "@/shared/lib/utils";

const Card = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn(
      "rounded-lg border bg-card text-card-foreground shadow-sm",
      className
    )}
    {...props}
  />
));

const CardHeader = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn("flex flex-col space-y-1.5 p-6", className)}
    {...props}
  />
));

const CardTitle = React.forwardRef<
  HTMLParagraphElement,
  React.HTMLAttributes<HTMLHeadingElement>
>(({ className, ...props }, ref) => (
  <h3
    ref={ref}
    className={cn(
      "text-2xl font-semibold leading-none tracking-tight",
      className
    )}
    {...props}
  />
));

const CardDescription = React.forwardRef<
  HTMLParagraphElement,
  React.HTMLAttributes<HTMLParagraphElement>
>(({ className, ...props }, ref) => (
  <p
    ref={ref}
    className={cn("text-sm text-muted-foreground", className)}
    {...props}
  />
));

const CardContent = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div ref={ref} className={cn("p-6 pt-0", className)} {...props} />
));

const CardFooter = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn("flex items-center p-6 pt-0", className)}
    {...props}
  />
));

Card.displayName = "Card";
CardHeader.displayName = "CardHeader";
CardTitle.displayName = "CardTitle";
CardDescription.displayName = "CardDescription";
CardContent.displayName = "CardContent";
CardFooter.displayName = "CardFooter";

export {
  Card,
  CardHeader,
  CardFooter,
  CardTitle,
  CardDescription,
  CardContent,
};
```

### Layout Components (`layout/`)

#### Header Component

```typescript
// shared/components/layout/Header.tsx
import React from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "@/features/auth/AuthContext";
import { Button } from "@/shared/components/ui/Button";
import { toast } from "react-hot-toast";

const Header: React.FC = () => {
  const { user, logout, isAuthenticated } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    try {
      await logout();
      toast.success("Logged out successfully");
      navigate("/login");
    } catch (error) {
      console.error("Logout error:", error);
      toast.error("Logout failed");
    }
  };

  return (
    <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="container flex h-14 items-center">
        <div className="mr-4 hidden md:flex">
          <Link to="/" className="mr-6 flex items-center space-x-2">
            <span className="hidden font-bold sm:inline-block">
              Soleil Hostel
            </span>
          </Link>
          <nav className="flex items-center space-x-6 text-sm font-medium">
            <Link
              to="/rooms"
              className="transition-colors hover:text-foreground/80 text-foreground/60"
            >
              Rooms
            </Link>
            {isAuthenticated && (
              <Link
                to="/dashboard"
                className="transition-colors hover:text-foreground/80 text-foreground/60"
              >
                Dashboard
              </Link>
            )}
          </nav>
        </div>

        <div className="flex flex-1 items-center justify-between space-x-2 md:justify-end">
          <div className="w-full flex-1 md:w-auto md:flex-none">
            {/* Mobile menu button could go here */}
          </div>

          <nav className="flex items-center space-x-2">
            {isAuthenticated ? (
              <div className="flex items-center space-x-4">
                <span className="text-sm text-muted-foreground">
                  Welcome, {user?.name}
                </span>
                <Button variant="outline" size="sm" onClick={handleLogout}>
                  Logout
                </Button>
              </div>
            ) : (
              <div className="flex items-center space-x-2">
                <Button variant="ghost" size="sm" asChild>
                  <Link to="/login">Login</Link>
                </Button>
                <Button size="sm" asChild>
                  <Link to="/register">Sign Up</Link>
                </Button>
              </div>
            )}
          </nav>
        </div>
      </div>
    </header>
  );
};

export { Header };
```

#### Footer Component

```typescript
// shared/components/layout/Footer.tsx
import React from "react";
import { Link } from "react-router-dom";

const Footer: React.FC = () => {
  return (
    <footer className="border-t bg-background">
      <div className="container flex flex-col items-center justify-between gap-4 py-10 md:h-24 md:flex-row md:py-0">
        <div className="flex flex-col items-center gap-4 px-8 md:flex-row md:gap-2 md:px-0">
          <p className="text-center text-sm leading-loose text-muted-foreground md:text-left">
            Built with ❤️ for Soleil Hostel. All rights reserved.
          </p>
        </div>

        <div className="flex items-center space-x-4">
          <Link
            to="/privacy"
            className="text-sm text-muted-foreground hover:text-foreground"
          >
            Privacy Policy
          </Link>
          <Link
            to="/terms"
            className="text-sm text-muted-foreground hover:text-foreground"
          >
            Terms of Service
          </Link>
          <Link
            to="/contact"
            className="text-sm text-muted-foreground hover:text-foreground"
          >
            Contact
          </Link>
        </div>
      </div>
    </footer>
  );
};

export { Footer };
```

### Feedback Components (`feedback/`)

#### LoadingSpinner Component

```typescript
// shared/components/feedback/LoadingSpinner.tsx
import React from "react";
import { cn } from "@/shared/lib/utils";

interface LoadingSpinnerProps {
  size?: "sm" | "md" | "lg" | "xl";
  fullScreen?: boolean;
  message?: string;
  className?: string;
}

const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({
  size = "md",
  fullScreen = false,
  message = "Loading...",
  className,
}) => {
  const sizeClasses = {
    sm: "h-4 w-4",
    md: "h-8 w-8",
    lg: "h-12 w-12",
    xl: "h-16 w-16",
  };

  const spinner = (
    <div
      className={cn(
        "flex flex-col items-center justify-center space-y-4",
        className
      )}
    >
      <div
        className={cn(
          "animate-spin rounded-full border-4 border-muted border-t-primary",
          sizeClasses[size]
        )}
      />
      {message && <p className="text-sm text-muted-foreground">{message}</p>}
    </div>
  );

  if (fullScreen) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm">
        {spinner}
      </div>
    );
  }

  return spinner;
};

export { LoadingSpinner };
```

#### ErrorMessage Component

```typescript
// shared/components/feedback/ErrorMessage.tsx
import React from "react";
import { AlertCircle, RefreshCw } from "lucide-react";
import { Button } from "@/shared/components/ui/Button";
import { cn } from "@/shared/lib/utils";

interface ErrorMessageProps {
  title?: string;
  message: string;
  onRetry?: () => void;
  className?: string;
}

const ErrorMessage: React.FC<ErrorMessageProps> = ({
  title = "Error",
  message,
  onRetry,
  className,
}) => {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center space-y-4 rounded-lg border border-red-200 bg-red-50 p-8 text-center",
        className
      )}
    >
      <AlertCircle className="h-12 w-12 text-red-500" />
      <div className="space-y-2">
        <h3 className="text-lg font-semibold text-red-900">{title}</h3>
        <p className="text-sm text-red-700">{message}</p>
      </div>
      {onRetry && (
        <Button variant="outline" size="sm" onClick={onRetry}>
          <RefreshCw className="mr-2 h-4 w-4" />
          Try Again
        </Button>
      )}
    </div>
  );
};

export { ErrorMessage };
```

#### EmptyState Component

```typescript
// shared/components/feedback/EmptyState.tsx
import React from "react";
import { FileX } from "lucide-react";
import { Button } from "@/shared/components/ui/Button";
import { cn } from "@/shared/lib/utils";

interface EmptyStateProps {
  title: string;
  description?: string;
  action?: {
    label: string;
    onClick: () => void;
  };
  icon?: React.ReactNode;
  className?: string;
}

const EmptyState: React.FC<EmptyStateProps> = ({
  title,
  description,
  action,
  icon,
  className,
}) => {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center space-y-4 rounded-lg border border-dashed p-8 text-center",
        className
      )}
    >
      <div className="flex h-20 w-20 items-center justify-center rounded-full bg-muted">
        {icon || <FileX className="h-10 w-10 text-muted-foreground" />}
      </div>
      <div className="space-y-2">
        <h3 className="text-lg font-semibold">{title}</h3>
        {description && (
          <p className="text-sm text-muted-foreground">{description}</p>
        )}
      </div>
      {action && <Button onClick={action.onClick}>{action.label}</Button>}
    </div>
  );
};

export { EmptyState };
```

## 2. Utilities (`shared/lib/` & `shared/utils/`)

### API Utilities (`lib/api.ts`)

```typescript
// shared/lib/api.ts
import { z } from "zod";

// Generic API response validator
export function validateApiResponse<T>(
  schema: z.ZodSchema<T>,
  data: unknown
): T {
  try {
    return schema.parse(data);
  } catch (error) {
    if (error instanceof z.ZodError) {
      console.error("[API Validation Error]", error.issues);
      throw new Error(
        `API response validation failed: ${error.issues
          .map((e) => e.message)
          .join(", ")}`
      );
    }
    throw error;
  }
}

// Safe validator that returns null on failure
export function safeValidateApiResponse<T>(
  schema: z.ZodSchema<T>,
  data: unknown
): T | null {
  const result = schema.safeParse(data);
  if (result.success) {
    return result.data;
  }
  console.warn("[API Validation Warning]", result.error.issues);
  return null;
}

// Generic error handler
export function handleApiError(error: unknown): string {
  if (error instanceof Error) {
    return error.message;
  }
  return "An unexpected error occurred";
}
```

### Date Utilities (`utils/date.ts`)

```typescript
// shared/utils/date.ts
import { format, differenceInDays, isAfter, isBefore, addDays } from "date-fns";

export const dateUtils = {
  // Format date for display
  formatDate: (date: Date | string, formatStr = "PPP") => {
    const d = typeof date === "string" ? new Date(date) : date;
    return format(d, formatStr);
  },

  // Calculate number of nights between check-in and check-out
  calculateNights: (checkIn: Date | string, checkOut: Date | string) => {
    const checkInDate =
      typeof checkIn === "string" ? new Date(checkIn) : checkIn;
    const checkOutDate =
      typeof checkOut === "string" ? new Date(checkOut) : checkOut;
    return differenceInDays(checkOutDate, checkInDate);
  },

  // Check if date is in the past
  isPast: (date: Date | string) => {
    const d = typeof date === "string" ? new Date(date) : date;
    return isBefore(d, new Date());
  },

  // Check if date is in the future
  isFuture: (date: Date | string) => {
    const d = typeof date === "string" ? new Date(date) : date;
    return isAfter(d, new Date());
  },

  // Get minimum check-out date (day after check-in)
  getMinCheckOut: (checkIn: Date | string) => {
    const checkInDate =
      typeof checkIn === "string" ? new Date(checkIn) : checkIn;
    return addDays(checkInDate, 1);
  },

  // Format date range for display
  formatDateRange: (checkIn: Date | string, checkOut: Date | string) => {
    const checkInStr = dateUtils.formatDate(checkIn, "MMM dd");
    const checkOutStr = dateUtils.formatDate(checkOut, "MMM dd, yyyy");
    return `${checkInStr} - ${checkOutStr}`;
  },

  // Check if two date ranges overlap
  doDateRangesOverlap: (
    start1: Date | string,
    end1: Date | string,
    start2: Date | string,
    end2: Date | string
  ) => {
    const s1 = typeof start1 === "string" ? new Date(start1) : start1;
    const e1 = typeof end1 === "string" ? new Date(end1) : end1;
    const s2 = typeof start2 === "string" ? new Date(start2) : start2;
    const e2 = typeof end2 === "string" ? new Date(end2) : end2;

    return s1 < e2 && s2 < e1;
  },
};
```

### Form Utilities (`utils/form.ts`)

```typescript
// shared/utils/form.ts
import { z } from "zod";

export const formUtils = {
  // Extract field errors from Zod error
  getFieldErrors: (error: z.ZodError) => {
    const fieldErrors: Record<string, string[]> = {};
    error.errors.forEach((err) => {
      const field = err.path.join(".");
      if (!fieldErrors[field]) {
        fieldErrors[field] = [];
      }
      fieldErrors[field].push(err.message);
    });
    return fieldErrors;
  },

  // Check if field has errors
  hasFieldError: (fieldErrors: Record<string, string[]>, field: string) => {
    return fieldErrors[field] && fieldErrors[field].length > 0;
  },

  // Get first error message for field
  getFieldError: (fieldErrors: Record<string, string[]>, field: string) => {
    return fieldErrors[field]?.[0] || "";
  },

  // Convert form data to FormData
  toFormData: (data: Record<string, any>) => {
    const formData = new FormData();
    Object.entries(data).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        if (Array.isArray(value)) {
          value.forEach((item) => formData.append(`${key}[]`, item));
        } else if (value instanceof File) {
          formData.append(key, value);
        } else {
          formData.append(key, String(value));
        }
      }
    });
    return formData;
  },

  // Debounce function for form inputs
  debounce: <T extends (...args: any[]) => any>(
    func: T,
    wait: number
  ): ((...args: Parameters<T>) => void) => {
    let timeout: NodeJS.Timeout;
    return (...args: Parameters<T>) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => func(...args), wait);
    };
  },
};
```

### Utility Functions (`lib/utils.ts`)

```typescript
// shared/lib/utils.ts
import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

// Generate random ID
export function generateId(length = 8): string {
  return Math.random().toString(36).substr(2, length);
}

// Deep clone object
export function deepClone<T>(obj: T): T {
  if (obj === null || typeof obj !== "object") return obj;
  if (obj instanceof Date) return new Date(obj.getTime()) as T;
  if (Array.isArray(obj)) return obj.map((item) => deepClone(item)) as T;

  const clonedObj = {} as T;
  for (const key in obj) {
    if (obj.hasOwnProperty(key)) {
      clonedObj[key] = deepClone(obj[key]);
    }
  }
  return clonedObj;
}

// Check if object is empty
export function isEmpty(obj: any): boolean {
  if (obj === null || obj === undefined) return true;
  if (typeof obj === "string" || Array.isArray(obj)) return obj.length === 0;
  if (typeof obj === "object") return Object.keys(obj).length === 0;
  return false;
}

// Capitalize first letter
export function capitalize(str: string): string {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

// Format currency
export function formatCurrency(amount: number, currency = "USD"): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
  }).format(amount);
}

// Truncate text
export function truncateText(text: string, maxLength: number): string {
  if (text.length <= maxLength) return text;
  return text.slice(0, maxLength) + "...";
}
```

## 3. Component Index (`components/index.ts`)

```typescript
// shared/components/index.ts
// UI Components
export { Button } from "./ui/Button";
export { Input } from "./ui/Input";
export {
  Card,
  CardHeader,
  CardFooter,
  CardTitle,
  CardDescription,
  CardContent,
} from "./ui/Card";

// Layout Components
export { Header } from "./layout/Header";
export { Footer } from "./layout/Footer";

// Feedback Components
export { LoadingSpinner } from "./feedback/LoadingSpinner";
export { ErrorMessage } from "./feedback/ErrorMessage";
export { EmptyState } from "./feedback/EmptyState";
```

## 4. Shared Layer Index (`index.ts`)

```typescript
// shared/index.ts
// Components
export * from "./components";

// Utilities
export * from "./lib";
export * from "./utils";
```

## Best Practices cho Shared Components

### 1. Component Design

- **Consistent API**: Props interface rõ ràng
- **Accessibility**: ARIA labels, keyboard navigation
- **Responsive**: Mobile-first design
- **Themeable**: CSS variables cho customization

### 2. Performance

- **Memoization**: React.memo cho expensive components
- **Lazy loading**: Dynamic imports cho heavy components
- **Bundle splitting**: Separate chunks cho large components

### 3. Testing

- **Unit tests**: Test component logic
- **Integration tests**: Test component interactions
- **Visual tests**: Screenshot testing với Playwright

### 4. Documentation

- **Storybook**: Interactive component documentation
- **Prop types**: TypeScript interfaces
- **Usage examples**: Code examples trong comments

### 5. Maintenance

- **Versioning**: Semantic versioning cho component changes
- **Deprecation**: Clear migration path cho breaking changes
- **Changelog**: Document all changes
