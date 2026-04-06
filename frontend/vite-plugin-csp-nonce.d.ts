import type { Plugin } from 'vite'
/**
 * Vite Plugin: CSP Nonce Injection
 *
 * Injects nonce placeholders into all <script> and <style> tags in the production build
 * so React works with a strict Content-Security-Policy.
 *
 * IMPORTANT: The placeholder `{{ csp_nonce() }}` is a Laravel Blade helper.
 * The built HTML MUST be served through a Blade template (e.g. resources/views/app.blade.php)
 * so Laravel can replace the placeholder with a real per-request nonce.
 * Serving the HTML as a static file will leave the placeholder unresolved.
 */
export default function vitePluginCspNonce(): Plugin
