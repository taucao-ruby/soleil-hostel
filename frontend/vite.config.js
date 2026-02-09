import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import vitePluginCspNonce from './vite-plugin-csp-nonce';
import path from 'path';
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
        proxy: {
            '/api': {
                target: 'http://backend:8000',
                changeOrigin: true,
                secure: false,
            },
        },
        open: false,
        cors: true,
        hmr: {
            overlay: true,
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: './src/test/setup.ts',
        exclude: ['**/tests/e2e/**', '**/node_modules/**'],
    },
});
