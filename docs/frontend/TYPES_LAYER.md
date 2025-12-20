# Types Layer (`src/types/`)

> Lớp types - Định nghĩa TypeScript types và Zod schemas

## Tổng quan

Types Layer chứa tất cả type definitions:

```
src/types/
├── api.ts           # API response/request types
├── components.ts    # Component prop types
├── forms.ts         # Form data types
└── index.ts         # Type exports
```

Types được định nghĩa bằng:

- **Zod Schemas**: Runtime validation và type inference
- **TypeScript Interfaces**: Explicit type definitions
- **Generic Types**: Reusable type patterns

## 1. API Types (`types/api.ts`)

### Base Schemas

```typescript
// types/api.ts
import { z } from "zod";

// Base API Response Schema
export const ApiResponseSchema = z.object({
  success: z.boolean().optional(),
  message: z.string().optional(),
  data: z.unknown().optional(),
  errors: z.record(z.string(), z.array(z.string())).optional(),
});

// Error Response Schema
export const ApiErrorSchema = z.object({
  message: z.string(),
  errors: z.record(z.string(), z.array(z.string())).optional(),
  exception: z.string().optional(),
  file: z.string().optional(),
  line: z.number().optional(),
});

// Pagination Schema
export const PaginationSchema = z.object({
  current_page: z.number(),
  per_page: z.number(),
  total: z.number(),
  last_page: z.number(),
  from: z.number().nullable(),
  to: z.number().nullable(),
});

// Paginated Response Schema
export const PaginatedResponseSchema = <T extends z.ZodTypeAny>(
  dataSchema: T
) =>
  ApiResponseSchema.extend({
    data: z.array(dataSchema),
    meta: PaginationSchema,
  });
```

### Authentication Types

```typescript
// User Schema
export const UserSchema = z.object({
  id: z.number(),
  name: z.string().min(1, "Name is required"),
  email: z.string().email("Invalid email address"),
  email_verified_at: z.string().nullable().optional(),
  avatar: z.string().url().optional(),
  role: z.enum(["admin", "user"]).default("user"),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
});

// Auth Response Schema
export const AuthResponseSchema = ApiResponseSchema.extend({
  user: UserSchema,
  csrf_token: z.string(),
  token: z.string().optional(), // For backward compatibility
  expires_in_minutes: z.number().optional(),
  expires_at: z.string().optional(),
});

// Login Request Schema
export const LoginRequestSchema = z.object({
  email: z.string().email("Invalid email address"),
  password: z.string().min(1, "Password is required"),
  remember_me: z.boolean().optional(),
});

// Register Request Schema
export const RegisterRequestSchema = z
  .object({
    name: z.string().min(2, "Name must be at least 2 characters"),
    email: z.string().email("Invalid email address"),
    password: z.string().min(8, "Password must be at least 8 characters"),
    password_confirmation: z
      .string()
      .min(8, "Password confirmation is required"),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
  });
```

### Room Types

```typescript
// Room Status Enum
export const RoomStatusSchema = z.enum([
  "available",
  "booked",
  "maintenance",
  "cleaning",
]);

// Room Schema
export const RoomSchema = z.object({
  id: z.number(),
  name: z.string().min(1, "Room name is required"),
  description: z.string().optional(),
  price: z.number().positive("Price must be positive"),
  max_guests: z
    .number()
    .min(1, "At least 1 guest required")
    .max(10, "Maximum 10 guests"),
  status: RoomStatusSchema,
  image_url: z.string().url().optional(),
  amenities: z.array(z.string()).optional(),
  room_number: z.string().optional(),
  floor: z.number().optional(),
  size_sqm: z.number().positive().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
});

// Room Filters Schema
export const RoomFiltersSchema = z.object({
  status: RoomStatusSchema.optional(),
  min_price: z.number().positive().optional(),
  max_price: z.number().positive().optional(),
  min_guests: z.number().min(1).optional(),
  max_guests: z.number().min(1).optional(),
  amenities: z.array(z.string()).optional(),
});

// Rooms Response Schema
export const RoomsResponseSchema = ApiResponseSchema.extend({
  data: z.array(RoomSchema),
  meta: PaginationSchema.optional(),
});
```

### Booking Types

```typescript
// Booking Status Enum
export const BookingStatusSchema = z.enum([
  "pending",
  "confirmed",
  "checked_in",
  "checked_out",
  "cancelled",
  "no_show",
]);

// Guest Information Schema
export const GuestInfoSchema = z.object({
  name: z.string().min(1, "Guest name is required"),
  email: z.string().email("Invalid email address"),
  phone: z.string().optional(),
  nationality: z.string().optional(),
  date_of_birth: z.string().optional(),
});

// Booking Schema
export const BookingSchema = z.object({
  id: z.number(),
  room_id: z.number(),
  user_id: z.number().optional(),
  guest_info: GuestInfoSchema,
  check_in: z.string(), // ISO date string
  check_out: z.string(), // ISO date string
  guests: z.number().min(1, "At least 1 guest required"),
  status: BookingStatusSchema,
  total_price: z.number().positive(),
  currency: z.string().default("USD"),
  special_requests: z.string().optional(),
  payment_status: z
    .enum(["pending", "paid", "refunded", "cancelled"])
    .optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
});

// Booking Create Schema
export const BookingCreateSchema = z
  .object({
    room_id: z.number(),
    guest_name: z.string().min(1, "Guest name is required"),
    guest_email: z.string().email("Invalid email address"),
    guest_phone: z.string().optional(),
    check_in: z.string(), // ISO date string
    check_out: z.string(), // ISO date string
    guests: z.number().min(1),
    special_requests: z.string().optional(),
  })
  .refine(
    (data) => {
      const checkIn = new Date(data.check_in);
      const checkOut = new Date(data.check_out);
      return checkOut > checkIn;
    },
    {
      message: "Check-out must be after check-in",
      path: ["check_out"],
    }
  );

// Booking Update Schema
export const BookingUpdateSchema = BookingCreateSchema.partial();

// Bookings Response Schema
export const BookingsResponseSchema = ApiResponseSchema.extend({
  data: z.array(BookingSchema),
  meta: PaginationSchema.optional(),
});
```

### Payment Types

```typescript
// Payment Method Schema
export const PaymentMethodSchema = z.enum([
  "credit_card",
  "debit_card",
  "paypal",
  "bank_transfer",
  "cash",
]);

// Payment Schema
export const PaymentSchema = z.object({
  id: z.number(),
  booking_id: z.number(),
  amount: z.number().positive(),
  currency: z.string().default("USD"),
  method: PaymentMethodSchema,
  status: z.enum(["pending", "completed", "failed", "refunded"]),
  transaction_id: z.string().optional(),
  payment_date: z.string().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
});

// Payment Create Schema
export const PaymentCreateSchema = z.object({
  booking_id: z.number(),
  amount: z.number().positive(),
  method: PaymentMethodSchema,
  // Payment provider specific fields would go here
});
```

## 2. Component Types (`types/components.ts`)

### UI Component Props

```typescript
// types/components.ts
import { ReactNode, ButtonHTMLAttributes, InputHTMLAttributes } from "react";

// Button Component Props
export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary" | "secondary" | "danger" | "outline" | "ghost";
  size?: "sm" | "md" | "lg" | "icon";
  loading?: boolean;
  fullWidth?: boolean;
}

// Input Component Props
export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  helperText?: string;
  startIcon?: ReactNode;
  endIcon?: ReactNode;
}

// Card Component Props
export interface CardProps {
  children: ReactNode;
  className?: string;
  padding?: "none" | "sm" | "md" | "lg";
  shadow?: "none" | "sm" | "md" | "lg";
  rounded?: "none" | "sm" | "md" | "lg";
}

// Modal Component Props
export interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title?: string;
  children: ReactNode;
  size?: "sm" | "md" | "lg" | "xl";
  closeOnOverlayClick?: boolean;
}
```

### Layout Component Props

```typescript
// Header Props
export interface HeaderProps {
  showSearch?: boolean;
  showUserMenu?: boolean;
  transparent?: boolean;
}

// Navigation Props
export interface NavigationItem {
  label: string;
  href: string;
  icon?: ReactNode;
  children?: NavigationItem[];
  requiresAuth?: boolean;
}

export interface NavigationProps {
  items: NavigationItem[];
  orientation?: "horizontal" | "vertical";
}
```

### Form Component Props

```typescript
// Form Field Props
export interface FormFieldProps {
  name: string;
  label?: string;
  required?: boolean;
  error?: string;
  helperText?: string;
  className?: string;
}

// Date Picker Props
export interface DatePickerProps {
  value?: Date;
  onChange: (date: Date | null) => void;
  minDate?: Date;
  maxDate?: Date;
  placeholder?: string;
  disabled?: boolean;
  dateFormat?: string;
}

// Select Props
export interface SelectOption {
  value: string | number;
  label: string;
  disabled?: boolean;
}

export interface SelectProps {
  options: SelectOption[];
  value?: string | number;
  onChange: (value: string | number) => void;
  placeholder?: string;
  disabled?: boolean;
  multiple?: boolean;
}
```

## 3. Form Types (`types/forms.ts`)

### Form Data Types

```typescript
// types/forms.ts
import { z } from "zod";

// Login Form
export const LoginFormSchema = z.object({
  email: z.string().email("Invalid email address"),
  password: z.string().min(1, "Password is required"),
  remember_me: z.boolean().optional(),
});

export type LoginFormData = z.infer<typeof LoginFormSchema>;

// Register Form
export const RegisterFormSchema = z
  .object({
    name: z.string().min(2, "Name must be at least 2 characters"),
    email: z.string().email("Invalid email address"),
    password: z.string().min(8, "Password must be at least 8 characters"),
    password_confirmation: z
      .string()
      .min(8, "Password confirmation is required"),
    accept_terms: z.boolean().refine((val) => val === true, {
      message: "You must accept the terms and conditions",
    }),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
  });

export type RegisterFormData = z.infer<typeof RegisterFormSchema>;

// Booking Form
export const BookingFormSchema = z
  .object({
    room_id: z.number().min(1, "Please select a room"),
    guest_name: z.string().min(2, "Name must be at least 2 characters"),
    guest_email: z.string().email("Invalid email address"),
    guest_phone: z.string().optional(),
    check_in: z.date(),
    check_out: z.date(),
    guests: z.number().min(1).max(10),
    special_requests: z.string().optional(),
  })
  .refine((data) => data.check_out > data.check_in, {
    message: "Check-out must be after check-in",
    path: ["check_out"],
  });

export type BookingFormData = z.infer<typeof BookingFormSchema>;

// Contact Form
export const ContactFormSchema = z.object({
  name: z.string().min(2, "Name must be at least 2 characters"),
  email: z.string().email("Invalid email address"),
  subject: z.string().min(5, "Subject must be at least 5 characters"),
  message: z.string().min(10, "Message must be at least 10 characters"),
});

export type ContactFormData = z.infer<typeof ContactFormSchema>;
```

### Form State Types

```typescript
// Form State
export interface FormState<T> {
  data: T;
  errors: Record<string, string[]>;
  isSubmitting: boolean;
  isValid: boolean;
  touched: Record<string, boolean>;
}

// Form Hook Return Type
export interface UseFormReturn<T> {
  formState: FormState<T>;
  register: (name: keyof T) => {
    value: T[keyof T];
    onChange: (value: T[keyof T]) => void;
    onBlur: () => void;
    error?: string;
  };
  handleSubmit: (
    onSubmit: (data: T) => void | Promise<void>
  ) => (e: React.FormEvent) => void;
  reset: () => void;
  setValue: (name: keyof T, value: T[keyof T]) => void;
  setError: (name: keyof T, error: string) => void;
  clearErrors: () => void;
}
```

## 4. Generic Types (`types/common.ts`)

### Utility Types

```typescript
// types/common.ts
// API Response Types
export type ApiResponse<T = any> = {
  success: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type PaginatedResponse<T> = ApiResponse<T[]> & {
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
  };
};

// Form Types
export type FormErrors<T> = Partial<Record<keyof T, string | string[]>>;
export type FormTouched<T> = Partial<Record<keyof T, boolean>>;

// Component Types
export type ComponentSize = "sm" | "md" | "lg" | "xl";
export type ComponentVariant =
  | "primary"
  | "secondary"
  | "success"
  | "warning"
  | "danger";
export type ComponentStatus = "idle" | "loading" | "success" | "error";

// Data Types
export type SortDirection = "asc" | "desc";
export type SortField<T> = keyof T;

export interface SortConfig<T> {
  field: SortField<T>;
  direction: SortDirection;
}

export interface FilterConfig {
  [key: string]: any;
}

// Event Types
export type EventHandler<T = void> = (event: T) => void;
export type AsyncEventHandler<T = void> = (event: T) => Promise<void>;
```

### Domain-Specific Types

```typescript
// Business Logic Types
export type RoomStatus = "available" | "booked" | "maintenance" | "cleaning";
export type BookingStatus =
  | "pending"
  | "confirmed"
  | "checked_in"
  | "checked_out"
  | "cancelled"
  | "no_show";
export type PaymentStatus = "pending" | "paid" | "refunded" | "cancelled";
export type UserRole = "admin" | "manager" | "staff" | "user";

// Date Range Type
export interface DateRange {
  start: Date;
  end: Date;
}

// Price Type
export interface Price {
  amount: number;
  currency: string;
  formatted: string;
}

// Address Type
export interface Address {
  street: string;
  city: string;
  state: string;
  zip_code: string;
  country: string;
}
```

## 5. Type Exports (`types/index.ts`)

```typescript
// types/index.ts
// API Types
export * from "./api";

// Component Types
export * from "./components";

// Form Types
export * from "./forms";

// Common Types
export * from "./common";

// Re-export Zod for convenience
export { z } from "zod";
```

## Best Practices cho Types

### 1. Zod Schema Best Practices

- **Descriptive Messages**: Error messages rõ ràng cho users
- **Type Inference**: Sử dụng `z.infer<>` để derive types
- **Validation Chains**: Combine multiple validations
- **Optional Fields**: Sử dụng `.optional()` thay vì union types

### 2. TypeScript Best Practices

- **Strict Mode**: Enable strict type checking
- **Interface vs Type**: Interface cho objects, type cho unions
- **Generic Constraints**: Proper generic constraints
- **Discriminated Unions**: For complex state types

### 3. API Type Safety

- **Runtime Validation**: Validate all API responses
- **Type Guards**: Custom type guards cho runtime checks
- **Error Types**: Specific error types cho different scenarios

### 4. Component Types

- **Prop Types**: Comprehensive prop interfaces
- **Event Handlers**: Proper event handler types
- **Children Types**: ReactNode cho flexible children

### 5. Form Types

- **Validation Schemas**: Zod schemas cho form validation
- **Form State**: Consistent form state management types
- **Error Handling**: Structured error types

### 6. Testing Types

- **Mock Types**: Types cho testing mocks
- **Factory Functions**: Helper functions to create test data
- **Assertion Types**: Types cho test assertions
