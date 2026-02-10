import type { NavigateFunction } from 'react-router-dom'

/**
 * Navigation Service — Programmatic navigation outside React component tree.
 *
 * Problem: The axios interceptor in api.ts runs outside React's component tree,
 * so it cannot use useNavigate(). Using window.location.href causes a full page
 * reload, losing all React state, form inputs, and in-memory data.
 *
 * Solution: A module-level navigate function that gets set by a component
 * inside the Router tree. Falls back to window.location.href only if the
 * router hasn't initialized yet.
 *
 * Setup: A root-level component inside RouterProvider calls setNavigate()
 * via useEffect. See NavigationSetter in router.tsx.
 */

let _navigate: NavigateFunction | null = null

/**
 * Register the React Router navigate function.
 * Called once from a component inside the Router tree.
 */
export function setNavigate(nav: NavigateFunction): void {
  _navigate = nav
}

/**
 * Navigate programmatically from anywhere in the app.
 * Uses React Router if available, falls back to hard redirect.
 */
export function appNavigate(to: string): void {
  if (_navigate) {
    _navigate(to)
  } else {
    // Fallback only if router not yet initialized
    window.location.href = to
  }
}
