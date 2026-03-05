import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import '@/shared/styles/index.css'
import 'react-toastify/dist/ReactToastify.css'
import App from './app/App'
import { initWebVitals } from '@/shared/utils/webVitals'

// Initialize Web Vitals monitoring for performance tracking
if (import.meta.env.PROD) {
  initWebVitals()
}

function mountApp() {
  const container = document.getElementById('root')
  if (!container) {
    if (import.meta.env.DEV) {
      // eslint-disable-next-line no-console
      console.error('React mount failed: #root element not found in the DOM.')
    }
    return
  }

  createRoot(container).render(
    <StrictMode>
      <App />
    </StrictMode>
  )
}

if (document.readyState === 'loading') {
  window.addEventListener('DOMContentLoaded', mountApp)
} else {
  mountApp()
}
