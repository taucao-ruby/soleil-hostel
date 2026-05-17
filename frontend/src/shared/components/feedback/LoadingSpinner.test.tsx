import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import LoadingSpinner from './LoadingSpinner'

describe('LoadingSpinner', () => {
  it('uses Vietnamese default accessible loading copy', () => {
    render(<LoadingSpinner />)

    expect(screen.getByRole('status', { name: 'Đang tải...' })).toBeInTheDocument()
    expect(screen.getByText('Đang tải...')).toBeInTheDocument()
    expect(screen.queryByText('Loading...')).not.toBeInTheDocument()
  })
})
