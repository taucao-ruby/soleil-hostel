# Health Check Endpoints

> **Last Updated:** May 8, 2026 | **Status:** Production Ready ✅
>
> **OBS-002 (Apr 28, 2026, commit `58da55e`):** All detail/component endpoints (`/api/health`, `/api/health/ready`, `/api/health/full`, `/api/health/detailed`, `/api/health/db`, `/api/health/cache`, `/api/health/queue`) are now **gated behind authenticated admin** — they leak topology (driver names, queue stats, exception messages) and must not be reachable by anonymous callers. Only `/api/ping` and `/api/health/live` remain public.

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

| Endpoint                | Purpose                                            | Auth   | Status Codes |
| ----------------------- | -------------------------------------------------- | ------ | ------------ |
| `GET /api/ping`         | Public liveness sentinel (`{"status":"ok"}` only)  | Public | `200`        |
| `GET /api/health/live`  | Liveness probe (returns only `{"status":"ok"}`)    | Public | `200`        |
| `GET /api/health/ready` | Readiness probe — returns full check breakdown     | Admin  | `200`, `503` |
| `GET /api/health/full`  | Detailed metrics                                   | Admin  | `200`, `503` |
| `GET /api/health/db`    | Database only                                      | Admin  | `200`, `503` |
| `GET /api/health/cache` | Cache only                                         | Admin  | `200`        |
| `GET /api/health/queue` | Queue only                                         | Admin  | `200`        |

### Detail Endpoints (admin-only since OBS-002)

| Endpoint                   | Purpose                        | Auth  |
| -------------------------- | ------------------------------ | ----- |
| `GET /api/health`          | Service breakdown              | Admin |
| `GET /api/health/detailed` | Extended info with Redis stats | Admin |

> **Why admin-gated:** detailed payloads include connection driver names, queue stats, exception messages, and component-level latency — fingerprintable infrastructure data that must not be exposed to anonymous callers. The public `/api/ping` and `/api/health/live` endpoints return a fixed shape (`{"status":"ok"}`) that carries no topology.

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
      "connection": "pgsql"
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
    "database": { "healthy": true, "latency_ms": 1.23, "connection": "pgsql" },
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
    "laravel_version": "12.x"
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
  "connection": "pgsql"
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
      # Public liveness (admin-gated detail since OBS-002).
      test: ["CMD", "curl", "-f", "http://localhost:8000/api/health/live"]
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
        # Use /api/health/live as the public readiness signal.
        # /api/health/ready is admin-gated since OBS-002 and would 401 from kubelet.
        # If you need component-level readiness, terminate at the infra layer.
        httpGet:
          path: /api/health/live
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

# Or active health check (nginx plus) — use the public liveness path.
# /api/health/ready is admin-gated since OBS-002.
location /health-check {
    internal;
    proxy_pass http://backend/api/health/live;
}
```

### AWS ALB Target Group

```json
{
  "HealthCheckPath": "/api/health/live",
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
# Public liveness (no auth required)
curl -s http://localhost:8000/api/health/live | jq '.status'

# Admin-only — detail endpoints require an admin session/token (OBS-002)
curl -s -H "Authorization: Bearer ${ADMIN_TOKEN}" http://localhost:8000/api/health/ready | jq '.status'
curl -s -H "Authorization: Bearer ${ADMIN_TOKEN}" http://localhost:8000/api/health/full  | jq

# Individual components — also admin-only
curl -s -H "Authorization: Bearer ${ADMIN_TOKEN}" http://localhost:8000/api/health/db    | jq
curl -s -H "Authorization: Bearer ${ADMIN_TOKEN}" http://localhost:8000/api/health/cache | jq
curl -s -H "Authorization: Bearer ${ADMIN_TOKEN}" http://localhost:8000/api/health/queue | jq
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
# Database (production: PostgreSQL 16)
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432

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

### Security Consideration (post OBS-002)

Only `/api/ping` and `/api/health/live` are public. Their payload is the fixed shape `{"status":"ok"}` — no topology, no version, no driver names, no exception messages. Use these for load balancer / Kubernetes liveness probes.

All detail endpoints (`/api/health/ready`, `/api/health/full`, `/api/health/detailed`, `/api/health`, `/api/health/db|cache|queue`) require an authenticated **admin** session. Use them for internal dashboards and on-call investigation only.

If a load balancer needs more than liveness, terminate the readiness check at the **infrastructure layer** (nginx, ALB, Caddy) using a privileged side-channel rather than exposing detail to the public path. Do **not** revert OBS-002.

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
