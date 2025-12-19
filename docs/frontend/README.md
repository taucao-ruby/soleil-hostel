# 🖥️ Frontend Documentation

> React 19 + TypeScript + Vite + TailwindCSS

## Quick Navigation

| Document                             | Description           |
| ------------------------------------ | --------------------- |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Frontend architecture |
| [COMPONENTS.md](./COMPONENTS.md)     | Component library     |
| [API_CLIENT.md](./API_CLIENT.md)     | API integration       |
| [TESTING.md](./TESTING.md)           | Frontend testing      |

---

## Tech Stack

| Component    | Technology  | Version |
| ------------ | ----------- | ------- |
| UI Framework | React       | 19      |
| Language     | TypeScript  | 5.x     |
| Build Tool   | Vite        | 6.x     |
| Styling      | TailwindCSS | 4.x     |
| HTTP Client  | Axios       | 1.x     |
| State        | React Query | 5.x     |
| Testing      | Playwright  | Latest  |

---

## Project Structure

```
frontend/
├── src/
│   ├── app/                # App configuration
│   ├── assets/             # Static assets
│   ├── features/           # Feature modules
│   │   ├── auth/           # Authentication
│   │   ├── booking/        # Booking management
│   │   └── rooms/          # Room listing
│   ├── lib/                # Core utilities
│   ├── pages/              # Page components
│   ├── services/           # API services
│   ├── shared/             # Shared components
│   ├── types/              # TypeScript types
│   └── main.tsx            # Entry point
├── tests/                  # E2E tests (Playwright)
├── public/                 # Public assets
└── package.json
```

---

## Quick Start

```bash
cd frontend

# Install dependencies
npm install

# Start development server
npm run dev

# Build for production
npm run build

# Run tests
npm run test
```

---

## Key Features

### Authentication

- HttpOnly Cookie support (XSS-safe)
- Auto token refresh
- CSRF protection

### API Integration

- Axios with interceptors
- Auto error handling
- Loading states with React Query

### Security

- CSP nonce injection
- Secure cookie handling
- Input sanitization

---

## Environment Variables

```env
VITE_API_URL=http://localhost:8000
VITE_APP_NAME="Soleil Hostel"
```

---

## Available Scripts

| Command           | Description              |
| ----------------- | ------------------------ |
| `npm run dev`     | Start dev server         |
| `npm run build`   | Build for production     |
| `npm run lint`    | Run ESLint               |
| `npm run test`    | Run Playwright tests     |
| `npm run preview` | Preview production build |
