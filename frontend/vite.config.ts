/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import vitePluginCspNonce from './vite-plugin-csp-nonce'
import path from 'path'
import { existsSync, readFileSync } from 'node:fs'

export interface CoverageThresholds {
  lines: number
  branches: number
  functions: number
  statements: number
}

const COVERAGE_METRICS = ['lines', 'branches', 'functions', 'statements'] as const

const ZERO_THRESHOLDS: CoverageThresholds = {
  lines: 0,
  branches: 0,
  functions: 0,
  statements: 0,
}

// Ratchet state file — the single source of truth for coverage floors.
// Resolved next to this config so it works regardless of the CI working directory.
const THRESHOLD_FILE = path.resolve(__dirname, './coverage-thresholds.json')

/**
 * Read the ratcheted coverage floors from coverage-thresholds.json.
 *
 * Pure + synchronous: Vite loads its config synchronously, so this must never be
 * async. It also never throws — on a missing, unreadable, or structurally invalid
 * file it falls back to all-zero floors (enforcement off) and says so loudly, so
 * the test run itself can never be broken by the state file.
 *
 * Integrity: every metric must be present and a finite number in [0, 100];
 * otherwise we reset to zero. This stops a corrupted or hand-edited file from
 * silently locking in garbage floors. We deliberately do NOT cryptographically
 * sign the file — overkill for a P3 coverage gate. The human guardrail is
 * CODEOWNERS review on coverage-thresholds.json (see RATCHET.md).
 */
export function readRatchetedThresholds(): CoverageThresholds {
  if (!existsSync(THRESHOLD_FILE)) {
    console.warn(
      '[coverage-ratchet] coverage-thresholds.json not found — falling back to 0 ' +
        '(bootstrap/first run). Coverage thresholds are NOT enforced this run.'
    )
    return { ...ZERO_THRESHOLDS }
  }

  let parsed: unknown
  try {
    parsed = JSON.parse(readFileSync(THRESHOLD_FILE, 'utf8'))
  } catch {
    console.warn('[coverage-ratchet] INVALID threshold file — resetting to 0')
    return { ...ZERO_THRESHOLDS }
  }

  if (typeof parsed !== 'object' || parsed === null) {
    console.warn('[coverage-ratchet] INVALID threshold file — resetting to 0')
    return { ...ZERO_THRESHOLDS }
  }

  const record = parsed as Record<string, unknown>
  const floors = { ...ZERO_THRESHOLDS }
  for (const metric of COVERAGE_METRICS) {
    const value = record[metric]
    if (typeof value !== 'number' || !Number.isFinite(value) || value < 0 || value > 100) {
      console.warn('[coverage-ratchet] INVALID threshold file — resetting to 0')
      return { ...ZERO_THRESHOLDS }
    }
    floors[metric] = value
  }
  return floors
}

// Shared backend proxy: keeps the SPA same-origin with the API in BOTH `vite`
// (dev, :5173) and `vite preview` (E2E, :4173). Same-origin is what lets the
// httpOnly SameSite auth cookie flow reliably — a cross-port setup (:4173 ->
// :8000) makes the cookie flaky, notably under Chrome mobile-device emulation.
const apiProxy = {
  '/api': {
    target: process.env.VITE_PROXY_TARGET || 'http://127.0.0.1:8000',
    changeOrigin: true,
    secure: false,
  },
}

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    vitePluginCspNonce(), // Inject CSP nonce vào HTML bundle
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    // Output build into the Laravel public build directory so Laravel can serve the static assets
    outDir: 'dist',
    assetsDir: 'assets',
    manifest: true,
    target: 'esnext',
    minify: 'terser',
    cssMinify: true,
    rollupOptions: {
      output: {
        manualChunks: {
          'react-vendor': ['react', 'react-dom'],
        },
      },
    },
  },
  server: {
    // The `dev` npm script runs `vite --port 5173` so match that default here to
    // keep behavior consistent. Set `open: false` to avoid OS-specific `xdg-open` warnings
    // when running inside containers or headless environments.
    host: '0.0.0.0',
    port: 5173,
    proxy: apiProxy,

    open: false,
    cors: true,
    hmr: {
      overlay: true,
    },
  },
  // `vite preview` serves the production build for the Playwright E2E suite.
  // Proxy /api to the backend so the SPA stays same-origin (see apiProxy), which
  // is what makes the httpOnly auth cookie work across every browser/project.
  preview: {
    port: 4173,
    proxy: apiProxy,
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    exclude: ['**/tests/e2e/**', '**/node_modules/**'],
    coverage: {
      provider: 'v8',
      // 'json' is kept for the existing Codecov upload (coverage-final.json);
      // 'json-summary' feeds scripts/ratchet-coverage.sh.
      reporter: ['json', 'json-summary', 'text', 'lcov'],
      reportsDirectory: './coverage',
      // RATCHET: managed by CI — do not edit manually
      thresholds: readRatchetedThresholds(),
    },
  },
})
