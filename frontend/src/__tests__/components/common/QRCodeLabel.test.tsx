import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QRCodeLabel } from '@/components/common/QRCodeLabel'

// Mock QRCodeSVG
vi.mock('qrcode.react', () => ({
    QRCodeSVG: ({ value, size }: { value: string; size: number }) => (
        <svg data-testid="qr-code" data-value={value} data-size={size} />
    ),
}))

vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) => (
        <button onClick={onClick} {...props}>{children}</button>
    ),
}))

describe('QRCodeLabel', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders QR code SVG with correct value', () => {
        render(<QRCodeLabel value="https://app.kalibrium.com/eq/123" label="EQ-123" />)
        const qrCodes = screen.getAllByTestId('qr-code')
        expect(qrCodes.length).toBeGreaterThan(0)
        expect(qrCodes[0]).toHaveAttribute('data-value', 'https://app.kalibrium.com/eq/123')
    })

    it('displays the label text', () => {
        render(<QRCodeLabel value="test" label="EQ-456" />)
        // Label appears in both print and display areas
        const labels = screen.getAllByText('EQ-456')
        expect(labels.length).toBeGreaterThan(0)
    })

    it('displays sub-label when provided', () => {
        render(<QRCodeLabel value="test" label="EQ-789" subLabel="S/N: ABC123" />)
        const subLabels = screen.getAllByText('S/N: ABC123')
        expect(subLabels.length).toBeGreaterThan(0)
    })

    it('renders print button', () => {
        render(<QRCodeLabel value="test" label="EQ-001" />)
        expect(screen.getByText('Imprimir Etiqueta')).toBeInTheDocument()
    })

    it('opens print window when print button is clicked', async () => {
        const user = userEvent.setup()
        const mockPrintWindow = {
            document: {
                write: vi.fn(),
                close: vi.fn(),
            },
            focus: vi.fn(),
        }
        vi.spyOn(window, 'open').mockReturnValue(mockPrintWindow as any)

        render(<QRCodeLabel value="test" label="EQ-001" />)

        await user.click(screen.getByText('Imprimir Etiqueta'))

        expect(window.open).toHaveBeenCalled()
        expect(mockPrintWindow.document.write).toHaveBeenCalled()
        expect(mockPrintWindow.document.close).toHaveBeenCalled()
    })

    it('shows equipment type by default', () => {
        render(<QRCodeLabel value="test" label="EQ-001" />)
        // Type text appears in the hidden print div
        expect(screen.getByText('Equipamento')).toBeInTheDocument()
    })

    it('shows work-order type when specified', () => {
        render(<QRCodeLabel value="test" label="OS-001" type="work-order" />)
        expect(screen.getByText('Ordem de Serviço')).toBeInTheDocument()
    })
})
