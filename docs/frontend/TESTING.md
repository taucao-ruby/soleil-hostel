# Testing Configuration

> E2E testing với Playwright và unit testing setup

## Tổng quan

Testing strategy bao gồm:

- **Playwright**: E2E testing cho critical user journeys
- **Vitest**: Unit testing cho components và utilities
- **Testing Library**: Component testing best practices
- **MSW**: API mocking cho isolated testing

## 1. Playwright Configuration (`playwright.config.ts`)

```typescript
// playwright.config.ts
import { defineConfig, devices } from "@playwright/test";
import path from "path";

export default defineConfig({
  testDir: "./tests/e2e",
  outputDir: "./test-results",

  // Global setup
  globalSetup: require.resolve("./tests/e2e/global-setup"),

  // Global teardown
  globalTeardown: require.resolve("./tests/e2e/global-teardown"),

  // Timeout settings
  timeout: 30 * 1000,
  expect: {
    timeout: 5000,
  },

  // Run tests in files in parallel
  fullyParallel: true,

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Opt out of parallel tests on CI
  workers: process.env.CI ? 1 : undefined,

  // Reporter to use
  reporter: process.env.CI
    ? [["github"], ["html", { open: "never" }]]
    : [["html"], ["list"]],

  // Shared settings for all the projects below
  use: {
    baseURL: process.env.BASE_URL || "http://localhost:8000",
    trace: "on-first-retry",
    screenshot: "only-on-failure",
    video: "retain-on-failure",
    actionTimeout: 10000,
    navigationTimeout: 30000,

    // Record HAR files for API calls
    recordHar: {
      mode: "minimal",
      content: "embed",
    },
  },

  // Configure projects for major browsers
  projects: [
    {
      name: "chromium",
      use: {
        ...devices["Desktop Chrome"],
        contextOptions: {
          permissions: ["geolocation"],
        },
      },
    },

    {
      name: "firefox",
      use: { ...devices["Desktop Firefox"] },
    },

    {
      name: "webkit",
      use: { ...devices["Desktop Safari"] },
    },

    // Test against mobile viewports
    {
      name: "Mobile Chrome",
      use: { ...devices["Pixel 5"] },
    },
    {
      name: "Mobile Safari",
      use: { ...devices["iPhone 12"] },
    },

    // Brand new branded browser
    {
      name: "Microsoft Edge",
      use: { ...devices["Desktop Edge"], channel: "msedge" },
    },

    // Test with geolocation
    {
      name: "geolocation",
      use: {
        ...devices["Desktop Chrome"],
        contextOptions: {
          permissions: ["geolocation"],
          geolocation: { latitude: 37.7749, longitude: -122.4194 },
        },
      },
    },
  ],

  // Run your local dev server before starting the tests
  webServer: {
    command: "npm run dev",
    url: "http://localhost:5173",
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
  },

  // Global test configuration
  globalSetup: "./tests/e2e/global-setup.ts",
  globalTeardown: "./tests/e2e/global-teardown.ts",
});
```

## 2. Global Setup/Teardown

### global-setup.ts

```typescript
// tests/e2e/global-setup.ts
import { chromium, FullConfig } from "@playwright/test";
import { resetDatabase } from "./helpers/database";

async function globalSetup(config: FullConfig) {
  // Setup test database
  if (process.env.E2E_RESET_DB === "true") {
    await resetDatabase();
  }

  // Create admin user for testing
  const browser = await chromium.launch();
  const page = await browser.newPage();

  try {
    await page.goto("/admin/setup");
    await page.fill('[data-testid="admin-email"]', "admin@test.com");
    await page.fill('[data-testid="admin-password"]', "password123");
    await page.click('[data-testid="create-admin"]');

    // Wait for setup completion
    await page.waitForSelector('[data-testid="setup-complete"]');
  } finally {
    await browser.close();
  }
}

export default globalSetup;
```

### global-teardown.ts

```typescript
// tests/e2e/global-teardown.ts
import { FullConfig } from "@playwright/test";
import { cleanupTestData } from "./helpers/database";

async function globalTeardown(config: FullConfig) {
  // Cleanup test data
  await cleanupTestData();

  // Generate test report
  console.log("E2E tests completed");
}

export default globalTeardown;
```

## 3. Test Helpers

### Database Helpers

```typescript
// tests/e2e/helpers/database.ts
import { APIRequestContext } from "@playwright/test";

export async function resetDatabase() {
  const response = await fetch(`${process.env.API_URL}/test/reset`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${process.env.TEST_API_TOKEN}`,
    },
  });

  if (!response.ok) {
    throw new Error("Failed to reset database");
  }
}

export async function createTestUser(userData: any) {
  const response = await fetch(`${process.env.API_URL}/test/users`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${process.env.TEST_API_TOKEN}`,
    },
    body: JSON.stringify(userData),
  });

  return response.json();
}

export async function cleanupTestData() {
  const response = await fetch(`${process.env.API_URL}/test/cleanup`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${process.env.TEST_API_TOKEN}`,
    },
  });

  if (!response.ok) {
    console.warn("Failed to cleanup test data");
  }
}
```

### Page Object Models

```typescript
// tests/e2e/pom/LoginPage.ts
import { Page, Locator } from "@playwright/test";

export class LoginPage {
  readonly page: Page;
  readonly emailInput: Locator;
  readonly passwordInput: Locator;
  readonly loginButton: Locator;
  readonly errorMessage: Locator;

  constructor(page: Page) {
    this.page = page;
    this.emailInput = page.locator('[data-testid="email-input"]');
    this.passwordInput = page.locator('[data-testid="password-input"]');
    this.loginButton = page.locator('[data-testid="login-button"]');
    this.errorMessage = page.locator('[data-testid="error-message"]');
  }

  async goto() {
    await this.page.goto("/login");
  }

  async login(email: string, password: string) {
    await this.emailInput.fill(email);
    await this.passwordInput.fill(password);
    await this.loginButton.click();
  }

  async getErrorMessage() {
    return this.errorMessage.textContent();
  }
}
```

### Custom Test Utilities

```typescript
// tests/e2e/utils/test-utils.ts
import { Page, expect } from "@playwright/test";

export async function waitForLoadingToComplete(page: Page) {
  await page.waitForFunction(() => {
    const loaders = document.querySelectorAll('[data-testid="loading"]');
    return loaders.length === 0;
  });
}

export async function loginAs(
  page: Page,
  user: { email: string; password: string }
) {
  await page.goto("/login");
  await page.fill('[data-testid="email-input"]', user.email);
  await page.fill('[data-testid="password-input"]', user.password);
  await page.click('[data-testid="login-button"]');
  await page.waitForURL("**/dashboard");
}

export async function createTestBooking(page: Page, bookingData: any) {
  await page.goto("/booking");
  // Fill booking form...
  await page.click('[data-testid="submit-booking"]');
  await page.waitForURL("**/booking/**");
}

export async function expectToastMessage(page: Page, message: string) {
  await expect(
    page.locator(`[data-testid="toast"]:has-text("${message}")`)
  ).toBeVisible();
}
```

## 4. Sample E2E Tests

### Authentication Flow Test

```typescript
// tests/e2e/auth.spec.ts
import { test, expect } from "@playwright/test";
import { LoginPage } from "./pom/LoginPage";

test.describe("Authentication", () => {
  test("should login successfully", async ({ page }) => {
    const loginPage = new LoginPage(page);

    await loginPage.goto();
    await loginPage.login("user@test.com", "password123");

    await expect(page).toHaveURL("/dashboard");
    await expect(page.locator('[data-testid="welcome-message"]')).toContainText(
      "Welcome"
    );
  });

  test("should show error for invalid credentials", async ({ page }) => {
    const loginPage = new LoginPage(page);

    await loginPage.goto();
    await loginPage.login("invalid@test.com", "wrongpassword");

    const errorMessage = await loginPage.getErrorMessage();
    expect(errorMessage).toContain("Invalid credentials");
  });

  test("should redirect to login for protected routes", async ({ page }) => {
    await page.goto("/dashboard");
    await expect(page).toHaveURL("/login?redirect=%2Fdashboard");
  });
});
```

### Booking Flow Test

```typescript
// tests/e2e/booking.spec.ts
import { test, expect } from "@playwright/test";
import {
  loginAs,
  createTestBooking,
  expectToastMessage,
} from "./utils/test-utils";

test.describe("Booking Flow", () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, { email: "user@test.com", password: "password123" });
  });

  test("should create booking successfully", async ({ page }) => {
    await page.goto("/rooms");

    // Select first available room
    await page.click(
      '[data-testid="room-card"]:first-child [data-testid="book-now"]'
    );

    // Fill booking form
    await page.fill('[data-testid="guest-name"]', "John Doe");
    await page.fill('[data-testid="guest-email"]', "john@example.com");

    // Select dates
    await page.click('[data-testid="check-in"]');
    await page.click(
      '[data-testid="check-in"] + .react-datepicker__day--today + .react-datepicker__day'
    );

    await page.click('[data-testid="check-out"]');
    await page.click(
      '[data-testid="check-out"] + .react-datepicker__day--today + .react-datepicker__day + .react-datepicker__day'
    );

    // Submit booking
    await page.click('[data-testid="submit-booking"]');

    // Verify success
    await expectToastMessage(page, "Booking created successfully!");
    await expect(page).toHaveURL(/\/booking\/\d+/);
  });

  test("should validate booking dates", async ({ page }) => {
    await page.goto("/booking");

    // Try to set check-out before check-in
    await page.click('[data-testid="check-in"]');
    await page.click(
      '[data-testid="check-in"] + .react-datepicker__day--today + .react-datepicker__day'
    );

    await page.click('[data-testid="check-out"]');
    await page.click(
      '[data-testid="check-out"] + .react-datepicker__day--today'
    );

    // Submit should show error
    await page.click('[data-testid="submit-booking"]');
    await expect(page.locator('[data-testid="date-error"]')).toBeVisible();
  });
});
```

### API Integration Test

```typescript
// tests/e2e/api.spec.ts
import { test, expect } from "@playwright/test";

test.describe("API Integration", () => {
  test("should handle API errors gracefully", async ({ page, context }) => {
    // Mock API failure
    await context.route("**/api/rooms", (route) =>
      route.fulfill({
        status: 500,
        contentType: "application/json",
        body: JSON.stringify({ message: "Internal server error" }),
      })
    );

    await page.goto("/rooms");
    await expect(page.locator('[data-testid="error-message"]')).toBeVisible();
    await expect(page.locator('[data-testid="retry-button"]')).toBeVisible();
  });

  test("should handle network failures", async ({ page, context }) => {
    // Mock network failure
    await context.route("**/api/rooms", (route) => route.abort());

    await page.goto("/rooms");
    await expect(page.locator('[data-testid="network-error"]')).toBeVisible();
  });

  test("should refresh token automatically", async ({ page, context }) => {
    // First request succeeds, second fails with 401, third succeeds with new token
    let requestCount = 0;
    await context.route("**/api/bookings", (route) => {
      requestCount++;
      if (requestCount === 2) {
        route.fulfill({ status: 401 });
      } else {
        route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({ data: [] }),
        });
      }
    });

    await page.goto("/bookings");
    // Should succeed despite token refresh
    await expect(page.locator('[data-testid="bookings-list"]')).toBeVisible();
  });
});
```

## 5. Visual Regression Testing

```typescript
// tests/e2e/visual.spec.ts
import { test, expect } from "@playwright/test";

test.describe("Visual Regression", () => {
  test("should match homepage visual", async ({ page }) => {
    await page.goto("/");
    await expect(page).toHaveScreenshot("homepage.png", {
      fullPage: true,
      threshold: 0.1,
    });
  });

  test("should match booking form visual", async ({ page }) => {
    await page.goto("/booking");
    await expect(page.locator('[data-testid="booking-form"]')).toHaveScreenshot(
      "booking-form.png"
    );
  });

  test("should match mobile layout", async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto("/");
    await expect(page).toHaveScreenshot("homepage-mobile.png");
  });
});
```

## 6. Performance Testing

```typescript
// tests/e2e/performance.spec.ts
import { test, expect } from "@playwright/test";

test.describe("Performance", () => {
  test("should load homepage within 3 seconds", async ({ page }) => {
    const startTime = Date.now();
    await page.goto("/", { waitUntil: "networkidle" });
    const loadTime = Date.now() - startTime;
    expect(loadTime).toBeLessThan(3000);
  });

  test("should load rooms page within 2 seconds", async ({ page }) => {
    await page.goto("/rooms");
    const response = await page.waitForResponse("**/api/rooms");
    expect(response.status()).toBe(200);

    const loadTime = await page.evaluate(() => {
      const perfData = performance.getEntriesByType(
        "navigation"
      )[0] as PerformanceNavigationTiming;
      return perfData.loadEventEnd - perfData.fetchStart;
    });
    expect(loadTime).toBeLessThan(2000);
  });

  test("should have good Core Web Vitals", async ({ page }) => {
    await page.goto("/");

    // Wait for page to stabilize
    await page.waitForLoadState("networkidle");

    const metrics = await page.evaluate(() => {
      const observer = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        // Process web vitals metrics
      });
      observer.observe({ entryTypes: ["measure"] });

      // Trigger measurements
      // This would integrate with web-vitals library
    });

    // Assert on Core Web Vitals scores
    // LCP < 2.5s, FID < 100ms, CLS < 0.1
  });
});
```

## 7. Accessibility Testing

```typescript
// tests/e2e/accessibility.spec.ts
import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";

test.describe("Accessibility", () => {
  test("should pass accessibility audit on homepage", async ({ page }) => {
    await page.goto("/");

    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"])
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test("should have proper focus management", async ({ page }) => {
    await page.goto("/login");

    // Tab through form elements
    await page.keyboard.press("Tab");
    await expect(page.locator('[data-testid="email-input"]')).toBeFocused();

    await page.keyboard.press("Tab");
    await expect(page.locator('[data-testid="password-input"]')).toBeFocused();

    await page.keyboard.press("Tab");
    await expect(page.locator('[data-testid="login-button"]')).toBeFocused();
  });

  test("should support keyboard navigation", async ({ page }) => {
    await page.goto("/");

    // Navigate with keyboard only
    await page.keyboard.press("Tab");
    await page.keyboard.press("Enter"); // Activate link

    await expect(page).toHaveURL("/rooms");
  });
});
```

## 8. CI/CD Integration

### GitHub Actions Workflow

```yaml
# .github/workflows/e2e.yml
name: E2E Tests
on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: "18"
          cache: "npm"

      - name: Install dependencies
        run: npm ci

      - name: Build application
        run: npm run build

      - name: Run E2E tests
        run: npx playwright test
        env:
          BASE_URL: http://localhost:8000

      - name: Upload test results
        uses: actions/upload-artifact@v3
        if: always()
        with:
          name: test-results
          path: test-results/
          retention-days: 30
```

## Best Practices cho Testing

### 1. Test Organization

- **Page Objects**: Encapsulate page logic
- **Test Data**: Centralized test data management
- **Helpers**: Reusable test utilities
- **Fixtures**: Setup/teardown logic

### 2. Test Reliability

- **Wait Strategies**: Proper waiting for elements
- **Flakiness Prevention**: Retry logic và stable selectors
- **Isolation**: Independent test execution
- **Cleanup**: Proper test data cleanup

### 3. Test Coverage

- **Critical Paths**: Test main user journeys
- **Edge Cases**: Error conditions và edge cases
- **Cross-browser**: Multiple browser testing
- **Mobile**: Responsive design testing

### 4. Performance Testing

- **Load Testing**: Simulate multiple users
- **Performance Budgets**: Define acceptable metrics
- **Regression Testing**: Catch performance regressions
- **Monitoring**: Continuous performance monitoring

### 5. Accessibility Testing

- **Automated Tools**: Axe-core integration
- **Manual Testing**: Screen reader testing
- **Keyboard Navigation**: Full keyboard support
- **Color Contrast**: WCAG compliance

### 6. CI/CD Integration

- **Parallel Execution**: Faster test runs
- **Artifact Storage**: Test result preservation
- **Notifications**: Test failure alerts
- **Gates**: Prevent deployment on test failures
