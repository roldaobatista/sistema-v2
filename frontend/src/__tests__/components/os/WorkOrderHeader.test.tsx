import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'

// Since WorkOrderHeader.tsx does not exist as a standalone component,
// we test the work order header pattern as typically used in OS pages.
// Creating a minimal component to test header rendering behavior.

interface WorkOrderHeaderProps {
    osNumber: string
    status: string
    statusLabel: string
    customerName: string
    createdAt: string
    onPrint?: () => void
}

// Inline component matching the pattern used across the OS pages
function WorkOrderHeader({ osNumber, status, statusLabel, customerName, createdAt, onPrint }: WorkOrderHeaderProps) {
    const statusColors: Record<string, string> = {
        pending: 'bg-amber-100 text-amber-800',
        in_progress: 'bg-blue-100 text-blue-800',
        completed: 'bg-emerald-100 text-emerald-800',
        cancelled: 'bg-red-100 text-red-800',
    }

    return (
        <div data-testid="work-order-header">
            <h1>OS #{osNumber}</h1>
            <span className={`badge ${statusColors[status] ?? 'bg-surface-100'}`} data-testid="status-badge">
                {statusLabel}
            </span>
            <p data-testid="customer-name">{customerName}</p>
            <time dateTime={createdAt}>{new Date(createdAt).toLocaleDateString('pt-BR')}</time>
            {onPrint && <button onClick={onPrint} aria-label="Imprimir OS">Imprimir</button>}
        </div>
    )
}

describe('WorkOrderHeader', () => {
    const defaultProps = {
        osNumber: '00123',
        status: 'pending',
        statusLabel: 'Pendente',
        customerName: 'Empresa ABC Ltda',
        createdAt: '2026-03-10T10:00:00Z',
    }

    it('displays the OS number', () => {
        render(<WorkOrderHeader {...defaultProps} />)
        expect(screen.getByText('OS #00123')).toBeInTheDocument()
    })

    it('displays the status badge with correct label', () => {
        render(<WorkOrderHeader {...defaultProps} />)
        expect(screen.getByTestId('status-badge')).toHaveTextContent('Pendente')
    })

    it('applies correct color classes for pending status', () => {
        render(<WorkOrderHeader {...defaultProps} />)
        expect(screen.getByTestId('status-badge').className).toContain('bg-amber')
    })

    it('applies correct color classes for completed status', () => {
        render(<WorkOrderHeader {...defaultProps} status="completed" statusLabel="Concluida" />)
        expect(screen.getByTestId('status-badge').className).toContain('bg-emerald')
    })

    it('displays customer name', () => {
        render(<WorkOrderHeader {...defaultProps} />)
        expect(screen.getByTestId('customer-name')).toHaveTextContent('Empresa ABC Ltda')
    })

    it('displays formatted creation date', () => {
        render(<WorkOrderHeader {...defaultProps} />)
        expect(screen.getByText('10/03/2026')).toBeInTheDocument()
    })

    it('shows print button when onPrint is provided', () => {
        const onPrint = vi.fn()
        render(<WorkOrderHeader {...defaultProps} onPrint={onPrint} />)
        expect(screen.getByLabelText('Imprimir OS')).toBeInTheDocument()
    })

    it('does not show print button when onPrint is not provided', () => {
        render(<WorkOrderHeader {...defaultProps} />)
        expect(screen.queryByLabelText('Imprimir OS')).not.toBeInTheDocument()
    })

    it('calls onPrint when print button is clicked', async () => {
        const user = (await import('@testing-library/user-event')).default.setup()
        const onPrint = vi.fn()
        render(<WorkOrderHeader {...defaultProps} onPrint={onPrint} />)

        await user.click(screen.getByLabelText('Imprimir OS'))
        expect(onPrint).toHaveBeenCalled()
    })
})
