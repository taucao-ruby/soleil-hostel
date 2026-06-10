import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { NavigateFunction } from 'react-router-dom'

/**
 * navigation.ts keeps the registered navigate function in module-level state,
 * so every test re-imports a fresh module instance via resetModules.
 */
async function importNavigation() {
  return import('@/shared/lib/navigation')
}

beforeEach(() => {
  vi.resetModules()
})

describe('navigation service', () => {
  it('appNavigate routes through the registered React Router navigate function', async () => {
    const { setNavigate, appNavigate } = await importNavigation()
    const navigate = vi.fn()

    setNavigate(navigate as unknown as NavigateFunction)
    appNavigate('/dashboard')

    expect(navigate).toHaveBeenCalledTimes(1)
    expect(navigate).toHaveBeenCalledWith('/dashboard')
  })

  it('appNavigate falls back to window.location.href when no navigate is registered', async () => {
    const { appNavigate } = await importNavigation()

    // jsdom only implements hash navigation, so a hash target observes the
    // window.location.href fallback without "Not implemented" noise.
    appNavigate('#fallback-target')

    expect(window.location.hash).toBe('#fallback-target')
  })
})
