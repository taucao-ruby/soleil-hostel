/**
 * k6 Load Test: Room Availability Query
 *
 * Tests the hot path: GET /api/v1/rooms (with availability)
 * This endpoint uses Redis caching + composite index queries.
 *
 * Targets:
 *   p95 < 50ms  (cache hit)
 *   p95 < 200ms (cache miss)
 *   Error rate < 1%
 *   RPS > 100
 *
 * Usage:
 *   k6 run tests/performance/availability-query.js
 *   k6 run --out json=results/availability.json tests/performance/availability-query.js
 */

import http from "k6/http";
import { check, sleep, group } from "k6";
import { Rate, Trend, Counter } from "k6/metrics";

// Custom metrics
const cacheHitRate = new Rate("cache_hit_rate");
const availabilityDuration = new Trend("availability_duration", true);
const errorCount = new Counter("error_count");

export const options = {
  stages: [
    { duration: "1m", target: 25 }, // Warm-up
    { duration: "3m", target: 50 }, // Steady state
    { duration: "2m", target: 100 }, // Spike test
    { duration: "3m", target: 100 }, // Sustained high load
    { duration: "1m", target: 50 }, // Scale down
    { duration: "1m", target: 0 }, // Ramp-down
  ],
  thresholds: {
    http_req_duration: ["p(95)<200", "p(99)<500"],
    http_req_failed: ["rate<0.01"],
    availability_duration: ["p(95)<200"],
    error_count: ["count<50"],
  },
  // Graceful stop
  gracefulRampDown: "30s",
};

const BASE_URL = __ENV.BASE_URL || "http://localhost:8000/api";

// Generate random date pairs for realistic variety
function getRandomDateRange() {
  const startOffset = Math.floor(Math.random() * 90) + 1; // 1-90 days from now
  const duration = Math.floor(Math.random() * 7) + 1; // 1-7 nights
  const start = new Date();
  start.setDate(start.getDate() + startOffset);
  const end = new Date(start);
  end.setDate(end.getDate() + duration);

  return {
    check_in: start.toISOString().split("T")[0],
    check_out: end.toISOString().split("T")[0],
  };
}

export default function () {
  group("Room Availability - Cache Hit Path", () => {
    // First request: may be cache miss
    const dates = getRandomDateRange();
    const url = `${BASE_URL}/v1/rooms?check_in=${dates.check_in}&check_out=${dates.check_out}`;

    const params = {
      headers: {
        Accept: "application/json",
        "X-Correlation-ID": `perf-avail-${__VU}-${__ITER}`,
      },
      tags: { name: "GET /v1/rooms" },
    };

    const response = http.get(url, params);

    availabilityDuration.add(response.timings.duration);

    const isSuccess = check(response, {
      "status is 200": (r) => r.status === 200,
      "response time < 500ms": (r) => r.timings.duration < 500,
      "has success field": (r) => {
        try {
          return JSON.parse(r.body).success === true;
        } catch {
          return false;
        }
      },
      "has data array": (r) => {
        try {
          return Array.isArray(JSON.parse(r.body).data);
        } catch {
          return false;
        }
      },
    });

    if (!isSuccess) {
      errorCount.add(1);
    }

    // Track cache behavior (faster responses likely cache hits)
    cacheHitRate.add(response.timings.duration < 50);
  });

  group("Room Availability - Repeated Query (Cache Hit)", () => {
    // Use fixed dates to ensure cache hits
    const url = `${BASE_URL}/v1/rooms?check_in=2026-06-01&check_out=2026-06-05`;

    const params = {
      headers: {
        Accept: "application/json",
        "X-Correlation-ID": `perf-cache-${__VU}-${__ITER}`,
      },
      tags: { name: "GET /v1/rooms (cached)" },
    };

    const response = http.get(url, params);

    check(response, {
      "cached: status 200": (r) => r.status === 200,
      "cached: response time < 100ms": (r) => r.timings.duration < 100,
    });

    cacheHitRate.add(response.timings.duration < 50);
  });

  group("Single Room Detail", () => {
    const roomId = Math.floor(Math.random() * 50) + 1; // Random room 1-50
    const url = `${BASE_URL}/v1/rooms/${roomId}`;

    const params = {
      headers: {
        Accept: "application/json",
        "X-Correlation-ID": `perf-room-${__VU}-${__ITER}`,
      },
      tags: { name: "GET /v1/rooms/:id" },
    };

    const response = http.get(url, params);

    check(response, {
      "room detail: status 200 or 404": (r) =>
        r.status === 200 || r.status === 404,
      "room detail: response time < 200ms": (r) => r.timings.duration < 200,
    });
  });

  sleep(Math.random() * 2 + 0.5); // 0.5-2.5s think time
}

export function handleSummary(data) {
  const summary = {
    timestamp: new Date().toISOString(),
    test: "availability-query",
    metrics: {
      http_req_duration_p50: data.metrics.http_req_duration?.values?.["p(50)"],
      http_req_duration_p95: data.metrics.http_req_duration?.values?.["p(95)"],
      http_req_duration_p99: data.metrics.http_req_duration?.values?.["p(99)"],
      http_req_duration_min: data.metrics.http_req_duration?.values?.min,
      http_req_duration_max: data.metrics.http_req_duration?.values?.max,
      http_req_duration_avg: data.metrics.http_req_duration?.values?.avg,
      http_reqs_rate: data.metrics.http_reqs?.values?.rate,
      http_req_failed_rate: data.metrics.http_req_failed?.values?.rate,
      cache_hit_rate: data.metrics.cache_hit_rate?.values?.rate,
      vus_max: data.metrics.vus_max?.values?.value,
      iterations: data.metrics.iterations?.values?.count,
    },
    thresholds: data.root_group?.checks
      ? Object.fromEntries(
          Object.entries(data.metrics)
            .filter(([_, v]) => v.thresholds)
            .map(([k, v]) => [k, v.thresholds]),
        )
      : {},
  };

  return {
    stdout: JSON.stringify(summary, null, 2) + "\n",
    "results/availability-summary.json": JSON.stringify(summary, null, 2),
  };
}
