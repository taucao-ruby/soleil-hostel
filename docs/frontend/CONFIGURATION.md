# Configuration Files

> Build, development tools, and environment configuration

## Overview

| File                     | Purpose                       |
| ------------------------ | ----------------------------- |
| `vite.config.ts`         | Build + dev server + test     |
| `vite-plugin-csp-nonce.js` | CSP nonce injection         |
| `tsconfig.json`          | TypeScript project references |
| `tsconfig.app.json`      | App TypeScript configuration  |
| `tsconfig.node.json`     | Node TypeScript configuration |
| `eslint.config.js`       | ESLint + Prettier             |
| `tailwind.config.js`     | Tailwind CSS                  |
| `postcss.config.js`      | PostCSS processing            |

---

## 1. Vite Configuration (`vite.config.ts`)

```typescript
import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import vitePluginCspNonce from './vite-plugin-csp-nonce'
import path from 'path'

export default defineConfig({
  plugins: [
    react(),
    vitePluginCspNonce(),
  ],

  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },

  build: {
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
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: process.env.VITE_PROXY_TARGET || 'http://backend:8000',
        changeOrigin: true,
        secure: false,
      },
    },
    open: false,
    cors: true,
    hmr: { overlay: true },
  },

  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    exclude: ['**/tests/e2e/**', '**/node_modules/**'],
  },
})
```

### Key Points

- **Single path alias**: Only `@` maps to `./src` (no `@components`, `@utils`, etc.)
- **Single manual chunk**: Only `react-vendor` (no separate ui-vendor or utils-vendor)
- **Config imported from** `vitest/config` (not `vite`) to support inline test config
- **Proxy target** configurable via `VITE_PROXY_TARGET` env variable
- **No scss/css modules** configuration

---

## 2. CSP Nonce Plugin (`vite-plugin-csp-nonce.js`)

Custom Vite plugin that injects Content Security Policy nonces into inline `<script>` and `<style>` tags during HTML transformation. Uses `{{ csp_nonce() }}` placeholder for Laravel server-side nonce generation.

---

## 3. TypeScript Configuration

### `tsconfig.json` (Project Root)

```json
{
  "files": [],
  "references": [
    { "path": "./tsconfig.app.json" },
    { "path": "./tsconfig.node.json" }
  ]
}
```

Uses TypeScript **project references** to separate app and build config.

### `tsconfig.app.json`

```json
{
  "compilerOptions": {
    "target": "ES2020",
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "moduleResolution": "bundler",
    "jsx": "react-jsx",
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true,
    "baseUrl": ".",
    "paths": {
      "@/*": ["./src/*"]
    }
  },
  "include": ["src/**/*"]
}
```

### Key TypeScript Settings

- **Strict mode** enabled
- **Single path alias**: `@/*` maps to `./src/*`
- **Bundler module resolution** (not Node)
- **React JSX runtime** (automatic)
- **No `@components`, `@services`, `@features` aliases** - only `@`

---

## 4. ESLint Configuration (`eslint.config.js`)

Uses flat config format with:

- `@eslint/js` recommended rules
- `typescript-eslint` recommended rules
- `eslint-plugin-react-hooks` recommended rules
- `eslint-plugin-react-refresh` for HMR
- `eslint-config-prettier` + `eslint-plugin-prettier` for formatting

### Key Rules

```javascript
rules: {
  ...reactHooks.configs.recommended.rules,
  'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
  '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
}
```

---

## 5. Tailwind Configuration (`tailwind.config.js`)

```javascript
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
```

**Note:** No Tailwind plugins installed (`@tailwindcss/forms`, `@tailwindcss/typography`, etc.).

---

## 6. PostCSS Configuration (`postcss.config.js`)

```javascript
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
```

---

## 7. Environment Variables

### Required Variables

| Variable           | Description               | Required In  |
| ------------------ | ------------------------- | ------------ |
| `VITE_API_URL`     | Backend API base URL      | Production   |
| `VITE_PROXY_TARGET`| Dev server proxy target   | Development  |

### Development Defaults

```bash
# No .env file required for development
# Defaults: VITE_API_URL = http://localhost:8000/api
# Defaults: VITE_PROXY_TARGET = http://backend:8000
```

All environment variables must be prefixed with `VITE_` to be exposed to client-side code.
