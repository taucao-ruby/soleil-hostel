# Performance Test Suite - Soleil Hostel API

## Overview

k6 load test scripts for benchmarking API performance. These tests establish baseline metrics for regression detection and SLA monitoring.

## Prerequisites

Install [k6](https://k6.io/docs/getting-started/installation/):

```bash
# macOS
brew install k6

# Windows (Chocolatey)
choco install k6

# Docker
docker pull grafana/k6
```

## Scripts

| Script                  | Description                                            | Target p95 |
| ----------------------- | ------------------------------------------------------ | ---------- |
| `availability-query.js` | Room listing + availability (read-heavy, cached)       | < 200ms    |
| `booking-creation.js`   | Booking creation (write-heavy, locking)                | < 300ms    |
| `auth-flow.js`          | Full auth lifecycle (login → me → refresh → logout)    | < 150ms    |
| `mixed-workload.js`     | Production-realistic (70% reads, 20% writes, 10% auth) | < 200ms    |

## Quick Start

```bash
# 1. Start the application
cd backend && php -S 127.0.0.1:8000 -t public public/index.php

# 2. Run individual tests
k6 run tests/performance/availability-query.js
k6 run tests/performance/booking-creation.js
k6 run tests/performance/auth-flow.js
k6 run tests/performance/mixed-workload.js

# 3. Run with custom base URL
k6 run -e BASE_URL=http://localhost:8000/api tests/performance/mixed-workload.js

# 4. Run with auth token (for write tests)
k6 run -e AUTH_TOKEN=your_token_here tests/performance/booking-creation.js

# 5. Export results to JSON
k6 run --out json=results/output.json tests/performance/mixed-workload.js
```

## Running with Docker

```bash
docker run --rm -i --network host \
  -v $(pwd)/tests/performance:/scripts \
  grafana/k6 run /scripts/mixed-workload.js
```

## Environment Variables

| Variable        | Default                     | Description                       |
| --------------- | --------------------------- | --------------------------------- |
| `BASE_URL`      | `http://localhost:8000/api` | API base URL                      |
| `AUTH_TOKEN`    | (auto-login)                | Pre-generated bearer token        |
| `TEST_EMAIL`    | `admin@soleilhostel.com`    | Test user email for auto-login    |
| `TEST_PASSWORD` | `password`                  | Test user password for auto-login |

## Results

Test summaries are written to `results/` directory:

```
results/
├── availability-summary.json
├── booking-summary.json
├── auth-flow-summary.json
└── mixed-workload-summary.json
```

## Grafana Integration

Stream real-time results to Grafana Cloud or InfluxDB:

```bash
# InfluxDB
k6 run --out influxdb=http://localhost:8086/k6 tests/performance/mixed-workload.js

# Grafana Cloud k6
K6_CLOUD_TOKEN=your_token k6 cloud tests/performance/mixed-workload.js
```

## CI/CD Integration

Add to your CI pipeline to catch performance regressions:

```yaml
# GitHub Actions example
- name: Run Performance Tests
  run: |
    k6 run --quiet tests/performance/mixed-workload.js
  env:
    BASE_URL: http://localhost:8000/api
```

Threshold failures will exit with non-zero code, failing the pipeline.
