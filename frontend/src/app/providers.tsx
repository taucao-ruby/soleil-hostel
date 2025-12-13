import React from 'react'
import { AuthProvider } from '@/features/auth/AuthContext'

/**
 * Providers Component
 *
 * Wraps all context providers for the application.
 * Centralizes provider hierarchy for clean App.tsx.
 *
 * Current Providers:
 * - AuthProvider: Authentication state management
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
  return <AuthProvider>{children}</AuthProvider>
}

export default Providers
