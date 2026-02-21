import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import FilterChips from './FilterChips'
import type { FilterChip } from '../home.types'

const CHIPS: FilterChip[] = [
  { id: 'all', label: 'Tất cả' },
  { id: 'dorm', label: 'Dorm' },
  { id: 'priv', label: 'Phòng đôi' },
]

describe('FilterChips', () => {
  it('renders all chips from props', () => {
    render(<FilterChips chips={CHIPS} />)
    expect(screen.getByText('Tất cả')).toBeInTheDocument()
    expect(screen.getByText('Dorm')).toBeInTheDocument()
    expect(screen.getByText('Phòng đôi')).toBeInTheDocument()
  })

  it('first chip is active by default (aria-pressed=true)', () => {
    render(<FilterChips chips={CHIPS} />)
    expect(screen.getByRole('button', { name: 'Tất cả' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: 'Dorm' })).toHaveAttribute('aria-pressed', 'false')
  })

  it('clicking an inactive chip calls onFilter and toggles active styling', async () => {
    const user = userEvent.setup()
    const onFilter = vi.fn()
    render(<FilterChips chips={CHIPS} onFilter={onFilter} />)

    const dormChip = screen.getByRole('button', { name: 'Dorm' })
    await user.click(dormChip)

    // Dorm chip becomes active
    expect(dormChip).toHaveAttribute('aria-pressed', 'true')
    // Previous active chip is deactivated
    expect(screen.getByRole('button', { name: 'Tất cả' })).toHaveAttribute('aria-pressed', 'false')
    // onFilter called with chip id
    expect(onFilter).toHaveBeenCalledWith('dorm')
  })

  it('clicking the already-active chip keeps it active', async () => {
    const user = userEvent.setup()
    render(<FilterChips chips={CHIPS} />)

    const allChip = screen.getByRole('button', { name: 'Tất cả' })
    await user.click(allChip)

    expect(allChip).toHaveAttribute('aria-pressed', 'true')
  })
})
