import { describe, it, expect, beforeEach } from 'vitest'
import { getCsrfToken, setCsrfToken, clearCsrfToken } from './csrf'

describe('CSRF Token Management', () => {
  beforeEach(() => {
    // Clear sessionStorage before each test
    sessionStorage.clear()
  })

  describe('getCsrfToken', () => {
    it('returns null when no token is set', () => {
      expect(getCsrfToken()).toBeNull()
    })

    it('returns the stored token', () => {
      sessionStorage.setItem('csrf_token', 'test-token-123')
      expect(getCsrfToken()).toBe('test-token-123')
    })
  })

  describe('setCsrfToken', () => {
    it('stores the token in sessionStorage', () => {
      setCsrfToken('my-csrf-token')
      expect(sessionStorage.getItem('csrf_token')).toBe('my-csrf-token')
    })

    it('overwrites an existing token', () => {
      setCsrfToken('token-1')
      setCsrfToken('token-2')
      expect(getCsrfToken()).toBe('token-2')
    })
  })

  describe('clearCsrfToken', () => {
    it('removes the token from sessionStorage', () => {
      setCsrfToken('to-be-cleared')
      clearCsrfToken()
      expect(getCsrfToken()).toBeNull()
    })

    it('does not throw if no token exists', () => {
      expect(() => clearCsrfToken()).not.toThrow()
    })
  })
})
