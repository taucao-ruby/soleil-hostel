# Health Check Endpoints

> **Last Updated:** January 3, 2026 | **Status:** Production Ready ✅

## Table of Contents

1. [Overview](#overview)
2. [Failure Semantics](#failure-semantics)
3. [Endpoints](#endpoints)
4. [Response Formats](#response-formats)
5. [Integration Examples](#integration-examples)
6. [Testing](#testing)
7. [Deployment Configuration](#deployment-configuration)

---

## Overview

The Soleil Hostel booking system provides comprehensive health check endpoints for monitoring system status, enabling load balancer integration, container orchestration (Kubernetes/Docker), and proactive alerting.

### Design Principles

- **Read-only**: No side effects on the system
- **Fast**: Minimal latency overhead
- **Semantic**: HTTP status codes reflect actual system state
- **Granular**: Individual component checks available
- **Booking-Critical**: Database failure = system unhealthy

---

## Failure Semantics

Health checks implement **differentiated failure semantics** based on component criticality for a booking system:

| Component    | Criticality | Failure Response          | Rationale                                                  |
| ------------ | ----------- | ------------------------- | ---------------------------------------------------------- |
| **Database** | CRITICAL    | `503 Service Unavailable` | Booking engine, optimistic locking, money paths require DB |
| **Cache**    | DEGRADED    | `200 OK` (with warning)   | System operable with reduced performance                   |
| **Queue**    | DEGRADED    | `200 OK` (with warning)   | Async jobs delayed, OTA sync may lag                       |
| **Redis**    | DEGRADED    | `200 OK` (with warning)   | Session fallback possible                                  |

### Invariant

> **"No database = no bookings"** — The system cannot accept booking requests without database connectivity.

---

## Endpoints

### Summary Table

| Endpoint                | Purpose          | Auth | Status Codes |
| ----------------------- | ---------------- | ---- | ------------ |
| `GET /api/health/live`  | Liveness probe   | None | `200`        |
| `GET /api/health/ready` | Readiness probe  | None | `200`, `503` |
| `GET /api/health/full`  | Detailed metrics | None | `200`, `503` |
| `GET /api/health/db`    | Database only    | None | `200`, `503` |
| `GET /api/health/cache` | Cache only       | None | `200`        |
| `GET /api/health/queue` | Queue only       | None | `200`        |

### Legacy Endpoints (backward compatible)

| Endpoint                   | Purpose                        |
| -------------------------- | ------------------------------ |
| `GET /api/health`          | Basic health check             |
| `GET /api/health/detailed` | Extended info with Redis stats |

---

## Response Formats

### Liveness Probe

```bash
GET /api/health/live
```

```json
{
  "status": "ok",
  "timestamp": "2026-01-03T10:30:00+00:00"
}
```

**Use case**: Kubernetes `livenessProbe` — restart container if fails.

---

### Readiness Probe

```bash
GET /api/health/ready
```

**Healthy Response (200)**:

```json
{
  "status": "ok",
  "timestamp": "2026-01-03T10:30:00+00:00",
  "checks": {
    "database": {
      "healthy": true,
      "latency_ms": 1.23,
      "connection": "mysql"
    },
    "cache": {
      "healthy": true,
      "latency_ms": 0.45,
      "driver": "redis"
    },
    "redis": {
      "healthy": true,
      "latency_ms": 0.32
    }
  },
  "failure_semantics": {
    "critical": ["database"],
    "degraded": ["cache", "queue", "redis"]
  }
}
```

**Degraded Response (200)** — Cache/Queue down but DB up:

```json
{
  "status": "degraded",
  "timestamp": "2026-01-03T10:30:00+00:00",
  "checks": {
    "database": { "healthy": true, "latency_ms": 1.23 },
    "cache": { "healthy": false, "error": "Connection refused" },
    "redis": { "healthy": false, "error": "Connection refused" }
  },
  "failure_semantics": {
    "critical": ["database"],
    "degraded": ["cache", "queue", "redis"]
  }
}
```

**Unhealthy Response (503)** — Database down:

```json
{
  "status": "unhealthy",
  "timestamp": "2026-01-03T10:30:00+00:00",
  "checks": {
    "database": { "healthy": false, "error": "Connection refused" },
    "cache": { "healthy": true, "latency_ms": 0.45 },
    "redis": { "healthy": true, "latency_ms": 0.32 }
  },
  "failure_semantics": {
    "critical": ["database"],
    "degraded": ["cache", "queue", "redis"]
  }
}
```

---

### Detailed Health (Full)

```bash
GET /api/health/full
```

```json
{
  "status": "ok",
  "timestamp": "2026-01-03T10:30:00+00:00",
  "app": {
    "name": "Soleil Hostel",
    "environment": "production",
    "debug": false
  },
  "checks": {
    "database": { "healthy": true, "latency_ms": 1.23, "connection": "mysql" },
    "cache": { "healthy": true, "latency_ms": 0.45, "driver": "redis" },
    "redis": { "healthy": true, "latency_ms": 0.32 },
    "storage": { "healthy": true, "writable": true },
    "queue": { "healthy": true, "driver": "redis" }
  },
  "summary": {
    "healthy": 5,
    "total": 5,
    "percentage": 100.0,
    "degraded_components": []
  },
  "failure_semantics": {
    "critical": ["database"],
    "degraded": ["cache", "queue", "redis"],
    "note": "Database failure = 503 (booking engine down). Cache/Queue failure = 200 degraded (still operable)."
  },
  "metrics": {
    "uptime_seconds": 3600,
    "memory_usage_mb": 32.5,
    "peak_memory_mb": 48.2,
    "php_version": "8.3.0",
    "laravel_version": "11.x"
  }
}
```

---

### Individual Component Endpoints

#### Database (Critical)

```bash
GET /api/health/db
```

```json
{
  "component": "database",
  "criticality": "critical",
  "timestamp": "2026-01-03T10:30:00+00:00",
  "healthy": true,
  "latency_ms": 1.23,
  "connection": "mysql"
}
```

**Status codes**: `200` if healthy, `503` if unhealthy.

#### Cache (Degraded)

```bash
GET /api/health/cache
```

```json
{
  "component": "cache",
  "criticality": "degraded",
  "timestamp": "2026-01-03T10:30:00+00:00",
  "healthy": true,
  "latency_ms": 0.45,
  "driver": "redis"
}
```

**Status codes**: Always `200` (degraded component).

#### Queue (Degraded)

```bash
GET /api/health/queue
```

```json
{
  "component": "queue",
  "criticality": "degraded",
  "timestamp": "2026-01-03T10:30:00+00:00",
  "healthy": true,
  "driver": "redis"
}
```

**Status codes**: Always `200` (degraded component).

---

## Integration Examples

### Docker Compose

```yaml
services:
  backend:
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/api/health/ready"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
```

### Kubernetes

```yaml
apiVersion: v1
kind: Pod
spec:
  containers:
    - name: backend
      livenessProbe:
        httpGet:
          path: /api/health/live
          port: 8000
        initialDelaySeconds: 30
        periodSeconds: 10
        failureThreshold: 3
      readinessProbe:
        httpGet:
          path: /api/health/ready
          port: 8000
        initialDelaySeconds: 5
        periodSeconds: 5
        failureThreshold: 3
```

### Nginx Upstream Health Check

```nginx
upstream backend {
    server backend:8000;

    # Passive health check
    server backend:8000 max_fails=3 fail_timeout=30s;
}

# Or active health check (nginx plus)
location /health-check {
    internal;
    proxy_pass http://backend/api/health/ready;
}
```

### AWS ALB Target Group

```json
{
  "HealthCheckPath": "/api/health/ready",
  "HealthCheckIntervalSeconds": 30,
  "HealthCheckTimeoutSeconds": 5,
  "HealthyThresholdCount": 2,
  "UnhealthyThresholdCount": 3,
  "Matcher": {
    "HttpCode": "200"
  }
}
```

### Monitoring with cURL

```bash
# Quick health check
curl -s http://localhost:8000/api/health/ready | jq '.status'

# Detailed check with timing
time curl -s http://localhost:8000/api/health/full | jq

# Individual components
curl -s http://localhost:8000/api/health/db | jq
curl -s http://localhost:8000/api/health/cache | jq
curl -s http://localhost:8000/api/health/queue | jq
```

---

## Testing

### Run Tests

```bash
cd backend

# Run all health check tests
php artisan test --filter=HealthControllerTest

# Run with coverage
php artisan test --filter=HealthControllerTest --coverage
```

### Test Cases

| Test                 | Description                  |
| -------------------- | ---------------------------- |
| Liveness returns 200 | App is alive                 |
| Readiness structure  | All checks present           |
| Failure semantics    | Critical vs degraded logic   |
| Individual endpoints | DB/Cache/Queue checks        |
| Latency metrics      | Timing included              |
| Status codes         | 200/503 based on criticality |

---

## Deployment Configuration

### Environment Variables

No additional environment variables required. Health checks use existing configuration:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306

# Cache
CACHE_DRIVER=redis
REDIS_HOST=redis

# Queue
QUEUE_CONNECTION=redis
```

### Rate Limiting Consideration

Health check endpoints are intentionally **not rate-limited** to allow frequent polling by:

- Load balancers
- Container orchestrators
- Monitoring systems

If needed, apply IP-based rate limiting at the infrastructure level (Nginx, Cloudflare, AWS WAF).

### Security Consideration

Health endpoints are **public by design** for load balancer integration. If sensitive metrics exposure is a concern:

1. Use `/api/health/ready` for load balancers (minimal info)
2. Protect `/api/health/full` behind auth middleware for dashboards
3. Use IP allowlisting at infrastructure level

---

## Future Enhancements (Post-MVP)

- [ ] Prometheus `/metrics` endpoint
- [ ] Custom health check thresholds (configurable latency warnings)
- [ ] Queue depth monitoring (job backlog alerts)
- [ ] Database connection pool metrics
- [ ] Automatic incident creation on prolonged degradation
- [ ] Historical health data for SLA reporting

---

## Related Documentation

- [Optimistic Locking](./OPTIMISTIC_LOCKING.md) - Database concurrency control
- [Rate Limiting](../security/RATE_LIMITING.md) - API protection
- [Logging & Monitoring](./LOGGING_MONITORING.md) - Observability stack
