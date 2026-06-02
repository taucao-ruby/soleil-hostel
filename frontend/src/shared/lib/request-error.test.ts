import { CanceledError } from 'axios'
import { describe, expect, it } from 'vitest'
import { isAbortError } from './request-error'

describe('isAbortError', () => {
  it('recognizes DOM and Axios cancellation errors', () => {
    expect(isAbortError(new DOMException('aborted', 'AbortError'))).toBe(true)
    expect(isAbortError(new CanceledError())).toBe(true)
  })

  it('does not classify ordinary failures as cancellation', () => {
    expect(isAbortError(new Error('request failed'))).toBe(false)
  })
})
