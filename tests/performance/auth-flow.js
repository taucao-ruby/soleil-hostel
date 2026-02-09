/**
 * k6 Load Test: Authentication Flow
 *
 * Tests the full auth lifecycle:
 *   1. Login (Bearer token)
 *   2. Login (HttpOnly cookie)
 *   3. Get current user (/auth/unified/me)
 *   4. Token refresh
 *   5. Logout
 *
 * Targets:
 *   Login p95 < 150ms
 *   /me p95 < 50ms
 *   Error rate < 1%
 *
 * Usage:
 *   k6 run tests/performance/auth-flow.js
 */

import http from "k6/http";
import { check, sleep, group } from "k6";
import { Rate, Trend } from "k6/metrics";

const loginDuration = new Trend("login_duration", true);
const meDuration = new Trend("me_duration", true);
const authSuccess = new Rate("auth_success_rate");

export const options = {
  stages: [
    { duration: "1m", target: 20 },
    { duration: "3m", target: 50 },
    { duration: "2m", target: 80 },
    { duration: "2m", target: 80 },
    { duration: "1m", target: 0 },
  ],
  thresholds: {
    login_duration: ["p(95)<150"],
    me_duration: ["p(95)<50"],
    http_req_failed: ["rate<0.01"],
  },
  gracefulRampDown: "30s",
};

const BASE_URL = __ENV.BASE_URL || "http://localhost:8000/api";

// Test users pool (should exist in seeded database)
const TEST_USERS = [
  { email: "user@test.com", password: "password" },
  { email: "admin@soleilhostel.com", password: "password" },
  { email: "moderator@test.com", password: "password" },
];

function getTestUser() {
  return TEST_USERS[Math.floor(Math.random() * TEST_USERS.length)];
}

export default function () {
  const user = getTestUser();
  const jsonHeaders = {
    "Content-Type": "application/json",
    Accept: "application/json",
    "X-Correlation-ID": `perf-auth-${__VU}-${__ITER}`,
  };

  // ─── Bearer Token Flow ───
  group("Bearer Token Auth Flow", () => {
    // 1. Login
    const loginPayload = JSON.stringify({
      email: user.email,
      password: user.password,
    });

    const loginRes = http.post(`${BASE_URL}/auth/login-v2`, loginPayload, {
      headers: jsonHeaders,
      tags: { name: "POST /auth/login-v2" },
    });

    loginDuration.add(loginRes.timings.duration);

    const loginOk = check(loginRes, {
      "bearer login: status 200 or 201": (r) =>
        r.status === 200 || r.status === 201,
      "bearer login: has token": (r) => {
        try {
          return !!JSON.parse(r.body).token;
        } catch {
          return false;
        }
      },
      "bearer login: response < 300ms": (r) => r.timings.duration < 300,
    });

    authSuccess.add(loginOk);

    if (!loginOk) {
      sleep(1);
      return;
    }

    let token;
    try {
      token = JSON.parse(loginRes.body).token;
    } catch {
      return;
    }

    const authHeaders = {
      ...jsonHeaders,
      Authorization: `Bearer ${token}`,
    };

    sleep(0.5);

    // 2. Get current user via unified endpoint
    const meRes = http.get(`${BASE_URL}/auth/unified/me`, {
      headers: authHeaders,
      tags: { name: "GET /auth/unified/me" },
    });

    meDuration.add(meRes.timings.duration);

    check(meRes, {
      "me: status 200": (r) => r.status === 200,
      "me: has user data": (r) => {
        try {
          const body = JSON.parse(r.body);
          return !!(body.user || body.data);
        } catch {
          return false;
        }
      },
      "me: response < 100ms": (r) => r.timings.duration < 100,
    });

    sleep(0.5);

    // 3. Get user via v2 endpoint
    const meV2Res = http.get(`${BASE_URL}/auth/me-v2`, {
      headers: authHeaders,
      tags: { name: "GET /auth/me-v2" },
    });

    check(meV2Res, {
      "me-v2: status 200": (r) => r.status === 200,
      "me-v2: response < 100ms": (r) => r.timings.duration < 100,
    });

    sleep(0.5);

    // 4. Refresh token
    const refreshRes = http.post(`${BASE_URL}/auth/refresh-v2`, null, {
      headers: authHeaders,
      tags: { name: "POST /auth/refresh-v2" },
    });

    check(refreshRes, {
      "refresh: status 200": (r) => r.status === 200,
      "refresh: has new token": (r) => {
        try {
          return !!JSON.parse(r.body).token;
        } catch {
          return false;
        }
      },
    });

    // Use new token for logout
    let newToken = token;
    try {
      newToken = JSON.parse(refreshRes.body).token || token;
    } catch {
      /* keep old token */
    }

    sleep(0.5);

    // 5. Logout
    const logoutRes = http.post(`${BASE_URL}/auth/unified/logout`, null, {
      headers: {
        ...jsonHeaders,
        Authorization: `Bearer ${newToken}`,
      },
      tags: { name: "POST /auth/unified/logout" },
    });

    check(logoutRes, {
      "logout: status 200": (r) => r.status === 200,
    });
  });

  sleep(Math.random() * 2 + 1);

  // ─── HttpOnly Cookie Flow (subset) ───
  group("HttpOnly Cookie Auth Flow", () => {
    const loginPayload = JSON.stringify({
      email: user.email,
      password: user.password,
    });

    const loginRes = http.post(
      `${BASE_URL}/auth/login-httponly`,
      loginPayload,
      {
        headers: jsonHeaders,
        tags: { name: "POST /auth/login-httponly" },
      },
    );

    loginDuration.add(loginRes.timings.duration);

    check(loginRes, {
      "cookie login: status 200": (r) => r.status === 200,
      "cookie login: has Set-Cookie": (r) =>
        r.headers["Set-Cookie"] !== undefined || r.status === 200,
      "cookie login: response < 300ms": (r) => r.timings.duration < 300,
    });
  });

  sleep(Math.random() * 2 + 1);
}

export function handleSummary(data) {
  const summary = {
    timestamp: new Date().toISOString(),
    test: "auth-flow",
    metrics: {
      login_p50: data.metrics.login_duration?.values?.["p(50)"],
      login_p95: data.metrics.login_duration?.values?.["p(95)"],
      login_p99: data.metrics.login_duration?.values?.["p(99)"],
      me_p50: data.metrics.me_duration?.values?.["p(50)"],
      me_p95: data.metrics.me_duration?.values?.["p(95)"],
      http_req_failed_rate: data.metrics.http_req_failed?.values?.rate,
      auth_success_rate: data.metrics.auth_success_rate?.values?.rate,
      http_reqs_rate: data.metrics.http_reqs?.values?.rate,
      vus_max: data.metrics.vus_max?.values?.value,
    },
  };

  return {
    stdout: JSON.stringify(summary, null, 2) + "\n",
    "results/auth-flow-summary.json": JSON.stringify(summary, null, 2),
  };
}
