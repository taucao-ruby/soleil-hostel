import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import Skeleton from './Skeleton'

describe('Skeleton', () => {
  it('uses Vietnamese sr-only/status loading copy', () => {
    render(<Skeleton />)

    expect(screen.getByRole('status', { name: 'Đang tải...' })).toBeInTheDocument()
    expect(screen.getByText('Đang tải...')).toBeInTheDocument()
    expect(screen.queryByText('Loading...')).not.toBeInTheDocument()
  })
})
