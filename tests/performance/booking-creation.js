/**
 * k6 Load Test: Booking Creation
 *
 * Tests the write-heavy path: POST /api/v1/bookings
 * Includes overlap checks, pessimistic locking, notifications.
 *
 * Targets:
 *   p95 < 300ms
 *   Error rate < 2% (some 409s expected from overlap)
 *   RPS > 30
 *
 * Prerequisites:
 *   - Seeded database with rooms and users
 *   - Valid auth tokens (set via setup function)
 *
 * Usage:
 *   k6 run -e AUTH_TOKEN=<token> tests/performance/booking-creation.js
 *   k6 run --out json=results/booking.json tests/performance/booking-creation.js
 */

import http from "k6/http";
import { check, sleep, group } from "k6";
import { Rate, Trend, Counter } from "k6/metrics";

// Custom metrics
const bookingDuration = new Trend("booking_creation_duration", true);
const bookingSuccess = new Rate("booking_success_rate");
const conflictRate = new Rate("booking_conflict_rate");
const errorCount = new Counter("booking_error_count");

export const options = {
  stages: [
    { duration: "1m", target: 10 }, // Warm-up
    { duration: "3m", target: 30 }, // Steady state
    { duration: "2m", target: 60 }, // Spike test
    { duration: "3m", target: 60 }, // Sustained load
    { duration: "1m", target: 0 }, // Ramp-down
  ],
  thresholds: {
    http_req_duration: ["p(95)<300", "p(99)<800"],
    http_req_failed: ["rate<0.05"], // Higher tolerance (409s are expected)
    booking_creation_duration: ["p(95)<300"],
  },
  gracefulRampDown: "30s",
};

const BASE_URL = __ENV.BASE_URL || "http://localhost:8000/api";
const AUTH_TOKEN = __ENV.AUTH_TOKEN || "";

// Pool of test data for realistic variation
const GUEST_NAMES = [
  "Alice Johnson",
  "Bob Smith",
  "Charlie Brown",
  "Diana Prince",
  "Eve Wilson",
  "Frank Castle",
  "Grace Hopper",
  "Henry Ford",
  "Iris Chang",
  "James Bond",
  "Karen Page",
  "Leo Messi",
];

const GUEST_EMAILS = GUEST_NAMES.map(
  (name) => name.toLowerCase().replace(" ", ".") + "@perf-test.example.com",
);

function getRandomGuest() {
  const idx = Math.floor(Math.random() * GUEST_NAMES.length);
  return { name: GUEST_NAMES[idx], email: GUEST_EMAILS[idx] };
}

function getBookingDateRange() {
  // Spread bookings across 180 days to reduce conflicts
  const startOffset = Math.floor(Math.random() * 180) + 7;
  const duration = Math.floor(Math.random() * 5) + 1; // 1-5 nights
  const start = new Date();
  start.setDate(start.getDate() + startOffset);
  const end = new Date(start);
  end.setDate(end.getDate() + duration);

  return {
    check_in: start.toISOString().split("T")[0],
    check_out: end.toISOString().split("T")[0],
  };
}

// Setup: Login and get auth tokens for VUs
export function setup() {
  if (AUTH_TOKEN) {
    return { token: AUTH_TOKEN };
  }

  // Auto-login with test user
  const loginPayload = JSON.stringify({
    email: __ENV.TEST_EMAIL || "admin@soleilhostel.com",
    password: __ENV.TEST_PASSWORD || "password",
  });

  const loginRes = http.post(`${BASE_URL}/auth/login-v2`, loginPayload, {
    headers: { "Content-Type": "application/json", Accept: "application/json" },
  });

  if (loginRes.status !== 200 && loginRes.status !== 201) {
    console.error(`Login failed: ${loginRes.status} - ${loginRes.body}`);
    return { token: "" };
  }

  try {
    const body = JSON.parse(loginRes.body);
    return { token: body.token || body.data?.access_token || "" };
  } catch {
    console.error("Failed to parse login response");
    return { token: "" };
  }
}

export default function (data) {
  const authHeaders = {
    "Content-Type": "application/json",
    Accept: "application/json",
    "X-Correlation-ID": `perf-booking-${__VU}-${__ITER}`,
  };

  if (data.token) {
    authHeaders["Authorization"] = `Bearer ${data.token}`;
  }

  group("Create Booking", () => {
    const guest = getRandomGuest();
    const dates = getBookingDateRange();
    const roomId = Math.floor(Math.random() * 50) + 1;

    const payload = JSON.stringify({
      room_id: roomId,
      check_in: dates.check_in,
      check_out: dates.check_out,
      guest_name: guest.name,
      guest_email: guest.email,
    });

    const response = http.post(`${BASE_URL}/v1/bookings`, payload, {
      headers: authHeaders,
      tags: { name: "POST /v1/bookings" },
    });

    bookingDuration.add(response.timings.duration);

    const isCreated = response.status === 201;
    const isConflict = response.status === 409 || response.status === 422;

    bookingSuccess.add(isCreated);
    conflictRate.add(isConflict);

    check(response, {
      "booking: valid response (201 or 409/422)": (r) =>
        r.status === 201 || r.status === 409 || r.status === 422,
      "booking: response time < 500ms": (r) => r.timings.duration < 500,
      "booking: has JSON body": (r) => {
        try {
          JSON.parse(r.body);
          return true;
        } catch {
          return false;
        }
      },
    });

    if (!isCreated && !isConflict) {
      errorCount.add(1);
      if (response.status === 401) {
        console.warn(`Auth failed for VU ${__VU}: ${response.status}`);
      }
    }
  });

  group("List User Bookings", () => {
    const response = http.get(`${BASE_URL}/v1/bookings`, {
      headers: authHeaders,
      tags: { name: "GET /v1/bookings" },
    });

    check(response, {
      "list bookings: status 200": (r) => r.status === 200,
      "list bookings: response time < 200ms": (r) => r.timings.duration < 200,
    });
  });

  sleep(Math.random() * 3 + 1); // 1-4s think time (writes are slower)
}

export function handleSummary(data) {
  const summary = {
    timestamp: new Date().toISOString(),
    test: "booking-creation",
    metrics: {
      http_req_duration_p50: data.metrics.http_req_duration?.values?.["p(50)"],
      http_req_duration_p95: data.metrics.http_req_duration?.values?.["p(95)"],
      http_req_duration_p99: data.metrics.http_req_duration?.values?.["p(99)"],
      http_req_duration_avg: data.metrics.http_req_duration?.values?.avg,
      http_reqs_rate: data.metrics.http_reqs?.values?.rate,
      http_req_failed_rate: data.metrics.http_req_failed?.values?.rate,
      booking_success_rate: data.metrics.booking_success_rate?.values?.rate,
      booking_conflict_rate: data.metrics.booking_conflict_rate?.values?.rate,
      booking_creation_p95:
        data.metrics.booking_creation_duration?.values?.["p(95)"],
      vus_max: data.metrics.vus_max?.values?.value,
      iterations: data.metrics.iterations?.values?.count,
    },
  };

  return {
    stdout: JSON.stringify(summary, null, 2) + "\n",
    "results/booking-summary.json": JSON.stringify(summary, null, 2),
  };
}
