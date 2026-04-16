import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { DealCard } from '@/components/crm/DealCard'

// Mock dnd-kit sortable
vi.mock('@dnd-kit/sortable', () => ({
    useSortable: () => ({
        attributes: {},
        listeners: {},
        setNodeRef: vi.fn(),
        transform: null,
        transition: null,
        isDragging: false,
    }),
}))

vi.mock('@dnd-kit/utilities', () => ({
    CSS: {
        Transform: {
            toString: () => null,
        },
    },
}))

vi.mock('@/lib/utils', async () => {
    const actual = await vi.importActual<typeof import('@/lib/utils')>('@/lib/utils')
    return {
        ...actual,
        formatCurrency: (value: number) =>
            new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value),
    }
})

const baseDeal = {
    id: 1,
    title: 'Contrato Calibracao Anual',
    value: 15000,
    probability: 75,
    stage_id: 1,
    customer: { id: 1, name: 'Industria Metal SA' },
    assignee: { id: 2, name: 'Carlos Pereira' },
    expected_close_date: '2026-04-15',
} as any

describe('DealCard', () => {
    it('displays the deal title', () => {
        render(<DealCard deal={baseDeal} />)
        expect(screen.getByText('Contrato Calibracao Anual')).toBeInTheDocument()
    })

    it('displays the customer name', () => {
        render(<DealCard deal={baseDeal} />)
        expect(screen.getByText('Industria Metal SA')).toBeInTheDocument()
    })

    it('displays the deal value formatted as currency', () => {
        render(<DealCard deal={baseDeal} />)
        expect(screen.getByText(/15\.000/)).toBeInTheDocument()
    })

    it('displays assignee first name', () => {
        render(<DealCard deal={baseDeal} />)
        expect(screen.getByText('Carlos')).toBeInTheDocument()
    })

    it('displays expected close date formatted', () => {
        render(<DealCard deal={baseDeal} />)
        // Date renders via toLocaleDateString with day/month, timezone may shift
        const dateEl = screen.getByText(/\/04/)
        expect(dateEl).toBeInTheDocument()
    })

    it('calls onClick when card is clicked', async () => {
        const user = userEvent.setup()
        const onClick = vi.fn()
        render(<DealCard deal={baseDeal} onClick={onClick} />)

        await user.click(screen.getByText('Contrato Calibracao Anual'))
        expect(onClick).toHaveBeenCalled()
    })

    it('renders probability progress bar', () => {
        const { container } = render(<DealCard deal={baseDeal} />)
        const bar = container.querySelector('[style*="width: 75%"]')
        expect(bar).toBeInTheDocument()
    })

    it('renders drag handle', () => {
        const { container } = render(<DealCard deal={baseDeal} />)
        // GripVertical icon is inside a button element
        const gripButton = container.querySelector('button')
        expect(gripButton).toBeInTheDocument()
    })

    it('handles deal without assignee gracefully', () => {
        const dealNoAssignee = { ...baseDeal, assignee: null }
        render(<DealCard deal={dealNoAssignee} />)
        expect(screen.getByText('Contrato Calibracao Anual')).toBeInTheDocument()
    })

    it('handles deal without expected_close_date gracefully', () => {
        const dealNoDate = { ...baseDeal, expected_close_date: null }
        render(<DealCard deal={dealNoDate} />)
        expect(screen.getByText('Contrato Calibracao Anual')).toBeInTheDocument()
    })
})
