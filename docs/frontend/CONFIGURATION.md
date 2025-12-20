# Configuration Files

> Cấu hình build, development tools và environment

## Tổng quan

Configuration files bao gồm:

- `vite.config.ts` - Build tool configuration
- `vite-plugin-csp-nonce.js` - CSP nonce injection
- `tsconfig.json` - TypeScript configuration
- `eslint.config.js` - Linting configuration
- `tailwind.config.js` - Styling configuration
- `postcss.config.js` - CSS processing

## 1. Vite Configuration (`vite.config.ts`)

```typescript
// vite.config.ts
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import vitePluginCspNonce from "./vite-plugin-csp-nonce";
import path from "path";

export default defineConfig(({ mode }) => ({
  plugins: [
    react({
      // Enable React Fast Refresh
      fastRefresh: true,
      // Enable JSX runtime
      jsxRuntime: "automatic",
    }),
    vitePluginCspNonce(),
  ],

  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
      "@components": path.resolve(__dirname, "./src/shared/components"),
      "@utils": path.resolve(__dirname, "./src/utils"),
      "@types": path.resolve(__dirname, "./src/types"),
    },
  },

  build: {
    target: "esnext",
    minify: "terser",
    sourcemap: mode === "development",
    outDir: "dist",
    assetsDir: "assets",

    rollupOptions: {
      output: {
        // Manual chunks for better caching
        manualChunks: {
          "react-vendor": ["react", "react-dom"],
          router: ["react-router-dom"],
          "ui-vendor": ["framer-motion", "react-datepicker"],
          utils: ["date-fns", "zod", "axios"],
        },

        // Asset naming
        assetFileNames: (assetInfo) => {
          const info = assetInfo.name?.split(".") ?? [];
          const extType = info[info.length - 1];
          if (
            /\.(png|jpe?g|svg|gif|tiff|bmp|ico)$/i.test(assetInfo.name ?? "")
          ) {
            return `assets/images/[name]-[hash][extname]`;
          }
          if (/\.(css)$/i.test(assetInfo.name ?? "")) {
            return `assets/css/[name]-[hash][extname]`;
          }
          return `assets/${extType}/[name]-[hash][extname]`;
        },

        chunkFileNames: "assets/js/[name]-[hash].js",
        entryFileNames: "assets/js/[name]-[hash].js",
      },
    },

    // Build optimizations
    terserOptions: {
      compress: {
        drop_console: mode === "production",
        drop_debugger: mode === "production",
      },
    },

    // Chunk size warnings
    chunkSizeWarningLimit: 1000,
  },

  server: {
    host: "0.0.0.0",
    port: 5173,
    open: false,
    cors: true,

    proxy: {
      "/api": {
        target:
          mode === "production"
            ? "https://api.soleil-hostel.com"
            : "http://backend:8000",
        changeOrigin: true,
        secure: false,
        configure: (proxy, options) => {
          proxy.on("error", (err, req, res) => {
            console.log("proxy error", err);
          });
          proxy.on("proxyReq", (proxyReq, req, res) => {
            console.log("Sending Request to the Target:", req.method, req.url);
          });
          proxy.on("proxyRes", (proxyRes, req, res) => {
            console.log(
              "Received Response from the Target:",
              proxyRes.statusCode,
              req.url
            );
          });
        },
      },
    },

    hmr: {
      overlay: true,
    },
  },

  // Environment variables
  envPrefix: "VITE_",

  // Preview server for production builds
  preview: {
    port: 4173,
    host: "0.0.0.0",
  },

  // Dependency optimization
  optimizeDeps: {
    include: [
      "react",
      "react-dom",
      "react-router-dom",
      "axios",
      "zod",
      "date-fns",
      "framer-motion",
    ],
    exclude: ["@vite/client", "@vite/env"],
  },

  // CSS configuration
  css: {
    devSourcemap: true,
    modules: {
      localsConvention: "camelCase",
    },
    preprocessorOptions: {
      scss: {
        additionalData: `@import "@/shared/styles/variables.scss";`,
      },
    },
  },

  // Test configuration
  test: {
    globals: true,
    environment: "jsdom",
    setupFiles: ["./src/test/setup.ts"],
    css: true,
  },
}));
```

## 2. CSP Nonce Plugin (`vite-plugin-csp-nonce.js`)

```javascript
// vite-plugin-csp-nonce.js
export default function vitePluginCspNonce() {
  let nonce = "";

  return {
    name: "vite-plugin-csp-nonce",

    buildStart() {
      // Generate nonce for build
      nonce = Buffer.from(Math.random().toString())
        .toString("base64")
        .slice(0, 16);
    },

    configureServer(server) {
      // Generate nonce for dev server
      server.middlewares.use((req, res, next) => {
        nonce = Buffer.from(Math.random().toString())
          .toString("base64")
          .slice(0, 16);
        res.locals.nonce = nonce;
        next();
      });
    },

    transformIndexHtml(html, ctx) {
      // Inject nonce vào <script> tags
      html = html.replace(/<script([^>]*)>/g, (match, attrs) => {
        if (attrs.includes("src=")) {
          // External scripts
          return match;
        }
        // Inline scripts
        return `<script nonce="${nonce}"${attrs}>`;
      });

      // Inject nonce vào <style> tags
      html = html.replace(/<style([^>]*)>/g, (match, attrs) => {
        return `<style nonce="${nonce}"${attrs}>`;
      });

      // Inject CSP meta tag cho development
      if (ctx?.bundle) {
        const cspContent = `
          default-src 'self';
          script-src 'self' 'nonce-${nonce}' 'unsafe-eval' 'unsafe-inline';
          style-src 'self' 'nonce-${nonce}' 'unsafe-inline';
          img-src 'self' data: https:;
          font-src 'self' data:;
          connect-src 'self' https://api.soleil-hostel.com http://localhost:8000;
          base-uri 'self';
          form-action 'self';
        `
          .replace(/\s+/g, " ")
          .trim();

        const cspMeta = `<meta http-equiv="Content-Security-Policy" content="${cspContent}">`;

        if (html.includes("</head>")) {
          html = html.replace("</head>", cspMeta + "</head>");
        }
      }

      return html;
    },
  };
}
```

## 3. TypeScript Configuration (`tsconfig.json`)

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "useDefineForClassFields": true,
    "lib": ["ES2022", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "skipLibCheck": true,
    "moduleResolution": "bundler",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "react-jsx",
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true,
    "noUncheckedIndexedAccess": true,
    "exactOptionalPropertyTypes": true,
    "baseUrl": ".",
    "paths": {
      "@/*": ["./src/*"],
      "@components/*": ["./src/shared/components/*"],
      "@utils/*": ["./src/utils/*"],
      "@types/*": ["./src/types/*"],
      "@features/*": ["./src/features/*"],
      "@services/*": ["./src/services/*"],
      "@shared/*": ["./src/shared/*"]
    },
    "types": ["vite/client", "vitest/globals"],
    "forceConsistentCasingInFileNames": true
  },
  "include": ["src", "tests", "*.config.ts", "*.config.js"],
  "exclude": ["node_modules", "dist", "build"],
  "ts-node": {
    "esm": true
  }
}
```

## 4. ESLint Configuration (`eslint.config.js`)

```javascript
// eslint.config.js
import js from "@eslint/js";
import tseslint from "typescript-eslint";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import reactRefresh from "eslint-plugin-react-refresh";
import importPlugin from "eslint-plugin-import";
import jsxA11y from "eslint-plugin-jsx-a11y";

export default tseslint.config(
  { ignores: ["dist", "build", "node_modules", "*.config.js"] },
  {
    extends: [
      js.configs.recommended,
      ...tseslint.configs.recommendedTypeChecked,
      ...tseslint.configs.stylisticTypeChecked,
    ],
    files: ["**/*.{ts,tsx}"],
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
      parserOptions: {
        project: "./tsconfig.json",
      },
    },
    plugins: {
      react,
      "react-hooks": reactHooks,
      "react-refresh": reactRefresh,
      import: importPlugin,
      "jsx-a11y": jsxA11y,
    },
    rules: {
      // React rules
      ...react.configs.recommended.rules,
      ...react.configs["jsx-runtime"].rules,
      ...reactHooks.configs.recommended.rules,
      "react-refresh/only-export-components": [
        "warn",
        { allowConstantExport: true },
      ],
      "react/prop-types": "off", // Using TypeScript
      "react/jsx-uses-react": "off", // JSX runtime
      "react/react-in-jsx-scope": "off", // JSX runtime

      // TypeScript rules
      "@typescript-eslint/no-unused-vars": [
        "error",
        {
          argsIgnorePattern: "^_",
          varsIgnorePattern: "^_",
          ignoreRestSiblings: true,
        },
      ],
      "@typescript-eslint/no-explicit-any": "warn",
      "@typescript-eslint/prefer-nullish-coalescing": "error",
      "@typescript-eslint/prefer-optional-chain": "error",
      "@typescript-eslint/no-unnecessary-condition": "error",
      "@typescript-eslint/no-confusing-void-expression": "error",
      "@typescript-eslint/consistent-type-definitions": ["error", "interface"],
      "@typescript-eslint/consistent-type-imports": [
        "error",
        { prefer: "type-imports", fixStyle: "inline-type-imports" },
      ],

      // Import rules
      "import/order": [
        "error",
        {
          groups: [
            "builtin",
            "external",
            "internal",
            "parent",
            "sibling",
            "index",
          ],
          "newlines-between": "always",
          alphabetize: { order: "asc", caseInsensitive: true },
        },
      ],
      "import/no-unresolved": "error",
      "import/no-cycle": "error",

      // Accessibility rules
      ...jsxA11y.configs.recommended.rules,
      "jsx-a11y/anchor-is-valid": [
        "error",
        {
          components: ["Link"],
          specialLink: ["hrefLeft", "hrefRight"],
          aspects: ["invalidHref", "preferButton"],
        },
      ],

      // General rules
      "no-console": ["warn", { allow: ["warn", "error"] }],
      "prefer-const": "error",
      "no-var": "error",
      "object-shorthand": "error",
      "prefer-arrow-callback": "error",
      "prefer-template": "error",
    },
    settings: {
      react: {
        version: "detect",
      },
      "import/resolver": {
        typescript: {
          alwaysTryTypes: true,
        },
      },
    },
  },
  // Configuration files
  {
    files: ["*.config.{js,ts}", "vite.config.{js,ts}"],
    rules: {
      "@typescript-eslint/no-var-requires": "off",
      "no-console": "off",
    },
  },
  // Test files
  {
    files: ["**/*.test.{ts,tsx}", "**/*.spec.{ts,tsx}"],
    rules: {
      "@typescript-eslint/no-explicit-any": "off",
      "react/display-name": "off",
    },
  }
);
```

## 5. Tailwind Configuration (`tailwind.config.js`)

```javascript
// tailwind.config.js
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
    "./tests/**/*.{js,ts,jsx,tsx}",
  ],

  darkMode: "class", // or 'media'

  theme: {
    container: {
      center: true,
      padding: "2rem",
      screens: {
        "2xl": "1400px",
      },
    },

    extend: {
      colors: {
        border: "hsl(var(--border))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
        background: "hsl(var(--background))",
        foreground: "hsl(var(--foreground))",
        primary: {
          DEFAULT: "hsl(var(--primary))",
          foreground: "hsl(var(--primary-foreground))",
        },
        secondary: {
          DEFAULT: "hsl(var(--secondary))",
          foreground: "hsl(var(--secondary-foreground))",
        },
        destructive: {
          DEFAULT: "hsl(var(--destructive))",
          foreground: "hsl(var(--destructive-foreground))",
        },
        muted: {
          DEFAULT: "hsl(var(--muted))",
          foreground: "hsl(var(--muted-foreground))",
        },
        accent: {
          DEFAULT: "hsl(var(--accent))",
          foreground: "hsl(var(--accent-foreground))",
        },
        popover: {
          DEFAULT: "hsl(var(--popover))",
          foreground: "hsl(var(--popover-foreground))",
        },
        card: {
          DEFAULT: "hsl(var(--card))",
          foreground: "hsl(var(--card-foreground))",
        },
      },

      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },

      fontFamily: {
        sans: ["Inter", "system-ui", "sans-serif"],
        serif: ["Playfair Display", "serif"],
        mono: ["JetBrains Mono", "monospace"],
      },

      fontSize: {
        "2xs": ["0.625rem", { lineHeight: "0.75rem" }],
      },

      keyframes: {
        "accordion-down": {
          from: { height: 0 },
          to: { height: "var(--radix-accordion-content-height)" },
        },
        "accordion-up": {
          from: { height: "var(--radix-accordion-content-height)" },
          to: { height: 0 },
        },
        "fade-in": {
          "0%": { opacity: "0" },
          "100%": { opacity: "1" },
        },
        "fade-out": {
          "0%": { opacity: "1" },
          "100%": { opacity: "0" },
        },
        "slide-in-from-left": {
          "0%": { transform: "translateX(-100%)" },
          "100%": { transform: "translateX(0)" },
        },
        "slide-in-from-right": {
          "0%": { transform: "translateX(100%)" },
          "100%": { transform: "translateX(0)" },
        },
        "slide-in-from-top": {
          "0%": { transform: "translateY(-100%)" },
          "100%": { transform: "translateY(0)" },
        },
        "slide-in-from-bottom": {
          "0%": { transform: "translateY(100%)" },
          "100%": { transform: "translateY(0)" },
        },
      },

      animation: {
        "accordion-down": "accordion-down 0.2s ease-out",
        "accordion-up": "accordion-up 0.2s ease-out",
        "fade-in": "fade-in 0.5s ease-in-out",
        "fade-out": "fade-out 0.5s ease-out",
        "slide-in-from-left": "slide-in-from-left 0.3s ease-out",
        "slide-in-from-right": "slide-in-from-right 0.3s ease-out",
        "slide-in-from-top": "slide-in-from-top 0.3s ease-out",
        "slide-in-from-bottom": "slide-in-from-bottom 0.3s ease-out",
      },

      boxShadow: {
        soft: "0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)",
        medium:
          "0 4px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)",
        hard: "0 10px 40px -10px rgba(0, 0, 0, 0.2)",
      },
    },
  },

  plugins: [
    require("@tailwindcss/forms"),
    require("@tailwindcss/typography"),
    require("@tailwindcss/aspect-ratio"),
  ],
};
```

## 6. PostCSS Configuration (`postcss.config.js`)

```javascript
// postcss.config.js
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {
      grid: true,
    },
    cssnano:
      process.env.NODE_ENV === "production"
        ? {
            preset: [
              "default",
              {
                discardComments: { removeAll: true },
                normalizeWhitespace: false,
              },
            ],
          }
        : false,
  },
};
```

## 7. Environment Configuration

### .env.example

```bash
# API Configuration
VITE_API_URL=http://localhost:8000/api

# App Configuration
VITE_APP_NAME=Soleil Hostel
VITE_APP_ENV=development

# Analytics (Production only)
VITE_GA_TRACKING_ID=
VITE_MIXPANEL_TOKEN=

# Feature Flags
VITE_ENABLE_ANALYTICS=false
VITE_ENABLE_ERROR_REPORTING=false

# Security
VITE_CSP_ENABLED=true
```

### .env.local (Development)

```bash
VITE_API_URL=http://localhost:8000/api
VITE_APP_ENV=development
VITE_ENABLE_ANALYTICS=false
VITE_ENABLE_ERROR_REPORTING=false
```

### .env.production

```bash
VITE_API_URL=https://api.soleil-hostel.com
VITE_APP_ENV=production
VITE_GA_TRACKING_ID=GA_MEASUREMENT_ID
VITE_ENABLE_ANALYTICS=true
VITE_ENABLE_ERROR_REPORTING=true
```

## Best Practices cho Configuration

### 1. Environment Variables

- **VITE\_ prefix**: Only expose necessary vars to client
- **Type safety**: Validate env vars at build time
- **Defaults**: Provide sensible defaults
- **Documentation**: Document all environment variables

### 2. Build Optimization

- **Code splitting**: Logical chunk splitting
- **Asset optimization**: Compress and optimize assets
- **Caching**: Long-term caching with hashed filenames
- **Bundle analysis**: Monitor bundle size

### 3. Development Experience

- **Fast refresh**: React Fast Refresh configuration
- **Source maps**: Development source maps
- **Error overlay**: Helpful error messages
- **Proxy configuration**: API proxy for development

### 4. TypeScript Configuration

- **Strict mode**: Maximum type safety
- **Path mapping**: Clean import paths
- **Declaration files**: Type definitions for libraries
- **Build performance**: Optimal compilation settings

### 5. Linting & Code Quality

- **Consistent rules**: Team-wide code standards
- **TypeScript integration**: Type-aware linting
- **Import sorting**: Organized imports
- **Accessibility**: WCAG compliance checking

### 6. CSS Configuration

- **Design system**: Consistent design tokens
- **Dark mode**: Theme switching support
- **Responsive design**: Mobile-first approach
- **Performance**: Optimized CSS output
