# Tổng quan Kiến trúc Frontend

> Chi tiết kiến trúc và tài liệu cho từng phần của frontend React 19 + TypeScript

## Feature-Sliced Architecture

Frontend sử dụng **Feature-Sliced Design (FSD)** - một kiến trúc hiện đại để tổ chức code:

```
src/
 app/           # Lớp ứng dụng - cấu hình global
 features/      # Lớp tính năng - logic nghiệp vụ
 shared/        # Lớp chia sẻ - components và utils tái sử dụng
 types/         # Lớp types - định nghĩa types và schemas
 utils/         # Lớp utilities - helpers và constants
```

### Nguyên tắc Thiết kế

- **Separation of Concerns**: Mỗi layer có trách nhiệm riêng biệt
- **Dependency Direction**: Các layer cao không phụ thuộc vào layer thấp
- **Reusability**: Shared components có thể tái sử dụng
- **Testability**: Mỗi feature có thể test độc lập

### Layers Chi tiết

#### 1. App Layer (`src/app/`)

- **Mục đích**: Cấu hình ứng dụng cốt lõi
- **Nội dung**: Root component, providers, router, layout
- **Dependencies**: Có thể import từ tất cả layers

#### 2. Features Layer (`src/features/`)

- **Mục đích**: Logic nghiệp vụ theo feature
- **Cấu trúc**: Mỗi feature có UI, API, types riêng
- **Dependencies**: Có thể import từ shared, types, utils

#### 3. Shared Layer (`src/shared/`)

- **Mục đích**: Components và utilities tái sử dụng
- **Cấu trúc**: components/ui, components/layout, lib, utils
- **Dependencies**: Không phụ thuộc vào app hoặc features

#### 4. Types Layer (`src/types/`)

- **Mục đích**: Định nghĩa TypeScript types và Zod schemas
- **Nội dung**: API types, component props, business entities
- **Dependencies**: Không có dependencies

#### 5. Utils Layer (`src/utils/`)

- **Mục đích**: Helper functions và constants
- **Nội dung**: Date utils, form utils, constants, web vitals
- **Dependencies**: Chỉ external libraries

## Tech Stack Chi tiết

| Component    | Technology       | Version | Mục đích                                   |
| ------------ | ---------------- | ------- | ------------------------------------------ |
| UI Framework | React            | 19      | Component-based UI với concurrent features |
| Language     | TypeScript       | 5.x     | Type-safe development                      |
| Build Tool   | Vite             | 6.x     | Fast HMR và optimized bundling             |
| Styling      | TailwindCSS      | 3.x     | Utility-first CSS framework                |
| HTTP Client  | Axios            | 1.x     | HTTP client với interceptors               |
| Routing      | React Router     | 7.x     | Declarative routing với lazy loading       |
| Validation   | Zod              | 4.x     | Runtime type validation                    |
| Animation    | Framer Motion    | 12.x    | Smooth animations                          |
| Date Picker  | React DatePicker | 9.x     | Accessible date selection                  |
| Testing      | Playwright       | Latest  | E2E testing framework                      |

## Security & Performance

### Authentication Flow

- **HttpOnly Cookies**: XSS protection
- **CSRF Tokens**: Cross-site request forgery protection
- **Auto Token Refresh**: Seamless user experience
- **Secure Logout**: Clear all sessions

### Performance Optimizations

- **Code Splitting**: React.lazy cho routes
- **Bundle Optimization**: Vendor chunk separation
- **Image Optimization**: WebP format và lazy loading
- **Web Vitals**: Core Web Vitals monitoring

### Content Security Policy

- **Nonce Injection**: Inline scripts/styles protection
- **Strict CSP Headers**: Server-side enforcement
- **Fallback Meta Tags**: Development support

## Development Workflow

### Code Organization

- **Feature-First**: Code organized by business features
- **Type Safety**: 100% TypeScript coverage
- **Validation**: Zod schemas cho tất cả data
- **Testing**: E2E tests với Playwright

### Build & Deployment

- **Docker**: Containerized deployment
- **CI/CD**: Automated testing và deployment
- **Environment Management**: Separate configs cho dev/staging/prod
- **Bundle Analysis**: Performance monitoring

## File Structure Overview

```
frontend/
├── docs/                 # Documentation
│   ├── README.md        # Main documentation index
│   ├── architecture-overview.md
│   ├── app-layer.md
│   ├── features-layer.md
│   ├── services-layer.md
│   ├── shared-layer.md
│   ├── types-layer.md
│   ├── utils-layer.md
│   ├── configuration.md
│   ├── testing.md
│   ├── performance-security.md
│   └── deployment.md
├── src/
│   ├── app/             # Application core
│   ├── features/        # Business features
│   ├── shared/          # Reusable components
│   ├── types/           # Type definitions
│   └── utils/           # Utilities
├── tests/               # E2E tests
├── public/              # Static assets
├── index.html           # HTML template
├── package.json         # Dependencies
├── vite.config.ts       # Build config
├── tsconfig.json        # TypeScript config
├── tailwind.config.js   # Styling config
└── playwright.config.ts # Testing config
```

## Next Steps

Để hiểu chi tiết từng phần, hãy xem các tài liệu chuyên biệt:

- [App Layer](./app-layer.md) - Cấu hình ứng dụng cốt lõi
- [Features Layer](./features-layer.md) - Logic nghiệp vụ
- [Services Layer](./services-layer.md) - API và authentication
- [Shared Layer](./shared-layer.md) - Components tái sử dụng
- [Types Layer](./types-layer.md) - Type definitions
- [Utils Layer](./utils-layer.md) - Helper functions
- [Configuration](./configuration.md) - Build và dev tools
- [Testing](./testing.md) - E2E testing
- [Performance & Security](./performance-security.md) - Best practices
- [Deployment](./deployment.md) - Docker và CI/CD
