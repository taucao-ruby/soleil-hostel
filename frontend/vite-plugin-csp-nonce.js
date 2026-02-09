/**
 * Vite Plugin: CSP Nonce Injection
 *
 * Tự động inject nonce vào tất cả <script> và <style> tags trong production build
 * Để React hoạt động với strict CSP
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
