# Shared Layer (`src/shared/`)

> Reusable UI components and utility libraries

## Overview

```text
src/shared/
├── components/
│   ├── ErrorBoundary.tsx       # Global error boundary
│   ├── feedback/
│   │   └── LoadingSpinner.tsx  # Loading states
│   ├── layout/
│   │   ├── Header.tsx          # Navigation header
│   │   └── Footer.tsx          # Site footer
│   └── ui/
│       ├── Button.tsx          # Button with variants (+test)
│       ├── Card.tsx            # Card container
│       ├── Input.tsx           # Form input (+test)
│       ├── Label.tsx           # Form label
│       ├── Skeleton.tsx        # Loading skeleton
│       └── SkeletonCard.tsx    # Card skeleton placeholder
├── lib/
│   ├── api.ts                  # Axios client (+test)
│   └── navigation.ts          # Programmatic navigation
└── utils/
    ├── csrf.ts                 # CSRF token management (+test)
    └── security.ts             # XSS sanitization (+test)
```

---

## 1. UI Components (`shared/components/ui/`)

### Button

```typescript
interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  children: React.ReactNode
}
```

- 5 variants with distinct Tailwind styles
- Loading state with inline SVG spinner
- Disabled state management

### Input

```typescript
interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
}
```

- Label rendering with `htmlFor` linkage
- Error message display in red
- Accessible with auto-generated IDs

### Card

Compound component pattern with sub-components:

- `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `CardFooter`

### Other UI Components

| Component      | Purpose                         |
| -------------- | ------------------------------- |
| `Label`        | Form label with styling         |
| `Skeleton`     | Loading placeholder animation   |
| `SkeletonCard` | Full card loading placeholder   |

---

## 2. Layout Components (`shared/components/layout/`)

### Header

- Sticky navigation with logo, nav links, auth buttons
- Mobile hamburger menu with slide-down panel
- Active route highlighting via `useLocation()`
- Auth-aware: shows Login/Register or user name + Logout
- Uses `useAuth()` hook from AuthContext
- Uses custom `Button` component

### Footer

- Site footer with copyright and navigation links
- Links to Privacy Policy, Terms of Service, Contact

---

## 3. Feedback Components (`shared/components/feedback/`)

### LoadingSpinner

```typescript
interface LoadingSpinnerProps {
  size?: 'sm' | 'md' | 'lg' | 'xl'
  fullScreen?: boolean
  message?: string
}
```

- CSS animation spinner (border-based)
- Optional full-screen overlay mode
- Optional loading message text

### ErrorBoundary

Class component at `shared/components/ErrorBoundary.tsx`:

- Catches React rendering errors
- Displays fallback UI with reload button
- Logs errors to console

---

## 4. Library (`shared/lib/`)

### api.ts

Shared Axios client with CSRF and token refresh. See [SERVICES_LAYER.md](SERVICES_LAYER.md) for details.

### navigation.ts

Programmatic navigation service for use outside React components. See [SERVICES_LAYER.md](SERVICES_LAYER.md) for details.

---

## 5. Utilities (`shared/utils/`)

### csrf.ts

CSRF token management (`getCsrfToken`, `setCsrfToken`, `clearCsrfToken`). See [SERVICES_LAYER.md](SERVICES_LAYER.md) for details.

### security.ts

XSS sanitization and URL validation utilities:

- Input sanitization for user-provided content
- URL validation for safe redirects
- Tested with 22 unit tests

---

## 6. Key Design Decisions

- **No class-variance-authority (cva)**: Button variants use plain Tailwind class objects
- **No clsx/tailwind-merge**: Class concatenation uses template literals
- **No lucide-react**: SVG icons are inline
- **No date-fns**: Not installed; date handling is in feature-level code
- **Component tests**: Button (12 tests) and Input (10 tests) have dedicated test files
