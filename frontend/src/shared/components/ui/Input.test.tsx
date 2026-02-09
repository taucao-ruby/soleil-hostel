import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Input from './Input'

describe('Input Component', () => {
  it('renders an input element', () => {
    render(<Input />)
    expect(screen.getByRole('textbox')).toBeInTheDocument()
  })

  it('renders with a label when provided', () => {
    render(<Input label="Email" />)
    expect(screen.getByLabelText('Email')).toBeInTheDocument()
  })

  it('associates label with input via htmlFor/id', () => {
    render(<Input label="Username" id="username" />)
    const input = screen.getByLabelText('Username')
    expect(input).toHaveAttribute('id', 'username')
  })

  it('generates an id when none is provided', () => {
    render(<Input label="Auto ID" />)
    const input = screen.getByLabelText('Auto ID')
    expect(input.id).toBeTruthy()
  })

  it('displays error message when error prop is set', () => {
    render(<Input error="Field is required" />)
    expect(screen.getByText('Field is required')).toBeInTheDocument()
  })

  it('sets aria-invalid when error is present', () => {
    render(<Input error="Invalid" />)
    expect(screen.getByRole('textbox')).toHaveAttribute('aria-invalid', 'true')
  })

  it('sets aria-invalid to false when no error', () => {
    render(<Input />)
    expect(screen.getByRole('textbox')).toHaveAttribute('aria-invalid', 'false')
  })

  it('sets aria-describedby to error element when error is present', () => {
    render(<Input id="test-input" error="Error text" />)
    const input = screen.getByRole('textbox')
    expect(input).toHaveAttribute('aria-describedby', 'test-input-error')
  })

  it('does not set aria-describedby when no error', () => {
    render(<Input id="test-input" />)
    const input = screen.getByRole('textbox')
    expect(input).not.toHaveAttribute('aria-describedby')
  })

  it('applies error styling when error is present', () => {
    render(<Input error="Error" />)
    const input = screen.getByRole('textbox')
    expect(input.className).toContain('border-red-300')
  })

  it('applies normal styling when no error', () => {
    render(<Input />)
    const input = screen.getByRole('textbox')
    expect(input.className).toContain('border-gray-300')
  })

  it('updates value on user input', async () => {
    const user = userEvent.setup()
    render(<Input />)
    const input = screen.getByRole('textbox')

    await user.type(input, 'hello')
    expect(input).toHaveValue('hello')
  })

  it('calls onChange handler', async () => {
    const handleChange = vi.fn()
    const user = userEvent.setup()
    render(<Input onChange={handleChange} />)

    await user.type(screen.getByRole('textbox'), 'a')
    expect(handleChange).toHaveBeenCalled()
  })

  it('is disabled when disabled prop is true', () => {
    render(<Input disabled />)
    expect(screen.getByRole('textbox')).toBeDisabled()
  })

  it('forwards ref correctly', () => {
    const ref = { current: null } as React.RefObject<HTMLInputElement | null>
    render(<Input ref={ref} />)
    expect(ref.current).toBeInstanceOf(HTMLInputElement)
  })
})
