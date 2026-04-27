import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosHeaders, AxiosError } from 'axios'

// Mock csrf utilities before importing api
vi.mock('@/shared/utils/csrf', () => ({
  getCsrfToken: vi.fn(),
  setCsrfToken: vi.fn(),
  clearCsrfToken: vi.fn(),
}))

// Mock navigation to prevent real redirects in tests
vi.mock('@/shared/lib/navigation', () => ({
  appNavigate: vi.fn(),
}))

import { getCsrfToken, setCsrfToken } from '@/shared/utils/csrf'
import { appNavigate } from '@/shared/lib/navigation'
import api from './api'

interface RequestConfig {
  method: string
  headers: AxiosHeaders
  url: string
}

interface InterceptorHandler {
  fulfilled?: (config: RequestConfig) => Promise<RequestConfig> | RequestConfig
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  rejected?: (error: any) => Promise<any>
}

function makeAxiosError(status: number, url = '/api/protected'): AxiosError {
  const err = new AxiosError('Request failed')
  err.config = { method: 'get', url, headers: new AxiosHeaders() } as never
  err.response = { status, data: {}, headers: {}, config: err.config, statusText: '' } as never
  return err
}

async function runResponseInterceptorError(error: AxiosError): Promise<unknown> {
  const interceptor = api.interceptors.response as unknown as { handlers: InterceptorHandler[] }
  for (const handler of interceptor.handlers) {
    if (handler?.rejected) {
      return handler.rejected(error)
    }
  }
  throw new Error('No response error interceptor found')
}

describe('API Client', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('has withCredentials enabled', () => {
    expect(api.defaults.withCredentials).toBe(true)
  })

  it('sets Content-Type to application/json', () => {
    expect(api.defaults.headers['Content-Type']).toBe('application/json')
  })

  it('sets Accept to application/json', () => {
    expect(api.defaults.headers.Accept).toBe('application/json')
  })

  describe('Request Interceptor — CSRF Token', () => {
    it('adds X-XSRF-TOKEN header on POST requests when token exists', async () => {
      vi.mocked(getCsrfToken).mockReturnValue('test-csrf-token')

      const config: RequestConfig = {
        method: 'post',
        headers: new AxiosHeaders(),
        url: '/test',
      }

      const interceptor = api.interceptors.request as unknown as { handlers: InterceptorHandler[] }
      const handlers = interceptor.handlers
      let result = config
      for (const handler of handlers) {
        if (handler && handler.fulfilled) {
          result = await handler.fulfilled(result)
        }
      }

      expect(result.headers['X-XSRF-TOKEN']).toBe('test-csrf-token')
    })

    it('does not add X-XSRF-TOKEN on GET requests', async () => {
      vi.mocked(getCsrfToken).mockReturnValue('test-csrf-token')

      const config: RequestConfig = {
        method: 'get',
        headers: new AxiosHeaders(),
        url: '/test',
      }

      const interceptor = api.interceptors.request as unknown as { handlers: InterceptorHandler[] }
      const handlers = interceptor.handlers
      let result = config
      for (const handler of handlers) {
        if (handler && handler.fulfilled) {
          result = await handler.fulfilled(result)
        }
      }

      expect(result.headers['X-XSRF-TOKEN']).toBeUndefined()
    })

    it('does not add X-XSRF-TOKEN when no token exists', async () => {
      vi.mocked(getCsrfToken).mockReturnValue(null)

      const config: RequestConfig = {
        method: 'post',
        headers: new AxiosHeaders(),
        url: '/test',
      }

      const interceptor = api.interceptors.request as unknown as { handlers: InterceptorHandler[] }
      const handlers = interceptor.handlers
      let result = config
      for (const handler of handlers) {
        if (handler && handler.fulfilled) {
          result = await handler.fulfilled(result)
        }
      }

      expect(result.headers['X-XSRF-TOKEN']).toBeUndefined()
    })
  })

  describe('Response Interceptor — 401 Token Refresh', () => {
    beforeEach(() => {
      vi.clearAllMocks()
      // Reset the refresh mutex between tests by re-importing won't work,
      // so we rely on the fact that each test controls mock responses fully.
    })

    it('does not attempt refresh when no csrf token exists (user not logged in)', async () => {
      vi.mocked(getCsrfToken).mockReturnValue(null)

      const err = makeAxiosError(401)
      await expect(runResponseInterceptorError(err)).rejects.toThrow()
      expect(setCsrfToken).not.toHaveBeenCalled()
    })

    it('does not attempt refresh on non-401 errors', async () => {
      vi.mocked(getCsrfToken).mockReturnValue('csrf-token')

      const err = makeAxiosError(500)
      await expect(runResponseInterceptorError(err)).rejects.toThrow()
      expect(setCsrfToken).not.toHaveBeenCalled()
    })

    it('does not redirect on 403 errors but shows toast', async () => {
      vi.mocked(getCsrfToken).mockReturnValue('csrf-token')

      // Mock dynamic toast import
      vi.doMock('@/shared/utils/toast', () => ({
        showToast: { error: vi.fn() },
      }))

      const err = makeAxiosError(403)
      await expect(runResponseInterceptorError(err)).rejects.toThrow()
      expect(appNavigate).not.toHaveBeenCalled()
    })

    it('clears auth storage and redirects to login when refresh fails on protected route', async () => {
      vi.mocked(getCsrfToken).mockReturnValue('old-csrf')

      // Mock api.post to simulate failed refresh
      const postSpy = vi
        .spyOn(api, 'post')
        .mockRejectedValueOnce(makeAxiosError(401, '/auth/refresh-httponly'))

      const sessionRemoveSpy = vi.spyOn(Storage.prototype, 'removeItem')

      const err = makeAxiosError(401, '/protected-route')
      await expect(runResponseInterceptorError(err)).rejects.toThrow()

      expect(postSpy).toHaveBeenCalledWith('/auth/refresh-httponly')
      // Should clear auth storage keys
      expect(sessionRemoveSpy).toHaveBeenCalledWith('csrf_token')
      // Should redirect to login (not a public route)
      expect(appNavigate).toHaveBeenCalledWith('/login')

      postSpy.mockRestore()
      sessionRemoveSpy.mockRestore()
    })

    it('does not redirect to login when refresh fails on a public route', async () => {
      vi.mocked(getCsrfToken).mockReturnValue('old-csrf')

      const postSpy = vi
        .spyOn(api, 'post')
        .mockRejectedValueOnce(makeAxiosError(401, '/auth/refresh-httponly'))

      const err = makeAxiosError(401, '/rooms')
      await expect(runResponseInterceptorError(err)).rejects.toThrow()

      expect(appNavigate).not.toHaveBeenCalledWith('/login')

      postSpy.mockRestore()
    })

    it('updates csrf token and retries original request on successful refresh', async () => {
      vi.mocked(getCsrfToken).mockReturnValue('old-csrf')

      const postSpy = vi.spyOn(api, 'post').mockResolvedValueOnce({
        data: { csrf_token: 'new-csrf-token' },
      })
      // Intercept the retry at the adapter level to prevent real HTTP calls
      const originalAdapter = api.defaults.adapter
      api.defaults.adapter = vi.fn().mockResolvedValueOnce({
        data: { success: true },
        status: 200,
        statusText: 'OK',
        headers: {},
        config: {},
      })

      const err = makeAxiosError(401, '/api/bookings')
      await expect(runResponseInterceptorError(err)).resolves.toBeDefined()

      expect(postSpy).toHaveBeenCalledWith('/auth/refresh-httponly')
      expect(setCsrfToken).toHaveBeenCalledWith('new-csrf-token')

      api.defaults.adapter = originalAdapter
      postSpy.mockRestore()
    })

    it('queues concurrent 401 requests and issues only one refresh call', async () => {
      vi.mocked(getCsrfToken).mockReturnValue('old-csrf')

      let resolveRefresh!: (v: unknown) => void
      const refreshPromise = new Promise(res => {
        resolveRefresh = res
      })

      const postSpy = vi.spyOn(api, 'post').mockReturnValueOnce(refreshPromise as never)
      // Intercept all retried requests at the adapter level
      const originalAdapter = api.defaults.adapter
      api.defaults.adapter = vi.fn().mockResolvedValue({
        data: { success: true },
        status: 200,
        statusText: 'OK',
        headers: {},
        config: {},
      })

      const err1 = makeAxiosError(401, '/api/bookings/1')
      const err2 = makeAxiosError(401, '/api/bookings/2')

      // Fire two concurrent 401s — first triggers refresh, second should queue
      const p1 = runResponseInterceptorError(err1)
      const p2 = runResponseInterceptorError(err2)

      // Resolve the refresh with a new csrf token
      resolveRefresh({ data: { csrf_token: 'refreshed-csrf' } })

      await Promise.allSettled([p1, p2])

      // Refresh must only have been called once despite two concurrent 401s
      expect(postSpy).toHaveBeenCalledTimes(1)
      expect(setCsrfToken).toHaveBeenCalledWith('refreshed-csrf')

      api.defaults.adapter = originalAdapter
      postSpy.mockRestore()
    })
  })
})
