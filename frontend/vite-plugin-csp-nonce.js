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

export default function vitePluginCspNonce() {
  return {
    name: 'vite-plugin-csp-nonce',

    /**
     * Khi build HTML, inject nonce placeholder
     * Placeholder sẽ được replace bằng real nonce ở server
     */
    transformIndexHtml: html => {
      // Inject nonce placeholder vào tất cả inline scripts
      // Format: {{ NONCE }} (Laravel Blade sẽ không parse nó)

      // Pattern 1: <script> tags (app bundle + React)
      html = html.replace(/<script([^>]*)>/g, (match, attrs) => {
        // Bỏ qua external scripts (có src attribute)
        if (attrs.includes('src=')) {
          return match
        }
        // Thêm nonce vào inline scripts
        return `<script nonce="{{ csp_nonce() }}"${attrs}>`
      })

      // Pattern 2: <style> tags trong bundle
      html = html.replace(/<style([^>]*)>/g, (match, attrs) => {
        return `<style nonce="{{ csp_nonce() }}"${attrs}>`
      })

      // CSP is set via HTTP headers (backend middleware), not via meta tag

      return html
    },

    /**
     * Transform HTML trong dev mode
     * Dev mode không cần nonce (unsafe-eval + unsafe-inline allowed)
     */
    apply: 'build', // Chỉ apply khi build, không dev
  }
}
