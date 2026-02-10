import React from 'react'

/**
 * Providers Component
 *
 * Wraps non-router context providers for the application.
 * Centralizes provider hierarchy for clean App.tsx.
 *
 * NOTE: AuthProvider has been moved inside the Router tree (see router.tsx)
 * so it can access React Router hooks (useNavigate, useLocation, etc.).
 *
 * Future Providers:
 * - ThemeProvider: Light/dark mode
 * - I18nProvider: Internationalization
 * - QueryClientProvider: React Query (if needed)
 */

interface ProvidersProps {
  children: React.ReactNode
}

const Providers: React.FC<ProvidersProps> = ({ children }) => {
  return <>{children}</>
}

export default Providers
