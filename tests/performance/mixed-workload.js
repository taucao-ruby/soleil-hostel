/**
 * k6 Load Test: Mixed Workload (Production-Realistic)
 *
 * Simulates realistic production traffic distribution:
 *   70% reads  (room listing, room detail, booking list)
 *   20% writes (booking creation)
 *   10% auth   (login, /me, logout)
 *
 * This is the most important test — it represents actual user behavior.
 *
 * Targets:
 *   Overall p95 < 200ms
 *   Error rate < 1%
 *   Sustained 100 VUs for 5 minutes
 *
 * Usage:
 *   k6 run -e AUTH_TOKEN=<token> tests/performance/mixed-workload.js
 *   k6 run --out json=results/mixed.json tests/performance/mixed-workload.js
 */

import http from "k6/http";
import { check, sleep, group } from "k6";
import { Rate, Trend, Counter } from "k6/metrics";

const readDuration = new Trend("read_duration", true);
const writeDuration = new Trend("write_duration", true);
const authDuration = new Trend("auth_duration", true);
const overallSuccess = new Rate("overall_success_rate");
const errorCount = new Counter("error_count");

export const options = {
  scenarios: {
    // Scenario 1: Ramp-up to steady state
    steady_load: {
      executor: "ramping-vus",
      startVUs: 0,
      stages: [
        { duration: "2m", target: 50 },
        { duration: "5m", target: 50 }, // Steady state
        { duration: "2m", target: 100 }, // Peak
        { duration: "5m", target: 100 }, // Sustained peak
        { duration: "2m", target: 0 }, // Ramp-down
      ],
      gracefulRampDown: "30s",
    },
  },
  thresholds: {
    http_req_duration: ["p(95)<200", "p(99)<500"],
    http_req_failed: ["rate<0.01"],
    read_duration: ["p(95)<150"],
    write_duration: ["p(95)<300"],
    auth_duration: ["p(95)<150"],
    overall_success_rate: ["rate>0.95"],
  },
};

const BASE_URL = __ENV.BASE_URL || "http://localhost:8000/api";

function getRandomDateRange() {
  const startOffset = Math.floor(Math.random() * 180) + 7;
  const duration = Math.floor(Math.random() * 7) + 1;
  const start = new Date();
  start.setDate(start.getDate() + startOffset);
  const end = new Date(start);
  end.setDate(end.getDate() + duration);
  return {
    check_in: start.toISOString().split("T")[0],
    check_out: end.toISOString().split("T")[0],
  };
}

const GUEST_NAMES = [
  "Alice Johnson",
  "Bob Smith",
  "Charlie Brown",
  "Diana Prince",
  "Eve Wilson",
  "Frank Castle",
  "Grace Hopper",
  "Henry Ford",
];

// Setup: get auth token
export function setup() {
  const AUTH_TOKEN = __ENV.AUTH_TOKEN;
  if (AUTH_TOKEN) {
    return { token: AUTH_TOKEN };
  }

  const loginRes = http.post(
    `${BASE_URL}/auth/login-v2`,
    JSON.stringify({
      email: __ENV.TEST_EMAIL || "admin@soleilhostel.com",
      password: __ENV.TEST_PASSWORD || "password",
    }),
    {
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
    },
  );

  try {
    const body = JSON.parse(loginRes.body);
    return { token: body.token || "" };
  } catch {
    return { token: "" };
  }
}

export default function (data) {
  const headers = {
    "Content-Type": "application/json",
    Accept: "application/json",
    "X-Correlation-ID": `perf-mixed-${__VU}-${__ITER}`,
  };

  if (data.token) {
    headers["Authorization"] = `Bearer ${data.token}`;
  }

  // Weighted random selection: 70% read, 20% write, 10% auth
  const roll = Math.random() * 100;

  if (roll < 70) {
    // ─── READ OPERATIONS (70%) ───
    const readRoll = Math.random();

    if (readRoll < 0.5) {
      // Room listing (50% of reads)
      group("Read: Room List", () => {
        const dates = getRandomDateRange();
        const res = http.get(
          `${BASE_URL}/v1/rooms?check_in=${dates.check_in}&check_out=${dates.check_out}`,
          { headers, tags: { name: "GET /v1/rooms" } },
        );
        readDuration.add(res.timings.duration);
        overallSuccess.add(
          check(res, {
            "room list: 200": (r) => r.status === 200,
          }),
        );
      });
    } else if (readRoll < 0.75) {
      // Room detail (25% of reads)
      group("Read: Room Detail", () => {
        const roomId = Math.floor(Math.random() * 50) + 1;
        const res = http.get(`${BASE_URL}/v1/rooms/${roomId}`, {
          headers,
          tags: { name: "GET /v1/rooms/:id" },
        });
        readDuration.add(res.timings.duration);
        overallSuccess.add(
          check(res, {
            "room detail: 200 or 404": (r) =>
              r.status === 200 || r.status === 404,
          }),
        );
      });
    } else {
      // User bookings (25% of reads)
      group("Read: User Bookings", () => {
        const res = http.get(`${BASE_URL}/v1/bookings`, {
          headers,
          tags: { name: "GET /v1/bookings" },
        });
        readDuration.add(res.timings.duration);
        overallSuccess.add(
          check(res, {
            "bookings list: 200": (r) => r.status === 200,
          }),
        );
      });
    }
  } else if (roll < 90) {
    // ─── WRITE OPERATIONS (20%) ───
    group("Write: Create Booking", () => {
      const guest = GUEST_NAMES[Math.floor(Math.random() * GUEST_NAMES.length)];
      const dates = getRandomDateRange();
      const roomId = Math.floor(Math.random() * 50) + 1;

      const payload = JSON.stringify({
        room_id: roomId,
        check_in: dates.check_in,
        check_out: dates.check_out,
        guest_name: guest,
        guest_email:
          guest.toLowerCase().replace(" ", ".") + "@perf-test.example.com",
      });

      const res = http.post(`${BASE_URL}/v1/bookings`, payload, {
        headers,
        tags: { name: "POST /v1/bookings" },
      });

      writeDuration.add(res.timings.duration);

      const ok = res.status === 201 || res.status === 409 || res.status === 422;
      overallSuccess.add(ok);

      if (!ok) {
        errorCount.add(1);
      }
    });
  } else {
    // ─── AUTH OPERATIONS (10%) ───
    group("Auth: Me Endpoint", () => {
      const res = http.get(`${BASE_URL}/auth/unified/me`, {
        headers,
        tags: { name: "GET /auth/unified/me" },
      });
      authDuration.add(res.timings.duration);
      overallSuccess.add(
        check(res, {
          "unified me: 200": (r) => r.status === 200,
        }),
      );
    });
  }

  // Realistic think time: 1-3 seconds
  sleep(Math.random() * 2 + 1);
}

export function handleSummary(data) {
  const summary = {
    timestamp: new Date().toISOString(),
    test: "mixed-workload",
    distribution: "70% reads / 20% writes / 10% auth",
    metrics: {
      overall: {
        p50: data.metrics.http_req_duration?.values?.["p(50)"],
        p95: data.metrics.http_req_duration?.values?.["p(95)"],
        p99: data.metrics.http_req_duration?.values?.["p(99)"],
        avg: data.metrics.http_req_duration?.values?.avg,
        rps: data.metrics.http_reqs?.values?.rate,
        error_rate: data.metrics.http_req_failed?.values?.rate,
        success_rate: data.metrics.overall_success_rate?.values?.rate,
      },
      reads: {
        p95: data.metrics.read_duration?.values?.["p(95)"],
        avg: data.metrics.read_duration?.values?.avg,
      },
      writes: {
        p95: data.metrics.write_duration?.values?.["p(95)"],
        avg: data.metrics.write_duration?.values?.avg,
      },
      auth: {
        p95: data.metrics.auth_duration?.values?.["p(95)"],
        avg: data.metrics.auth_duration?.values?.avg,
      },
      vus_max: data.metrics.vus_max?.values?.value,
      iterations: data.metrics.iterations?.values?.count,
    },
  };

  return {
    stdout: JSON.stringify(summary, null, 2) + "\n",
    "results/mixed-workload-summary.json": JSON.stringify(summary, null, 2),
  };
}
