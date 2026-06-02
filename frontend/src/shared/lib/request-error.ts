import { isAxiosError } from 'axios'

export function isAbortError(error: unknown): boolean {
  return (
    (typeof DOMException !== 'undefined' &&
      error instanceof DOMException &&
      error.name === 'AbortError') ||
    (error instanceof Error && error.name === 'AbortError') ||
    (isAxiosError(error) && error.code === 'ERR_CANCELED')
  )
}
