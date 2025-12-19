# 🐳 Docker & Deployment

> Docker setup and deployment guide for Soleil Hostel

## Docker Compose Services

| Service  | Image          | Port | Purpose               |
| -------- | -------------- | ---- | --------------------- |
| backend  | Laravel 11     | 8000 | API server            |
| frontend | Vite + React   | 5173 | Web application       |
| db       | MySQL 8.0      | 3306 | Primary database      |
| redis    | Redis 7 Alpine | 6379 | Cache, queue, session |

---

## Quick Start

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f backend

# Stop all services
docker-compose down

# Rebuild after changes
docker-compose up -d --build
```

---

## Environment Variables

```bash
# .env (root directory)

# Database
MYSQL_ROOT_PASSWORD=root
MYSQL_DATABASE=homestay
MYSQL_PASSWORD=root

# App
APP_ENV=local
APP_DEBUG=true

# Database connection
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=homestay
DB_USERNAME=root
DB_PASSWORD=root

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_CLIENT=phpredis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Sanctum (CORS)
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
SESSION_DOMAIN=localhost
```

---

## Service Details

### Backend (Laravel)

```yaml
backend:
  build:
    context: ./backend
    dockerfile: Dockerfile
  ports:
    - "8000:8000"
  depends_on:
    - db
    - redis
  command: |
    bash -lc "
      composer install || true
      php artisan key:generate || true
      php artisan migrate --force || true
      php artisan serve --host=0.0.0.0 --port=8000
    "
```

### Redis

```yaml
redis:
  image: redis:7-alpine
  ports:
    - "6379:6379"
  volumes:
    - ./redis.conf:/usr/local/etc/redis/redis.conf:ro
    - redisdata:/data
  command: redis-server /usr/local/etc/redis/redis.conf
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 5s
    timeout: 3s
    retries: 5
```

### Frontend (Vite)

```yaml
frontend:
  build:
    context: ./frontend
    dockerfile: Dockerfile
  ports:
    - "5173:5173"
  volumes:
    - ./frontend:/app
```

---

## Production Deployment

### Prerequisites

- Docker & Docker Compose
- SSL certificate
- Domain configured

### Production docker-compose.prod.yml

```yaml
version: "3.8"

services:
  backend:
    image: ghcr.io/your-org/soleil-backend:latest
    environment:
      APP_ENV: production
      APP_DEBUG: false
    deploy:
      replicas: 2
      resources:
        limits:
          cpus: "1"
          memory: 512M

  redis:
    image: redis:7-alpine
    command: redis-server --requirepass ${REDIS_PASSWORD}
    deploy:
      resources:
        limits:
          memory: 2G

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
      - ./ssl:/etc/ssl/certs:ro
```

### Deploy Commands

```bash
# Production deployment
docker-compose -f docker-compose.prod.yml up -d

# Rolling update
docker-compose -f docker-compose.prod.yml pull
docker-compose -f docker-compose.prod.yml up -d --no-deps backend

# View production logs
docker-compose -f docker-compose.prod.yml logs -f --tail=100 backend
```

---

## Laravel Octane (Production)

For high-performance production, use Octane with Swoole:

```yaml
backend:
  command: php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=auto
  deploy:
    replicas: 1 # Octane handles concurrency internally
    resources:
      limits:
        cpus: "4"
        memory: 2G
```

---

## Health Checks

```bash
# Backend health
curl http://localhost:8000/api/health

# Detailed health
curl http://localhost:8000/api/health/detailed

# Redis connectivity
docker-compose exec redis redis-cli ping
```

---

## Troubleshooting

### Database connection issues

```bash
# Check database logs
docker-compose logs db

# Restart database
docker-compose restart db

# Access MySQL CLI
docker-compose exec db mysql -u root -p
```

### Redis connection issues

```bash
# Check Redis logs
docker-compose logs redis

# Test Redis connection
docker-compose exec redis redis-cli ping

# Monitor Redis
docker-compose exec redis redis-cli monitor
```

### Backend issues

```bash
# Clear Laravel cache
docker-compose exec backend php artisan cache:clear
docker-compose exec backend php artisan config:clear

# Run migrations
docker-compose exec backend php artisan migrate

# Check logs
docker-compose logs -f backend
```
