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
