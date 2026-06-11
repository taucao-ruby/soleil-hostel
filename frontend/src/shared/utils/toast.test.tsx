import { describe, expect, it } from 'vitest'
import { act, render, screen } from '@testing-library/react'
import { showToast, ToastContainer } from './toast'

describe('toast utility', () => {
  it('renders a success toast with the internal renderer', async () => {
    render(<ToastContainer />)

    act(() => {
      showToast.success('Toast nội bộ hoạt động', { autoClose: false })
    })

    expect(await screen.findByText('Toast nội bộ hoạt động')).toBeInTheDocument()
  })
})

// ── NEW TESTS APPENDED BY COVERAGE-LIFT PR ──────────────────────────────────

describe('toast variants and lifecycle', () => {
  it('renders error, warning, and info variants with their styling classes', async () => {
    render(<ToastContainer />)

    act(() => {
      showToast.error('Lỗi hệ thống', { autoClose: false })
      showToast.warning('Cảnh báo hệ thống', { autoClose: false })
      showToast.info('Thông tin hệ thống', { autoClose: false })
    })

    expect((await screen.findByText('Lỗi hệ thống')).closest('[role="status"]')).toHaveClass(
      'border-red-200'
    )
    expect(screen.getByText('Cảnh báo hệ thống').closest('[role="status"]')).toHaveClass(
      'border-yellow-200'
    )
    expect(screen.getByText('Thông tin hệ thống').closest('[role="status"]')).toHaveClass(
      'border-blue-200'
    )
  })

  it('flushes toasts queued before the container mounted', async () => {
    showToast.warning('Xếp hàng trước khi container gắn', { autoClose: false })

    render(<ToastContainer />)

    expect(await screen.findByText('Xếp hàng trước khi container gắn')).toBeInTheDocument()
  })

  it('replaces a toast reusing the same toastId instead of stacking', async () => {
    render(<ToastContainer />)

    act(() => {
      showToast.info('Bước 1', { autoClose: false, toastId: 'wizard' })
    })
    expect(await screen.findByText('Bước 1')).toBeInTheDocument()

    act(() => {
      showToast.success('Bước 2', { autoClose: false, toastId: 'wizard' })
    })

    expect(await screen.findByText('Bước 2')).toBeInTheDocument()
    expect(screen.queryByText('Bước 1')).not.toBeInTheDocument()
  })

  it('auto-removes a toast after its autoClose delay', async () => {
    const { waitForElementToBeRemoved } = await import('@testing-library/react')
    render(<ToastContainer />)

    act(() => {
      showToast.success('Tự động đóng', { autoClose: 50 })
    })
    expect(screen.getByText('Tự động đóng')).toBeInTheDocument()

    await waitForElementToBeRemoved(() => screen.queryByText('Tự động đóng'))
  })

  it('dismisses a toast via its close button', async () => {
    const user = (await import('@testing-library/user-event')).default.setup()
    render(<ToastContainer />)

    act(() => {
      showToast.success('Đóng thủ công', { autoClose: false })
    })
    expect(await screen.findByText('Đóng thủ công')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Đóng thông báo' }))

    expect(screen.queryByText('Đóng thủ công')).not.toBeInTheDocument()
  })

  it('promise toast transitions pending → success and passes the value through', async () => {
    render(<ToastContainer />)

    let resolvePromise!: (value: string) => void
    const pending = new Promise<string>(resolve => {
      resolvePromise = resolve
    })

    let tracked!: Promise<string>
    act(() => {
      tracked = showToast.promise(
        pending,
        {
          pending: 'Đang xử lý thanh toán...',
          success: 'Thanh toán thành công',
          error: 'Thanh toán thất bại',
        },
        { autoClose: false }
      )
    })

    expect(await screen.findByText('Đang xử lý thanh toán...')).toBeInTheDocument()

    await act(async () => {
      resolvePromise('hóa đơn #7')
    })

    await expect(tracked).resolves.toBe('hóa đơn #7')
    expect(screen.getByText('Thanh toán thành công')).toBeInTheDocument()
    expect(screen.queryByText('Đang xử lý thanh toán...')).not.toBeInTheDocument()
  })

  it('promise toast surfaces the error copy and rethrows the original failure', async () => {
    render(<ToastContainer />)

    const failure = new Error('card declined')
    let rejectPromise!: (reason: unknown) => void
    const pending = new Promise<string>((_resolve, reject) => {
      rejectPromise = reject
    })

    let tracked!: Promise<string>
    act(() => {
      tracked = showToast.promise(
        pending,
        {
          pending: 'Đang xử lý thanh toán...',
          success: 'Thanh toán thành công',
          error: 'Thanh toán thất bại',
        },
        { autoClose: false }
      )
    })

    const rejection = expect(tracked).rejects.toBe(failure)
    await act(async () => {
      rejectPromise(failure)
    })
    await rejection

    expect(screen.getByText('Thanh toán thất bại')).toBeInTheDocument()
    expect(screen.queryByText('Đang xử lý thanh toán...')).not.toBeInTheDocument()
  })
})
