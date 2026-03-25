# 🎨 Soleil Hostel Frontend

> **Last Updated:** January 4, 2026 | **React:** 19.0.0 | **TypeScript:** 5.x

## 🎯 Overview

The Soleil Hostel frontend is a **modern web application** built with React 19 and TypeScript, featuring a feature-sliced architecture for maximum maintainability and scalability.

### Key Features

- ✅ **Modern Stack**: React 19 + TypeScript + Vite
- ✅ **Feature-Sliced Design**: Modular architecture for scalability
- ✅ **Authentication**: Dual-mode (Bearer Token + HttpOnly Cookie)
- ✅ **Professional UI**: shadcn/ui-inspired component library
- ✅ **Responsive Design**: Mobile-first approach with TailwindCSS
- ✅ **Type Safety**: Full TypeScript coverage with strict mode
- ✅ **Performance**: Code splitting with React.lazy + Suspense
- ✅ **Accessibility**: WCAG 2.1 Level AA compliant
- ✅ **Testing**: Playwright E2E tests ready

---

## 🚀 Quick Start

### Prerequisites

- Node.js 18+ or higher
- npm or yarn

### Installation

```bash
# 1. Install dependencies
npm ci

# 2. Configure environment (optional)
# Create .env file if needed for custom API URL
echo "VITE_API_URL=http://127.0.0.1:8000" > .env

# 3. Start development server
npm run dev
# Or with specific port:
npx vite --port 5173
```

Frontend will be available at: http://localhost:5173

### Build for Production

```bash
# Build optimized production bundle
npm run build

# Preview production build
npm run preview
```

---

## 📂 Project Structure

```
frontend/
├── public/              # Static assets
├── src/
│   ├── app/             # Application core
│   │   ├── App.tsx      # Root component with layout
│   │   ├── providers.tsx # AuthProvider wrapper
│   │   └── router.tsx   # React Router v7 with lazy loading
│   │
│   ├── features/        # Feature modules (business logic)
│   │   ├── auth/        # Authentication feature
│   │   ├── rooms/       # Room management feature
│   │   └── booking/     # Booking feature
│   │
│   ├── shared/          # Shared resources
│   │   ├── components/  # Reusable components
│   │   ├── lib/         # Library configurations
│   │   └── utils/       # Utility functions
│   │
│   ├── pages/           # Page components
│   └── main.tsx         # Application entry point
│
├── tests/               # E2E tests (Playwright)
├── package.json         # Dependencies & scripts
├── vite.config.ts       # Vite configuration
└── tsconfig.json        # TypeScript configuration
```

---

## 📚 Documentation

Full documentation available in the `docs/` folder:

- [Frontend Architecture](../docs/frontend/ARCHITECTURE.md)
- [App Layer](../docs/frontend/APP_LAYER.md)
- [Features Layer](../docs/frontend/FEATURES_LAYER.md)
- [Testing Guide](../docs/frontend/TESTING.md)
- [Performance & Security](../docs/frontend/PERFORMANCE_SECURITY.md)
- [Deployment Guide](../docs/frontend/DEPLOYMENT.md)

---

**Frontend URL**: http://localhost:5173
**Backend API**: http://127.0.0.1:8000
