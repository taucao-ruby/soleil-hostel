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

// ── NEW TESTS APPENDED BY COVERAGE-LIFT PR ──────────────────────────────────

describe('Skeleton presets', () => {
  it('SkeletonText renders three lines by default with a shorter last line', async () => {
    const { SkeletonText } = await import('./Skeleton')
    render(<SkeletonText />)

    const lines = screen.getAllByRole('status')
    expect(lines).toHaveLength(3)
    expect(lines[0]).toHaveStyle({ width: '100%' })
    expect(lines[2]).toHaveStyle({ width: '75%' })
  })

  it('SkeletonText honors a custom line count', async () => {
    const { SkeletonText } = await import('./Skeleton')
    render(<SkeletonText lines={1} />)

    const lines = screen.getAllByRole('status')
    expect(lines).toHaveLength(1)
    expect(lines[0]).toHaveStyle({ width: '75%' })
  })

  it('SkeletonCard composes image, title, text, and footer placeholders', async () => {
    const { SkeletonCard } = await import('./Skeleton')
    render(<SkeletonCard />)

    expect(screen.getAllByRole('status')).toHaveLength(6)
  })

  it('applies the requested rounded variant to the base skeleton', () => {
    render(<Skeleton rounded="full" />)

    expect(screen.getByRole('status').className).toContain('rounded-full')
  })
})
