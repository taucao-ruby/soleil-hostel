# 🚀 Environment Setup Guide

> Complete guide to set up Soleil Hostel development environment

## Prerequisites

| Tool       | Version | Installation                               |
| ---------- | ------- | ------------------------------------------ |
| PHP        | 8.3+    | [php.net](https://php.net)                 |
| Composer   | 2.x     | [getcomposer.org](https://getcomposer.org) |
| Node.js    | 20+     | [nodejs.org](https://nodejs.org)           |
| PostgreSQL | 15+     | [postgresql.org](https://postgresql.org)   |
| Redis      | 7+      | Docker or native                           |

---

## Quick Setup (5 minutes)

### 1. Clone Repository

```bash
git clone <repo-url>
cd soleil-hostel
```

### 2. Backend Setup

```bash
cd backend

# Install dependencies
composer install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Start server
php artisan serve
```

### 3. Frontend Setup

```bash
cd frontend

# Install dependencies
npm install

# Start dev server
npm run dev
```

### 4. Start Redis (Docker)

```bash
cd soleil-hostel
docker-compose up -d redis
```

---

## Environment Variables

### Backend (.env)

```env
# App
APP_NAME="Soleil Hostel"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=soleil_hostel
DB_USERNAME=postgres
DB_PASSWORD=secret

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

### Frontend (.env)

```env
VITE_API_URL=http://localhost:8000/api
```

---

## Database Setup

### PostgreSQL

```bash
# Create database
createdb soleil_hostel

# Run migrations
php artisan migrate

# Seed test data
php artisan db:seed
```

### SQLite (Testing)

Tests use in-memory SQLite by default. No setup required.

```bash
php artisan test
```

---

## Docker Setup (Alternative)

```bash
# Start all services
docker-compose up -d

# Backend at http://localhost:8000
# Frontend at http://localhost:5173
# PostgreSQL at localhost:5432
# Redis at localhost:6379
```

---

## IDE Setup

### VS Code Extensions

```json
{
  "recommendations": [
    "bmewburn.vscode-intelephense-client",
    "bradlc.vscode-tailwindcss",
    "dbaeumer.vscode-eslint",
    "esbenp.prettier-vscode"
  ]
}
```

### PHPStorm

1. Enable Laravel plugin
2. Configure PHP interpreter (8.3+)
3. Set up PHPUnit configuration

---

## Troubleshooting

### Redis Connection Failed

```bash
# Check Redis is running
docker ps | grep redis

# Or start it
docker-compose up -d redis
```

### Database Connection Refused

```bash
# Check PostgreSQL is running
pg_isready -h localhost -p 5432

# Create database if missing
createdb soleil_hostel
```

### CORS Errors

Check `SANCTUM_STATEFUL_DOMAINS` includes your frontend URL:

```env
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:3000
```

### Vite HMR Not Working

Ensure Vite dev server is running:

```bash
cd frontend && npm run dev
```

---

## Next Steps

- [Testing Guide](./TESTING.md)
- [API Reference](../architecture/API.md)
- [Feature Documentation](../features/README.md)
