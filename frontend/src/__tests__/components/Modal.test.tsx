import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Modal } from '@/components/ui/modal'

// Mock Radix Dialog primitives to control behavior in tests
vi.mock('@/components/ui/dialog', () => ({
    Dialog: ({ children, open, onOpenChange }: { children: React.ReactNode; open: boolean; onOpenChange: (v: boolean) => void }) =>
        open ? (
            <div data-testid="dialog-backdrop" onClick={() => onOpenChange(false)} onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Escape') onOpenChange(false) }}>
                {children}
            </div>
        ) : null,
    DialogContent: ({ children, size }: { children: React.ReactNode; size?: string }) =>
        <div data-testid="dialog-content" data-size={size} role="dialog" aria-modal="true" onClick={(e: React.MouseEvent) => e.stopPropagation()}>
            {children}
        </div>,
    DialogHeader: ({ children }: { children: React.ReactNode }) =>
        <div data-testid="dialog-header">{children}</div>,
    DialogBody: ({ children }: { children: React.ReactNode }) =>
        <div data-testid="dialog-body">{children}</div>,
    DialogTitle: ({ children }: { children: React.ReactNode }) =>
        <h2 data-testid="dialog-title">{children}</h2>,
    DialogDescription: ({ children, className }: { children: React.ReactNode; className?: string }) =>
        <p data-testid="dialog-description" className={className}>{children}</p>,
}))

describe('Modal', () => {
    const onOpenChange = vi.fn()
    const onClose = vi.fn()

    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders nothing when open is false', () => {
        render(
            <Modal open={false} onOpenChange={onOpenChange} title="Test">
                Body
            </Modal>
        )
        expect(screen.queryByTestId('dialog')).not.toBeInTheDocument()
        expect(screen.queryByText('Body')).not.toBeInTheDocument()
    })

    it('renders dialog content when open is true', () => {
        render(
            <Modal open={true} onOpenChange={onOpenChange} title="Test Title">
                Body content
            </Modal>
        )
        expect(screen.getByRole('dialog')).toBeInTheDocument()
        expect(screen.getByText('Body content')).toBeInTheDocument()
    })

    it('accepts isOpen as alternative to open prop', () => {
        render(
            <Modal isOpen={true} onOpenChange={onOpenChange} title="Alt">
                Alt body
            </Modal>
        )
        expect(screen.getByRole('dialog')).toBeInTheDocument()
    })

    it('displays the title correctly', () => {
        render(
            <Modal open={true} onOpenChange={onOpenChange} title="My Modal Title">
                X
            </Modal>
        )
        expect(screen.getByTestId('dialog-title')).toHaveTextContent('My Modal Title')
    })

    it('displays description when provided', () => {
        render(
            <Modal open={true} onOpenChange={onOpenChange} title="T" description="Detailed description">
                X
            </Modal>
        )
        expect(screen.getByTestId('dialog-description')).toHaveTextContent('Detailed description')
    })

    it('uses title as sr-only description when no description provided', () => {
        render(
            <Modal open={true} onOpenChange={onOpenChange} title="Fallback Title">
                X
            </Modal>
        )
        const desc = screen.getByTestId('dialog-description')
        expect(desc).toHaveTextContent('Fallback Title')
        expect(desc).toHaveClass('sr-only')
    })

    it('fires onClose callback when dialog closes', async () => {
        const user = userEvent.setup()
        render(
            <Modal open={true} onClose={onClose} onOpenChange={onOpenChange} title="T">
                Content
            </Modal>
        )
        // Click the backdrop to trigger onOpenChange(false)
        await user.click(screen.getByTestId('dialog-backdrop'))
        expect(onOpenChange).toHaveBeenCalledWith(false)
        expect(onClose).toHaveBeenCalled()
    })

    it('fires onOpenChange when closing via backdrop click', async () => {
        const user = userEvent.setup()
        render(
            <Modal open={true} onOpenChange={onOpenChange} title="T">
                Content
            </Modal>
        )
        await user.click(screen.getByTestId('dialog-backdrop'))
        expect(onOpenChange).toHaveBeenCalledWith(false)
    })

    it('renders footer when provided', () => {
        render(
            <Modal open={true} onOpenChange={onOpenChange} title="T"
                footer={<button>Save</button>}>
                Content
            </Modal>
        )
        expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument()
    })

    it('does not render footer section when footer is not provided', () => {
        const { container } = render(
            <Modal open={true} onOpenChange={onOpenChange} title="T">
                Content
            </Modal>
        )
        expect(container.querySelector('.border-t')).not.toBeInTheDocument()
    })

    it('passes size prop to DialogContent', () => {
        render(
            <Modal open={true} onOpenChange={onOpenChange} title="T" size="lg">
                Content
            </Modal>
        )
        expect(screen.getByTestId('dialog-content')).toHaveAttribute('data-size', 'lg')
    })

    it('defaults to md size', () => {
        render(
            <Modal open={true} onOpenChange={onOpenChange} title="T">
                Content
            </Modal>
        )
        expect(screen.getByTestId('dialog-content')).toHaveAttribute('data-size', 'md')
    })
})
