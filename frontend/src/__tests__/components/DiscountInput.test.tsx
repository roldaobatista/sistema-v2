import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { DiscountInput } from '@/components/common/DiscountInput'

describe('DiscountInput', () => {
    const onUpdate = vi.fn()

    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders in percent mode with formatted display', () => {
        render(<DiscountInput mode="percent" value={10} onUpdate={onUpdate} />)
        const input = screen.getByRole('textbox')
        expect(input).toHaveValue('10,00%')
    })

    it('renders in value mode with BRL formatted display', () => {
        render(<DiscountInput mode="value" value={50} onUpdate={onUpdate} />)
        const input = screen.getByRole('textbox')
        expect(input).toHaveValue('R$\u00a050,00')
    })

    it('shows percent icon toggle button in percent mode', () => {
        render(<DiscountInput mode="percent" value={10} onUpdate={onUpdate} />)
        const toggleBtn = screen.getByTitle('Trocar para valor (R$)')
        expect(toggleBtn).toBeInTheDocument()
    })

    it('shows dollar icon toggle button in value mode', () => {
        render(<DiscountInput mode="value" value={10} onUpdate={onUpdate} />)
        const toggleBtn = screen.getByTitle('Trocar para percentual (%)')
        expect(toggleBtn).toBeInTheDocument()
    })

    it('toggles from percent to value mode on button click', async () => {
        const user = userEvent.setup()
        render(
            <DiscountInput mode="percent" value={10} onUpdate={onUpdate} referenceAmount={200} />
        )

        await user.click(screen.getByTitle('Trocar para valor (R$)'))

        // 10% of 200 = 20
        expect(onUpdate).toHaveBeenCalledWith('value', 20)
    })

    it('toggles from value to percent mode on button click', async () => {
        const user = userEvent.setup()
        render(
            <DiscountInput mode="value" value={20} onUpdate={onUpdate} referenceAmount={200} />
        )

        await user.click(screen.getByTitle('Trocar para percentual (%)'))

        // 20 / 200 * 100 = 10%
        expect(onUpdate).toHaveBeenCalledWith('percent', 10)
    })

    it('resets to 0 when toggling without referenceAmount', async () => {
        const user = userEvent.setup()
        render(<DiscountInput mode="percent" value={10} onUpdate={onUpdate} />)

        await user.click(screen.getByTitle('Trocar para valor (R$)'))

        expect(onUpdate).toHaveBeenCalledWith('value', 0)
    })

    it('fires onUpdate with numeric value on input change', async () => {
        const user = userEvent.setup()
        render(<DiscountInput mode="percent" value={0} onUpdate={onUpdate} />)

        const input = screen.getByRole('textbox')
        await user.clear(input)
        await user.type(input, '1500')

        // raw = "1500", num = 15.00
        expect(onUpdate).toHaveBeenLastCalledWith('percent', 15)
    })

    it('updates display when value prop changes', () => {
        const { rerender } = render(
            <DiscountInput mode="percent" value={5} onUpdate={onUpdate} />
        )
        expect(screen.getByRole('textbox')).toHaveValue('5,00%')

        rerender(<DiscountInput mode="percent" value={15} onUpdate={onUpdate} />)
        expect(screen.getByRole('textbox')).toHaveValue('15,00%')
    })

    it('uses numeric inputMode', () => {
        render(<DiscountInput mode="percent" value={10} onUpdate={onUpdate} />)
        expect(screen.getByRole('textbox')).toHaveAttribute('inputMode', 'numeric')
    })
})
