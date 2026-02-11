import { describe, it, expect, vi, beforeEach } from 'vitest'
import axios from 'axios'

// Mock csrf utilities before importing api
vi.mock('@/shared/utils/csrf', () => ({
  getCsrfToken: vi.fn(),
  setCsrfToken: vi.fn(),
  clearCsrfToken: vi.fn(),
}))

import { getCsrfToken } from '@/shared/utils/csrf'
import api from './api'

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

      // Use interceptor to transform the config
      const config = {
        method: 'post',
        headers: new axios.AxiosHeaders(),
        url: '/test',
      }

      // Run through the request interceptors
      type InterceptorHandler = { fulfilled?: (config: typeof config) => Promise<typeof config> | typeof config }
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

      const config = {
        method: 'get',
        headers: new axios.AxiosHeaders(),
        url: '/test',
      }

      type InterceptorHandler = { fulfilled?: (config: typeof config) => Promise<typeof config> | typeof config }
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

      const config = {
        method: 'post',
        headers: new axios.AxiosHeaders(),
        url: '/test',
      }

      type InterceptorHandler = { fulfilled?: (config: typeof config) => Promise<typeof config> | typeof config }
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
})
