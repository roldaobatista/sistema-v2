import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { SignaturePad } from '@/components/common/SignaturePad'

// Mock the Button component
vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, disabled, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) => (
        <button onClick={onClick} disabled={disabled} {...props}>{children}</button>
    ),
}))

// Mock canvas methods
const mockGetContext = vi.fn()
const mockToDataURL = vi.fn(() => 'data:image/png;base64,mockdata')

beforeEach(() => {
    mockGetContext.mockReturnValue({
        lineWidth: 0,
        lineJoin: '',
        lineCap: '',
        strokeStyle: '',
        scale: vi.fn(),
        beginPath: vi.fn(),
        moveTo: vi.fn(),
        lineTo: vi.fn(),
        stroke: vi.fn(),
        clearRect: vi.fn(),
    })

    HTMLCanvasElement.prototype.getContext = mockGetContext as any
    HTMLCanvasElement.prototype.toDataURL = mockToDataURL
    HTMLCanvasElement.prototype.getBoundingClientRect = vi.fn(() => ({
        left: 0, top: 0, right: 300, bottom: 200, width: 300, height: 200,
        x: 0, y: 0, toJSON: vi.fn(),
    }))
})

describe('SignaturePad', () => {
    const onSave = vi.fn()
    const onClear = vi.fn()

    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders canvas element', () => {
        const { container } = render(<SignaturePad onSave={onSave} />)
        const canvas = container.querySelector('canvas')
        expect(canvas).toBeInTheDocument()
    })

    it('displays placeholder text when empty', () => {
        render(<SignaturePad onSave={onSave} placeholder="Sign here..." />)
        expect(screen.getByText('Sign here...')).toBeInTheDocument()
    })

    it('uses default placeholder when none provided', () => {
        render(<SignaturePad onSave={onSave} />)
        expect(screen.getByText('Assine aqui...')).toBeInTheDocument()
    })

    it('renders clear and save buttons', () => {
        render(<SignaturePad onSave={onSave} />)
        expect(screen.getByText('Limpar')).toBeInTheDocument()
        expect(screen.getByText('Confirmar Assinatura')).toBeInTheDocument()
    })

    it('save button is disabled when canvas is empty', () => {
        render(<SignaturePad onSave={onSave} />)
        expect(screen.getByText('Confirmar Assinatura').closest('button')).toBeDisabled()
    })

    it('calls onClear when clear button is clicked', async () => {
        const user = userEvent.setup()
        render(<SignaturePad onSave={onSave} onClear={onClear} />)

        await user.click(screen.getByText('Limpar'))
        expect(onClear).toHaveBeenCalled()
    })

    it('enables save after drawing on canvas', () => {
        const { container } = render(<SignaturePad onSave={onSave} />)
        const canvas = container.querySelector('canvas')!

        // Simulate mouse drawing
        fireEvent.mouseDown(canvas, { clientX: 10, clientY: 10 })
        fireEvent.mouseMove(canvas, { clientX: 50, clientY: 50 })
        fireEvent.mouseUp(canvas)

        expect(screen.getByText('Confirmar Assinatura').closest('button')).not.toBeDisabled()
    })

    it('calls onSave with base64 data when save is clicked after drawing', async () => {
        const user = userEvent.setup()
        const { container } = render(<SignaturePad onSave={onSave} />)
        const canvas = container.querySelector('canvas')!

        // Draw something
        fireEvent.mouseDown(canvas, { clientX: 10, clientY: 10 })
        fireEvent.mouseMove(canvas, { clientX: 50, clientY: 50 })
        fireEvent.mouseUp(canvas)

        await user.click(screen.getByText('Confirmar Assinatura'))

        expect(onSave).toHaveBeenCalledWith('data:image/png;base64,mockdata')
        expect(mockToDataURL).toHaveBeenCalledWith('image/png')
    })

    it('has crosshair cursor on canvas', () => {
        const { container } = render(<SignaturePad onSave={onSave} />)
        const canvas = container.querySelector('canvas')!
        expect(canvas).toHaveClass('cursor-crosshair')
    })
})
