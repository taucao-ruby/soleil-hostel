import React from 'react'
import { Outlet } from 'react-router-dom'
import Header from '@/shared/components/layout/Header'
import Footer from '@/shared/components/layout/Footer'

/**
 * Layout Component
 *
 * Provides consistent layout structure for all pages.
 * Includes Header, content area (Outlet), and Footer.
 *
 * This component is rendered inside the Router context,
 * allowing Header to use useNavigate() and other router hooks.
 */

const Layout: React.FC = () => {
  return (
    <div className="flex flex-col min-h-screen">
      <Header />
      <main className="flex-grow">
        <Outlet />
      </main>
      <Footer />
    </div>
  )
}

export default Layout
