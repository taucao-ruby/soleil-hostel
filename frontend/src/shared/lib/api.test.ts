import { describe, it, expect, vi, beforeEach } from 'vitest'
import axios, { AxiosHeaders } from 'axios'

// Mock csrf utilities before importing api
vi.mock('@/shared/utils/csrf', () => ({
  getCsrfToken: vi.fn(),
  setCsrfToken: vi.fn(),
  clearCsrfToken: vi.fn(),
}))

import { getCsrfToken } from '@/shared/utils/csrf'
import api from './api'

interface RequestConfig {
  method: string
  headers: AxiosHeaders
  url: string
}

interface InterceptorHandler {
  fulfilled?: (config: RequestConfig) => Promise<RequestConfig> | RequestConfig
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
})
