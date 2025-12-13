/**
 * Security Utilities
 *
 * XSS Prevention and Input Sanitization
 */

/**
 * Escape HTML special characters to prevent XSS attacks
 *
 * Converts potentially dangerous characters to HTML entities:
 * - & → &amp;
 * - < → &lt;
 * - > → &gt;
 * - " → &quot;
 * - ' → &#039;
 */
export function escapeHtml(text: string): string {
  const map: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }
  return text.replace(/[&<>"']/g, char => map[char])
}

/**
 * Sanitize user input for safe display
 *
 * Trims whitespace and escapes HTML
 */
export function sanitizeInput(input: string): string {
  return escapeHtml(input.trim())
}

/**
 * Validate email format
 */
export function isValidEmail(email: string): boolean {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

/**
 * Validate URL format (basic check)
 */
export function isValidUrl(url: string): boolean {
  try {
    new URL(url)
    return true
  } catch {
    return false
  }
}
