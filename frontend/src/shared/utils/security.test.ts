import { describe, it, expect } from 'vitest'
import { escapeHtml, sanitizeInput, isValidEmail, isValidUrl } from './security'

describe('Security Utilities', () => {
  describe('escapeHtml', () => {
    it('escapes ampersand', () => {
      expect(escapeHtml('foo & bar')).toBe('foo &amp; bar')
    })

    it('escapes less-than sign', () => {
      expect(escapeHtml('<script>')).toBe('&lt;script&gt;')
    })

    it('escapes greater-than sign', () => {
      expect(escapeHtml('a > b')).toBe('a &gt; b')
    })

    it('escapes double quotes', () => {
      expect(escapeHtml('"hello"')).toBe('&quot;hello&quot;')
    })

    it('escapes single quotes', () => {
      expect(escapeHtml("it's")).toBe('it&#039;s')
    })

    it('escapes multiple special characters at once', () => {
      expect(escapeHtml('<img src="x" onerror=\'alert(1)\'>')).toBe(
        '&lt;img src=&quot;x&quot; onerror=&#039;alert(1)&#039;&gt;'
      )
    })

    it('returns empty string for empty input', () => {
      expect(escapeHtml('')).toBe('')
    })

    it('does not modify strings without special characters', () => {
      expect(escapeHtml('hello world 123')).toBe('hello world 123')
    })
  })

  describe('sanitizeInput', () => {
    it('trims whitespace and escapes HTML', () => {
      expect(sanitizeInput('  <b>bold</b>  ')).toBe('&lt;b&gt;bold&lt;/b&gt;')
    })

    it('trims leading and trailing whitespace', () => {
      expect(sanitizeInput('  hello  ')).toBe('hello')
    })

    it('handles empty string', () => {
      expect(sanitizeInput('')).toBe('')
    })

    it('handles string with only whitespace', () => {
      expect(sanitizeInput('   ')).toBe('')
    })
  })

  describe('isValidEmail', () => {
    it('returns true for valid email', () => {
      expect(isValidEmail('user@example.com')).toBe(true)
    })

    it('returns true for email with subdomain', () => {
      expect(isValidEmail('user@mail.example.com')).toBe(true)
    })

    it('returns false for missing @', () => {
      expect(isValidEmail('userexample.com')).toBe(false)
    })

    it('returns false for missing domain', () => {
      expect(isValidEmail('user@')).toBe(false)
    })

    it('returns false for empty string', () => {
      expect(isValidEmail('')).toBe(false)
    })

    it('returns false for email with spaces', () => {
      expect(isValidEmail('user @example.com')).toBe(false)
    })
  })

  describe('isValidUrl', () => {
    it('returns true for valid http URL', () => {
      expect(isValidUrl('http://example.com')).toBe(true)
    })

    it('returns true for valid https URL', () => {
      expect(isValidUrl('https://example.com/path?q=1')).toBe(true)
    })

    it('returns false for invalid URL', () => {
      expect(isValidUrl('not-a-url')).toBe(false)
    })

    it('returns false for empty string', () => {
      expect(isValidUrl('')).toBe(false)
    })
  })
})
