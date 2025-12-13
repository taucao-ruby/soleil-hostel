import React from 'react'
import ErrorBoundary from '@/shared/components/ErrorBoundary'
import Providers from './providers'
import Router from './router'

/**
 * App Component - Root Application Component
 *
 * Architecture:
 * 1. ErrorBoundary: Catches runtime errors, shows fallback UI
 * 2. Providers: Wraps AuthContext and future providers
 * 3. Router: React Router v7 with routes configuration
 *
 * This is the entry point for the feature-sliced architecture.
 * All global providers and error handling start here.
 */

const App: React.FC = () => {
  return (
    <ErrorBoundary>
      <Providers>
        <Router />
      </Providers>
    </ErrorBoundary>
  )
}

export default App
