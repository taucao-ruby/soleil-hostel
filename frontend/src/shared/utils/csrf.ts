/**
 * CSRF Token Management Utility
 *
 * SECURITY NOTE: The primary CSRF defence is SameSite=Strict on the httpOnly
 * soleil_token cookie. Cross-origin requests are blocked by the browser before
 * they can carry the authentication cookie.
 *
 * The csrf_token stored here is returned by the login/refresh endpoints and
 * sent as X-XSRF-TOKEN on state-changing requests. This header is NOT currently
 * validated server-side; it provides a supplementary XSS barrier because a
 * cross-origin attacker cannot read sessionStorage to forge the header value.
 *
 * IMPORTANT: CSRF tokens are stored in sessionStorage (NOT localStorage)
 * - sessionStorage is cleared when the browser tab closes
 * - Reduces window of exposure for the supplementary token
 * - httpOnly cookie + SameSite=Strict provides the actual CSRF protection
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
