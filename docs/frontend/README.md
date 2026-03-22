# 🖥️ Frontend Documentation

> React 19 + TypeScript + Vite + TailwindCSS - Production-Ready Hostel Booking Platform

## Quick Navigation

| Document                                               | Description                                        |
| ------------------------------------------------------ | -------------------------------------------------- |
| [ARCHITECTURE.md](./ARCHITECTURE.md)                   | Architecture overview and design principles        |
| [APP_LAYER.md](./APP_LAYER.md)                         | App shell - routing, layout, error boundary        |
| [FEATURES_LAYER.md](./FEATURES_LAYER.md)               | Business features - auth, booking, rooms, locations|
| [SERVICES_LAYER.md](./SERVICES_LAYER.md)               | API client, authentication, CSRF protection        |
| [SHARED_LAYER.md](./SHARED_LAYER.md)                   | Reusable UI components and utility libraries       |
| [TYPES_LAYER.md](./TYPES_LAYER.md)                     | TypeScript interfaces and type definitions         |
| [UTILS_LAYER.md](./UTILS_LAYER.md)                     | Toast notifications and web vitals monitoring      |
| [CONFIGURATION.md](./CONFIGURATION.md)                 | Build config - Vite, TypeScript, Tailwind, ESLint  |
| [TESTING.md](./TESTING.md)                             | Testing - Vitest unit + Playwright E2E             |
| [PERFORMANCE_SECURITY.md](./PERFORMANCE_SECURITY.md)   | Performance & security practices                   |
| [RBAC.md](./RBAC.md)                                   | Frontend role-based access control model           |
| [DEPLOYMENT.md](./DEPLOYMENT.md)                       | Deployment & DevOps - Docker, CI/CD                |

---

## Tech Stack

| Component     | Technology     | Version | Purpose                                                  |
| ------------- | -------------- | ------- | -------------------------------------------------------- |
| UI Framework  | React          | 19      | Modern component-based UI with concurrent features       |
| Language      | TypeScript     | 5.x     | Type-safe development with strict compilation            |
| Build Tool    | Vite           | 6.x     | Fast HMR, optimized bundling, and modern tooling         |
| Styling       | TailwindCSS    | 3.x     | Utility-first CSS framework for rapid UI development     |
| HTTP Client   | Axios          | 1.x     | Robust HTTP client with interceptors and error handling  |
| Routing       | React Router   | 7.x     | Declarative routing with nested layouts and lazy loading |
| Notifications | React Toastify | 11.x    | User feedback and toast notifications                    |
| Metrics       | Web Vitals     | 5.x     | Core Web Vitals performance monitoring                   |
| Unit Testing  | Vitest         | Latest  | Unit and integration testing framework                   |
| E2E Testing   | Playwright     | Latest  | End-to-end testing (scaffolded)                          |
| Linting       | ESLint         | 9.x     | Code quality and consistency                             |
| Formatting    | Prettier       | 3.x     | Code formatting automation                               |

---

## Project Structure

```
frontend/
├── src/
│   ├── app/                # Application core & configuration
│   │   ├── App.tsx         # Root component with error boundaries
│   │   ├── router.tsx      # React Router v7 with lazy loading + AuthLayout
│   │   └── Layout.tsx      # Main layout with header/footer
│   ├── features/           # Feature-sliced business logic
│   │   ├── auth/           # Authentication (login, register, context)
│   │   ├── booking/        # Booking management (form, validation)
│   │   ├── locations/      # Location listing and details
│   │   └── rooms/          # Room listing and details
│   ├── pages/              # Page components (HomePage, NotFoundPage)
│   ├── shared/             # Shared utilities and components
│   │   ├── components/     # Reusable UI components (ui/, layout/, feedback/)
│   │   ├── lib/            # API client and navigation helper
│   │   └── utils/          # CSRF and security utilities
│   ├── types/              # TypeScript type definitions
│   ├── utils/              # Core utilities (toast, web vitals)
│   └── test/               # Test setup configuration
├── tests/                  # E2E tests (Playwright, scaffolded)
├── public/                 # Public assets served by Vite
└── package.json
```

---

## Architecture Overview

### Feature-Sliced Design

The application follows **Feature-Sliced Architecture** principles:

- **Features**: Self-contained business logic modules (auth, booking, rooms)
- **Shared**: Reusable components and utilities
- **App**: Application-wide configuration and routing

### Key Architectural Decisions

- **React 19**: Latest concurrent features for better performance
- **TypeScript Strict**: Zero-tolerance for type errors
- **Lazy Loading**: Code splitting for optimal bundle sizes
- **Error Boundaries**: Graceful error handling with fallbacks
- **HttpOnly Cookies**: Secure authentication without client-side token storage

---

## Quick Start

```bash
cd frontend

# Install dependencies
pnpm install

# Start development server with HMR
pnpm dev

# Build for production
pnpm build

# Run tests
pnpm test

# Lint and format code
pnpm lint
pnpm format
```

---

## Core Features

### 🔐 Authentication System

- **HttpOnly Cookie Authentication**: XSS-safe token storage
- **Auto Token Refresh**: Seamless session management
- **CSRF Protection**: Laravel Sanctum integration
- **Protected Routes**: Automatic redirects for unauthorized access

### 🏠 Room Management

- **Dynamic Room Listing**: Real-time availability display
- **Rich Room Details**: Images, descriptions, pricing
- **Status Management**: Available, booked, maintenance states

### 📅 Booking System

- **Interactive Booking Form**: Date inputs with validation
- **Guest Information**: Comprehensive booking details
- **Real-time Validation**: Custom validation functions
- **Booking Status Tracking**: Pending, confirmed, cancelled

### 📊 Dashboard System

- **Guest Dashboard**: Booking list with filter tabs (All/Upcoming/Past), cancel with confirm dialog
- **Admin Dashboard**: 3-tab view (Bookings / Trashed / Contacts), lazy-fetch per tab
- **Role-Based Routing**: `user.role === 'admin'` → AdminDashboard, else GuestDashboard
- **Toast Notifications**: Success/error feedback on cancellation

### 🎨 UI/UX Excellence

- **Professional Design System**: Consistent typography and spacing
- **Mobile-First Responsive**: Optimized for all devices
- **Accessibility Compliant**: WCAG 2.1 Level AA standards
- **Loading States**: Skeleton cards and spinners
- **Error Boundaries**: Graceful error recovery

---

## Security Features

### Content Security Policy (CSP)

- **Nonce Injection**: Vite plugin for dynamic CSP headers
- **Strict CSP**: Prevents XSS attacks in production
- **Development Fallback**: Relaxed policies for HMR

### Authentication Security

- **HttpOnly Cookies**: Server-side token storage
- **CSRF Tokens**: Laravel Sanctum protection
- **Secure Headers**: XSS and injection prevention
- **Input Sanitization**: Custom validation on all inputs

### API Security

- **Request Interceptors**: Automatic CSRF token injection
- **Response Validation**: Runtime type checking
- **Error Handling**: Secure error responses
- **Rate Limiting**: Backend-enforced request limits

---

## Performance Optimizations

### Build Optimizations

- **Code Splitting**: React.lazy for route-based splitting
- **Tree Shaking**: Automatic dead code elimination
- **Asset Optimization**: Terser minification and compression
- **Manual Chunking**: Vendor separation for caching

### Runtime Performance

- **React 19 Concurrent**: Automatic batching and prioritization
- **Suspense Boundaries**: Progressive loading states
- **Memoization**: Optimized re-renders
- **Web Vitals Monitoring**: Performance tracking in production

### Development Experience

- **Hot Module Replacement**: Instant updates during development
- **TypeScript Compilation**: Real-time error checking
- **ESLint Integration**: Code quality enforcement
- **Prettier Formatting**: Consistent code style

---

## Environment Variables

```env
# API Configuration
VITE_API_URL=http://localhost:8000/api

# Application Settings
VITE_APP_NAME="Soleil Hostel"
VITE_APP_ENV=development

# Optional: Analytics, Monitoring
VITE_ANALYTICS_ID=your_analytics_id
```

---

## Available Scripts

| Command            | Description                         |
| ------------------ | ----------------------------------- |
| `pnpm dev`         | Start dev server with HMR           |
| `pnpm build`       | Production build with optimizations |
| `pnpm preview`     | Preview production build locally    |
| `pnpm lint`        | Run ESLint code quality checks      |
| `pnpm lint:fix`    | Auto-fix ESLint issues              |
| `pnpm format`      | Format code with Prettier           |
| `pnpm test`        | Run Vitest unit tests               |

---

## Deployment

### Production Build

```bash
pnpm build
```

- Outputs optimized bundles to `dist/`
- Generates manifest for Laravel asset management
- Includes CSP nonce placeholders for server injection

### Laravel Integration

- Static assets served from `backend/public/build/`
- Hot reloading support during development
- Environment-specific configuration

### Docker Support

```bash
pnpm dev:docker  # Docker development environment
```

---

## Testing Strategy

### Unit & Integration Testing (Vitest + Testing Library)

- **Component Tests**: 24 test files, 236 tests passing
- **Auth Tests**: Login, register, context lifecycle
- **Booking Tests**: Form validation, date logic, view model
- **Dashboard Tests**: GuestDashboard, AdminDashboard, DashboardPage
- **Home Tests**: HomePage, SearchCard, FilterChips
- **UI Tests**: Button, Input component behavior
- **Security Tests**: CSRF, XSS sanitization, API interceptors
- **Location Tests**: Nav link rendering

### E2E Testing (Playwright - Scaffolded)

- **Booking Flows**: End-to-end booking spec
- **Status**: Requires running application for execution

---

## Development Guidelines

### Code Quality

- **TypeScript Strict**: All type errors must be resolved
- **ESLint Rules**: Airbnb config with React extensions
- **Prettier**: Consistent formatting across team
- **Commit Standards**: Conventional commits

### Performance Budgets

- **Bundle Size**: < 500KB gzipped
- **First Paint**: < 1.5s
- **Lighthouse Score**: > 90
- **Accessibility**: WCAG 2.1 AA compliance

### Browser Support

- **Modern Browsers**: Chrome, Firefox, Safari, Edge
- **Mobile Browsers**: iOS Safari, Chrome Mobile
- **ES2020+**: Modern JavaScript features
- **Progressive Enhancement**: Graceful degradation

---

## Troubleshooting

### Common Issues

**Build Errors**

```bash
# Clear node_modules and reinstall
rm -rf node_modules pnpm-lock.yaml
pnpm install
```

**HMR Not Working**

- Check `backend/public/hot` file exists
- Verify Vite dev server is running on port 5173
- Ensure Laravel is serving from correct host

**API Connection Issues**

- Verify `VITE_API_URL` environment variable
- Check CORS configuration in Laravel
- Ensure backend is running on port 8000

**TypeScript Errors**

```bash
# Regenerate type definitions
pnpm build
```

---

## Contributing

1. **Branch Strategy**: Feature branches from `dev`
2. **Code Reviews**: Required for all PRs
3. **Testing**: E2E tests must pass
4. **Documentation**: Update docs for API changes

---

## Future Enhancements

- **PWA Features**: Offline support and caching
- **Advanced Analytics**: User behavior tracking
- **Multi-language Support**: Full i18n implementation
- **Real-time Updates**: WebSocket integration
