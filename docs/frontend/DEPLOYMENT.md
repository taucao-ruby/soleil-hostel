# Deployment Configuration

> Production deployment với Docker và CI/CD pipeline

## Tổng quan

Deployment strategy bao gồm:

- **Docker**: Containerized deployment
- **Docker Compose**: Multi-service orchestration
- **GitHub Actions**: CI/CD automation
- **Environment Management**: Multi-environment support
- **Monitoring**: Health checks và logging

## 1. Docker Configuration

### Backend Dockerfile

```dockerfile
# backend/Dockerfile
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    postgresql-dev \
    redis \
    git \
    curl \
    unzip

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd zip bcmath opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start command
CMD ["/usr/local/bin/entrypoint.sh"]
```

### Frontend Dockerfile

```dockerfile
# frontend/Dockerfile
FROM node:18-alpine AS builder

# Set working directory
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy source code
COPY . .

# Build application
RUN npm run build

# Production stage
FROM nginx:alpine

# Copy built application
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start nginx
CMD ["nginx", "-g", "daemon off;"]
```

### Docker Compose Production

```yaml
# docker-compose.prod.yml
version: "3.8"

services:
  # PostgreSQL Database
  postgres:
    image: postgres:15-alpine
    container_name: soleil-postgres
    environment:
      POSTGRES_DB: soleil_hostel
      POSTGRES_USER: soleil_user
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./database/backups:/backups
    networks:
      - soleil-network
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U soleil_user -d soleil_hostel"]
      interval: 30s
      timeout: 10s
      retries: 3

  # Redis Cache
  redis:
    image: redis:7-alpine
    container_name: soleil-redis
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    networks:
      - soleil-network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 30s
      timeout: 10s
      retries: 3

  # Backend API
  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: soleil-backend
    environment:
      APP_NAME: SoleilHostel
      APP_ENV: production
      APP_KEY: ${APP_KEY}
      APP_URL: ${APP_URL}
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: soleil_hostel
      DB_USERNAME: soleil_user
      DB_PASSWORD: ${DB_PASSWORD}
      REDIS_HOST: redis
      REDIS_PASSWORD: null
      REDIS_PORT: 6379
      CACHE_DRIVER: redis
      QUEUE_CONNECTION: redis
      SESSION_DRIVER: redis
      MAIL_MAILER: smtp
      MAIL_HOST: ${MAIL_HOST}
      MAIL_PORT: ${MAIL_PORT}
      MAIL_USERNAME: ${MAIL_USERNAME}
      MAIL_PASSWORD: ${MAIL_PASSWORD}
      MAIL_ENCRYPTION: ${MAIL_ENCRYPTION}
      MAIL_FROM_ADDRESS: ${MAIL_FROM_ADDRESS}
      MAIL_FROM_NAME: ${MAIL_FROM_NAME}
    volumes:
      - ./backend/storage:/var/www/html/storage
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - soleil-network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  # Frontend
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    container_name: soleil-frontend
    environment:
      API_URL: ${API_URL}
      APP_ENV: production
    depends_on:
      - backend
    networks:
      - soleil-network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3

  # Nginx Reverse Proxy
  nginx:
    image: nginx:alpine
    container_name: soleil-nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx.conf:/etc/nginx/nginx.conf
      - ./docker/ssl:/etc/ssl/certs
      - nginx_logs:/var/log/nginx
    depends_on:
      - frontend
      - backend
    networks:
      - soleil-network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  # SSL Certificate Manager (Let's Encrypt)
  certbot:
    image: certbot/certbot
    container_name: soleil-certbot
    volumes:
      - ./docker/ssl:/etc/letsencrypt
      - certbot_webroot:/var/www/certbot
    entrypoint: "/bin/sh -c 'trap exit TERM; while :; do certbot renew; sleep 12h & wait $${!}; done;'"
    networks:
      - soleil-network

volumes:
  postgres_data:
  redis_data:
  nginx_logs:
  certbot_webroot:

networks:
  soleil-network:
    driver: bridge
```

## 2. Nginx Configuration

### Reverse Proxy Configuration

```nginx
# docker/nginx.conf
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log notice;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/atom+xml
        image/svg+xml;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone $binary_remote_addr zone=auth:10m rate=5r/m;

    # Upstream servers
    upstream backend {
        server backend:80;
    }

    upstream frontend {
        server frontend:80;
    }

    # SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    server {
        listen 80;
        server_name _;
        return 301 https://$host$request_uri;
    }

    server {
        listen 443 ssl http2;
        server_name your-domain.com;

        ssl_certificate /etc/ssl/certs/fullchain.pem;
        ssl_certificate_key /etc/ssl/certs/privkey.pem;

        # Frontend
        location / {
            proxy_pass http://frontend;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # Backend API
        location /api/ {
            proxy_pass http://backend;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;

            # Rate limiting for API
            limit_req zone=api burst=20 nodelay;
        }

        # Authentication endpoints
        location ~ ^/(login|register|password) {
            proxy_pass http://backend;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;

            # Stricter rate limiting for auth
            limit_req zone=auth burst=5 nodelay;
        }

        # Static files
        location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }

        # Health check
        location /health {
            access_log off;
            return 200 "healthy\n";
            add_header Content-Type text/plain;
        }
    }
}
```

## 3. Environment Configuration

### Production Environment Variables

```bash
# .env.production
# Application
APP_NAME=SoleilHostel
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_URL=https://your-domain.com
APP_DEBUG=false

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=soleil_hostel
DB_USERNAME=soleil_user
DB_PASSWORD=your-secure-db-password

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache & Session
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Soleil Hostel"

# Security
SANCTUM_STATEFUL_DOMAINS=your-domain.com
SESSION_DOMAIN=.your-domain.com

# Frontend
VITE_API_URL=https://your-domain.com/api
VITE_APP_ENV=production
```

### Environment Validation Script

```bash
#!/bin/bash
# scripts/validate-env.sh

# Required environment variables
required_vars=(
    "APP_KEY"
    "DB_PASSWORD"
    "MAIL_USERNAME"
    "MAIL_PASSWORD"
    "VITE_API_URL"
)

# Check if all required variables are set
for var in "${required_vars[@]}"; do
    if [[ -z "${!var}" ]]; then
        echo "Error: $var is not set"
        exit 1
    fi
done

echo "All required environment variables are set"
```

## 4. CI/CD Pipeline

### GitHub Actions Workflow

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]
  workflow_dispatch:

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: "18"
          cache: "npm"
          cache-dependency-path: "frontend/package-lock.json"

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.2"
          extensions: pdo, pdo_pgsql, redis
          tools: composer:v2

      - name: Install backend dependencies
        working-directory: ./backend
        run: composer install --no-progress --prefer-dist --optimize-autoloader

      - name: Install frontend dependencies
        working-directory: ./frontend
        run: npm ci

      - name: Run backend tests
        working-directory: ./backend
        run: php artisan test

      - name: Run frontend tests
        working-directory: ./frontend
        run: npm run test

      - name: Build frontend
        working-directory: ./frontend
        run: npm run build

  build-and-push:
    needs: test
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Log in to Container Registry
        uses: docker/login-action@v2
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v4
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=ref,event=branch
            type=ref,event=pr
            type=sha

      - name: Build and push backend
        uses: docker/build-push-action@v4
        with:
          context: ./backend
          push: true
          tags: ${{ steps.meta.outputs.tags }}-backend
          labels: ${{ steps.meta.outputs.labels }}

      - name: Build and push frontend
        uses: docker/build-push-action@v4
        with:
          context: ./frontend
          push: true
          tags: ${{ steps.meta.outputs.tags }}-frontend
          labels: ${{ steps.meta.outputs.labels }}

  deploy:
    needs: build-and-push
    runs-on: ubuntu-latest
    environment: production

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Deploy to production
        uses: appleboy/ssh-action@v0.1.10
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /opt/soleil-hostel

            # Pull latest changes
            git pull origin main

            # Copy environment file
            cp .env.production .env

            # Update environment variables
            sed -i 's|APP_KEY=.*|APP_KEY=${{ secrets.APP_KEY }}|g' .env
            sed -i 's|DB_PASSWORD=.*|DB_PASSWORD=${{ secrets.DB_PASSWORD }}|g' .env
            sed -i 's|MAIL_USERNAME=.*|MAIL_USERNAME=${{ secrets.MAIL_USERNAME }}|g' .env
            sed -i 's|MAIL_PASSWORD=.*|MAIL_PASSWORD=${{ secrets.MAIL_PASSWORD }}|g' .env

            # Deploy with docker-compose
            docker-compose -f docker-compose.prod.yml pull
            docker-compose -f docker-compose.prod.yml up -d --build

            # Run database migrations
            docker-compose -f docker-compose.prod.yml exec -T backend php artisan migrate --force

            # Clear and cache config
            docker-compose -f docker-compose.prod.yml exec -T backend php artisan config:cache
            docker-compose -f docker-compose.prod.yml exec -T backend php artisan route:cache
            docker-compose -f docker-compose.prod.yml exec -T backend php artisan view:cache

            # Run health check
            sleep 30
            curl -f https://your-domain.com/health || exit 1

  rollback:
    needs: deploy
    runs-on: ubuntu-latest
    environment: production
    if: failure()

    steps:
      - name: Rollback deployment
        uses: appleboy/ssh-action@v0.1.10
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /opt/soleil-hostel

            # Rollback to previous version
            docker-compose -f docker-compose.prod.yml up -d --no-build previous-backend previous-frontend

            # Notify team
            curl -X POST -H 'Content-type: application/json' \
              --data '{"text":"🚨 Deployment failed, rollback initiated"}' \
              ${{ secrets.SLACK_WEBHOOK_URL }}
```

## 5. Monitoring và Logging

### Health Check Endpoints

```php
// backend/routes/web.php
Route::get('/health', function () {
    try {
        // Check database connection
        DB::connection()->getPdo();

        // Check Redis connection
        Redis::ping();

        // Check storage permissions
        $storagePath = storage_path('app');
        if (!is_writable($storagePath)) {
            throw new Exception('Storage not writable');
        }

        return response('OK', 200);
    } catch (Exception $e) {
        Log::error('Health check failed: ' . $e->getMessage());
        return response('Service Unavailable', 503);
    }
});

Route::get('/health/detailed', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'services' => [
            'database' => DB::connection()->getPdo() ? 'ok' : 'error',
            'redis' => Redis::ping() ? 'ok' : 'error',
            'storage' => is_writable(storage_path('app')) ? 'ok' : 'error',
        ],
        'version' => config('app.version'),
        'environment' => config('app.env'),
    ]);
});
```

### Application Monitoring

```php
// backend/app/Http/Middleware/Monitoring.php
class Monitoring
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $start;

        // Log slow requests
        if ($duration > 1.0) {
            Log::warning('Slow request', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'duration' => $duration,
                'user_id' => auth()->id(),
            ]);
        }

        // Add performance headers
        $response->headers->set('X-Response-Time', round($duration * 1000) . 'ms');

        return $response;
    }
}
```

### Error Tracking

```php
// backend/app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    // Report to error tracking service
    if ($this->shouldReport($exception)) {
        $this->reportToExternalService($exception, $request);
    }

    // Custom error responses for API
    if ($request->is('api/*')) {
        return $this->handleApiException($request, $exception);
    }

    return parent::render($request, $exception);
}

private function reportToExternalService(Throwable $exception, Request $request)
{
    // Send to Sentry, Bugsnag, or similar service
    // This is a placeholder implementation
    Log::error('Exception reported', [
        'exception' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'request' => [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ],
    ]);
}
```

## 6. Backup Strategy

### Database Backup Script

```bash
#!/bin/bash
# scripts/backup.sh

BACKUP_DIR="/opt/soleil-hostel/backups"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="soleil_backup_$DATE"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
docker-compose -f docker-compose.prod.yml exec -T postgres pg_dump \
    -U soleil_user \
    -d soleil_hostel \
    -F c \
    -f /backups/$BACKUP_NAME.sql

# Compress backup
gzip $BACKUP_DIR/$BACKUP_NAME.sql

# Upload to cloud storage (example with AWS S3)
aws s3 cp $BACKUP_DIR/$BACKUP_NAME.sql.gz s3://your-backup-bucket/

# Clean old backups (keep last 7 days)
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

# Log backup completion
echo "Backup completed: $BACKUP_NAME" >> /var/log/backup.log
```

### Automated Backup Cron Job

```bash
# crontab entry for daily backups at 2 AM
0 2 * * * /opt/soleil-hostel/scripts/backup.sh
```

## 7. Security Hardening

### Docker Security

```yaml
# docker-compose.prod.yml (security additions)
services:
  backend:
    security_opt:
      - no-new-privileges:true
    read_only: true
    tmpfs:
      - /tmp
    volumes:
      - ./backend/storage:/var/www/html/storage:rw
    user: www-data:www-data

  frontend:
    security_opt:
      - no-new-privileges:true
    read_only: true
```

### SSL/TLS Configuration

```nginx
# SSL configuration with security headers
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    # SSL certificates
    ssl_certificate /etc/ssl/certs/fullchain.pem;
    ssl_certificate_key /etc/ssl/certs/privkey.pem;

    # SSL security settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Content Security Policy
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https://api.your-domain.com" always;
}
```

## 8. Performance Optimization

### Database Optimization

```sql
-- Database indexes for performance
CREATE INDEX CONCURRENTLY idx_bookings_user_id ON bookings(user_id);
CREATE INDEX CONCURRENTLY idx_bookings_room_id ON bookings(room_id);
CREATE INDEX CONCURRENTLY idx_bookings_dates ON bookings(check_in_date, check_out_date);
CREATE INDEX CONCURRENTLY idx_rooms_type_price ON rooms(type, price_per_night);

-- Query optimization
EXPLAIN ANALYZE SELECT * FROM bookings WHERE user_id = $1 AND check_in_date >= $2;
```

### Caching Strategy

```php
// backend/config/cache.php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

// Cache frequently accessed data
Cache::remember('rooms.available', 3600, function () {
    return Room::where('status', 'available')->get();
});

// Cache user permissions
Cache::tags(['permissions'])->remember("user.{$userId}.permissions", 3600, function () use ($userId) {
    return User::find($userId)->getAllPermissions();
});
```

### CDN Configuration

```javascript
// frontend/src/utils/cdn.ts
export const CDN_URL =
  process.env.VITE_CDN_URL || "https://cdn.your-domain.com";

// Load assets from CDN
export const getAssetUrl = (path: string): string => {
  return `${CDN_URL}${path}`;
};

// Preload critical resources
export const preloadCriticalResources = () => {
  const link = document.createElement("link");
  link.rel = "preload";
  link.href = getAssetUrl("/fonts/main.woff2");
  link.as = "font";
  link.type = "font/woff2";
  link.crossOrigin = "anonymous";
  document.head.appendChild(link);
};
```

## Best Practices cho Production Deployment

### 1. Infrastructure as Code

- **Version Control**: All infrastructure code in Git
- **Immutable Infrastructure**: Containers never modified at runtime
- **Infrastructure Testing**: Test deployments before production

### 2. Security First

- **Least Privilege**: Minimal permissions for services
- **Network Security**: Proper firewall rules và network segmentation
- **Secret Management**: Secure storage cho sensitive data
- **Regular Updates**: Keep dependencies và base images updated

### 3. Monitoring & Alerting

- **Health Checks**: Comprehensive service health monitoring
- **Metrics Collection**: Performance và business metrics
- **Alert Configuration**: Proactive alerting cho issues
- **Log Aggregation**: Centralized logging system

### 4. Backup & Recovery

- **Regular Backups**: Automated daily backups
- **Backup Testing**: Regular restore testing
- **Disaster Recovery**: Documented recovery procedures
- **Data Retention**: Appropriate data retention policies

### 5. Performance & Scalability

- **Load Testing**: Regular performance testing
- **Auto-scaling**: Horizontal scaling capabilities
- **Caching Strategy**: Multi-layer caching approach
- **CDN Integration**: Global content delivery

### 6. Deployment Strategy

- **Blue-Green Deployment**: Zero-downtime deployments
- **Canary Releases**: Gradual rollout cho new features
- **Rollback Plan**: Quick rollback capabilities
- **Feature Flags**: Runtime feature toggles
