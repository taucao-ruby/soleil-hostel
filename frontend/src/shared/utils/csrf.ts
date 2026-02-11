/**
 * CSRF Token Management Utility
 *
 * SECURITY NOTE: HttpOnly cookie authentication provides inherent CSRF protection.
 * The browser sends the httpOnly cookie automatically, but JavaScript (and therefore
 * XSS) cannot read it. The X-XSRF-TOKEN header acts as a double-submit cookie pattern
 * for additional defence-in-depth on state-changing requests.
 *
 * IMPORTANT: CSRF tokens are stored in sessionStorage (NOT localStorage)
 * - sessionStorage is cleared when browser closes
 * - Reduces attack surface for CSRF token theft
 * - httpOnly cookie handles actual token security
 */

/**
 * Get CSRF token from sessionStorage
 *
 * Returns null if not set. Frontend adds this to X-XSRF-TOKEN header
 * for CSRF protection on non-GET requests.
 */
export function getCsrfToken(): string | null {
  return sessionStorage.getItem('csrf_token')
}

/**
 * Save CSRF token to sessionStorage
 *
 * Called after successful login/refresh response.
 * Backend includes csrf_token in response body for this purpose.
 */
export function setCsrfToken(token: string): void {
  sessionStorage.setItem('csrf_token', token)
}

/**
 * Clear CSRF token from sessionStorage
 *
 * Called on logout to remove CSRF token.
 * SessionStorage clears on browser close anyway, but good practice to explicitly clear.
 */
export function clearCsrfToken(): void {
  sessionStorage.removeItem('csrf_token')
}
