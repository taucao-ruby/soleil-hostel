# 🖥️ Frontend Documentation

> React 19 + TypeScript + Vite + TailwindCSS - Production-Ready Hostel Booking Platform

## Quick Navigation

| Document                                               | Description                                        |
| ------------------------------------------------------ | -------------------------------------------------- |
| [ARCHITECTURE_OVERVIEW.md](./ARCHITECTURE_OVERVIEW.md) | Tổng quan kiến trúc và nguyên tắc thiết kế         |
| [APP_LAYER.md](./APP_LAYER.md)                         | App Layer - Cấu hình ứng dụng cốt lõi              |
| [FEATURES_LAYER.md](./FEATURES_LAYER.md)               | Features Layer - Logic nghiệp vụ                   |
| [SERVICES_LAYER.md](./SERVICES_LAYER.md)               | Services Layer - API và authentication             |
| [SHARED_LAYER.md](./SHARED_LAYER.md)                   | Shared Layer - Components và utilities tái sử dụng |
| [TYPES_LAYER.md](./TYPES_LAYER.md)                     | Types Layer - Định nghĩa types và schemas          |
| [UTILS_LAYER.md](./UTILS_LAYER.md)                     | Utils Layer - Utilities và constants               |
| [CONFIGURATION.md](./CONFIGURATION.md)                 | Configuration Files - Cấu hình build và dev tools  |
| [TESTING.md](./TESTING.md)                             | Testing Configuration - E2E testing với Playwright |
| [PERFORMANCE_SECURITY.md](./PERFORMANCE_SECURITY.md)   | Performance & Security Best Practices              |
| [DEPLOYMENT.md](./DEPLOYMENT.md)                       | Deployment & DevOps - Docker, CI/CD                |

---

## Tech Stack

| Component     | Technology       | Version | Purpose                                                  |
| ------------- | ---------------- | ------- | -------------------------------------------------------- |
| UI Framework  | React            | 19      | Modern component-based UI with concurrent features       |
| Language      | TypeScript       | 5.x     | Type-safe development with strict compilation            |
| Build Tool    | Vite             | 6.x     | Fast HMR, optimized bundling, and modern tooling         |
| Styling       | TailwindCSS      | 3.x     | Utility-first CSS framework for rapid UI development     |
| HTTP Client   | Axios            | 1.x     | Robust HTTP client with interceptors and error handling  |
| Routing       | React Router     | 7.x     | Declarative routing with nested layouts and lazy loading |
| Forms         | React Hook Form  | -       | Efficient form handling (used in features)               |
| Validation    | Zod              | 4.x     | Runtime type validation and schema parsing               |
| Animation     | Framer Motion    | 12.x    | Smooth animations and transitions                        |
| Date Picker   | React DatePicker | 9.x     | Accessible date selection component                      |
| Notifications | React Toastify   | 11.x    | User feedback and toast notifications                    |
| i18n          | react-i18next    | 16.x    | Internationalization support                             |
| Testing       | Playwright       | Latest  | E2E testing framework                                    |
| Linting       | ESLint           | 9.x     | Code quality and consistency                             |
| Formatting    | Prettier         | 3.x     | Code formatting automation                               |

---

## Project Structure

```
frontend/
├── src/
│   ├── app/                # Application core & configuration
│   │   ├── App.tsx         # Root component with error boundaries
│   │   ├── providers.tsx   # Context providers (Auth, future: Theme, i18n)
│   │   ├── router.tsx      # React Router v7 configuration with lazy loading
│   │   └── Layout.tsx      # Main layout with header/footer
│   ├── assets/             # Static assets (images, icons)
│   ├── features/           # Feature-sliced business logic
│   │   ├── auth/           # Authentication (login, register, context)
│   │   ├── booking/        # Booking management (form, validation)
│   │   └── rooms/          # Room listing and details
│   ├── pages/              # Page components (HomePage)
│   ├── services/           # API services and HTTP clients
│   ├── shared/             # Shared utilities and components
│   │   ├── components/     # Reusable UI components
│   │   ├── lib/            # Utility functions
│   │   ├── styles/         # Global styles and Tailwind config
│   │   └── utils/          # Helper functions
│   ├── types/              # TypeScript type definitions
│   └── utils/              # Core utilities (web vitals, etc.)
├── tests/                  # E2E tests (Playwright)
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
npm install

# Start development server with HMR
npm run dev

# Build for production
npm run build

# Run E2E tests
npm run test

# Lint and format code
npm run lint
npm run format
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

- **Interactive Booking Form**: Date pickers with validation
- **Guest Information**: Comprehensive booking details
- **Real-time Validation**: Zod schema validation
- **Booking Status Tracking**: Pending, confirmed, completed

### 🎨 UI/UX Excellence

- **Professional Design System**: Consistent typography and spacing
- **Mobile-First Responsive**: Optimized for all devices
- **Accessibility Compliant**: WCAG 2.1 Level AA standards
- **Smooth Animations**: Framer Motion transitions
- **Dark/Light Mode Ready**: Theme provider architecture

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
- **Input Sanitization**: Zod validation on all inputs

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
| `npm run dev`      | Start dev server with HMR           |
| `npm run build`    | Production build with optimizations |
| `npm run preview`  | Preview production build locally    |
| `npm run lint`     | Run ESLint code quality checks      |
| `npm run lint:fix` | Auto-fix ESLint issues              |
| `npm run format`   | Format code with Prettier           |
| `npm run test`     | Run Playwright E2E tests            |

---

## Deployment

### Production Build

```bash
npm run build
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
npm run dev:docker  # Docker development environment
```

---

## Testing Strategy

### E2E Testing with Playwright

- **User Journey Tests**: Complete booking flows
- **Authentication Tests**: Login/logout scenarios
- **Cross-browser Testing**: Chrome, Firefox, Safari
- **Mobile Testing**: Responsive design validation

### Component Testing (Future)

- **Unit Tests**: Individual component logic
- **Integration Tests**: Component interactions
- **Visual Regression**: UI consistency checks

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
rm -rf node_modules package-lock.json
npm install
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
npm run build
```

---

## Contributing

1. **Branch Strategy**: Feature branches from `dev`
2. **Code Reviews**: Required for all PRs
3. **Testing**: E2E tests must pass
4. **Documentation**: Update docs for API changes

---

## Future Enhancements

- **React Query Integration**: Server state management
- **PWA Features**: Offline support and caching
- **Advanced Analytics**: User behavior tracking
- **Multi-language Support**: Full i18n implementation
- **Admin Dashboard**: Management interface
- **Real-time Updates**: WebSocket integration

## Available Scripts

| Command            | Description                         |
| ------------------ | ----------------------------------- |
| `npm run dev`      | Start dev server with HMR           |
| `npm run build`    | Production build with optimizations |
| `npm run preview`  | Preview production build locally    |
| `npm run lint`     | Run ESLint code quality checks      |
| `npm run lint:fix` | Auto-fix ESLint issues              |
| `npm run format`   | Format code with Prettier           |
| `npm run test`     | Run Playwright E2E tests            |
