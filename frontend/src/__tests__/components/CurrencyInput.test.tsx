import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { CurrencyInput, CurrencyInputInline } from '@/components/common/CurrencyInput'

// Mock the Input component
vi.mock('@/components/ui/input', () => ({
    Input: (props: React.InputHTMLAttributes<HTMLInputElement> & { inputMode?: string }) => (
        <input {...props} />
    ),
}))

describe('CurrencyInput', () => {
    const onChange = vi.fn()

    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders with default value formatted as BRL', () => {
        render(<CurrencyInput onChange={onChange} />)
        const input = screen.getByRole('textbox')
        expect(input).toHaveValue('R$\u00a00,00')
    })

    it('formats initial value correctly', () => {
        render(<CurrencyInput value={1234.56} onChange={onChange} />)
        const input = screen.getByRole('textbox')
        expect(input).toHaveValue('R$\u00a01.234,56')
    })

    it('uses numeric inputMode for mobile keyboards', () => {
        render(<CurrencyInput onChange={onChange} />)
        const input = screen.getByRole('textbox')
        expect(input).toHaveAttribute('inputMode', 'numeric')
    })

    it('strips non-numeric characters and formats on input', async () => {
        const user = userEvent.setup()
        render(<CurrencyInput value={0} onChange={onChange} />)

        const input = screen.getByRole('textbox')
        await user.clear(input)
        await user.type(input, '12345')

        // Each keystroke fires onChange with the numeric value
        // After typing "12345", raw is "12345", numeric = 123.45
        expect(onChange).toHaveBeenLastCalledWith(123.45)
    })

    it('fires onChange with numeric value (cents handling)', async () => {
        const user = userEvent.setup()
        render(<CurrencyInput value={0} onChange={onChange} />)

        const input = screen.getByRole('textbox')
        await user.clear(input)
        await user.type(input, '100')

        expect(onChange).toHaveBeenLastCalledWith(1)
    })

    it('handles zero input correctly', async () => {
        const user = userEvent.setup()
        render(<CurrencyInput value={0} onChange={onChange} />)

        const input = screen.getByRole('textbox')
        await user.clear(input)

        // Clearing yields no digits, raw defaults to "0"
        expect(onChange).toHaveBeenCalledWith(0)
    })

    it('updates display when value prop changes', () => {
        const { rerender } = render(<CurrencyInput value={10} onChange={onChange} />)
        expect(screen.getByRole('textbox')).toHaveValue('R$\u00a010,00')

        rerender(<CurrencyInput value={25.5} onChange={onChange} />)
        expect(screen.getByRole('textbox')).toHaveValue('R$\u00a025,50')
    })

    it('selects all text on focus', async () => {
        const user = userEvent.setup()
        render(<CurrencyInput value={100} onChange={onChange} />)

        const input = screen.getByRole('textbox') as HTMLInputElement
        await user.click(input)

        // After focus, the input's content should be selected
        expect(input.selectionStart).toBe(0)
        expect(input.selectionEnd).toBe(input.value.length)
    })

    it('formats large values with thousand separators', () => {
        render(<CurrencyInput value={1000000} onChange={onChange} />)
        const input = screen.getByRole('textbox')
        expect(input).toHaveValue('R$\u00a01.000.000,00')
    })

    it('passes additional props through', () => {
        render(<CurrencyInput value={0} onChange={onChange} placeholder="Valor" data-testid="currency" />)
        expect(screen.getByTestId('currency')).toBeInTheDocument()
    })

    it('handles NaN and null values gracefully', () => {
        render(<CurrencyInput value={NaN} onChange={onChange} />)
        const input = screen.getByRole('textbox')
        expect(input).toHaveValue('R$\u00a00,00')
    })
})

describe('CurrencyInputInline', () => {
    it('renders as a plain input element', () => {
        render(<CurrencyInputInline value={50} onChange={vi.fn()} data-testid="inline" />)
        expect(screen.getByTestId('inline')).toBeInTheDocument()
        expect(screen.getByTestId('inline').tagName).toBe('INPUT')
    })

    it('formats value as BRL', () => {
        render(<CurrencyInputInline value={99.99} onChange={vi.fn()} />)
        const input = screen.getByRole('textbox')
        expect(input).toHaveValue('R$\u00a099,99')
    })
})
