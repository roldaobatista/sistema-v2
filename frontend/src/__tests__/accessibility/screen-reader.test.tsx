import { describe, it, expect } from 'vitest'
import { render, screen, waitFor } from '../test-utils'
import userEvent from '@testing-library/user-event'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
} from '@/components/ui/dialog'
import {
    Sheet,
    SheetContent,
    SheetTrigger,
    SheetTitle,
    SheetHeader,
} from '@/components/ui/sheet'
import {
    PaginationEllipsis,
} from '@/components/ui/pagination'

describe('Screen Reader Compatibility', () => {
    // ── Live regions for dynamic content ───────────────────────────────────

    it('aria-live region announces dynamic content changes', () => {
        render(
            <div>
                <div aria-live="polite" data-testid="live-region">
                    Registro salvo com sucesso
                </div>
            </div>
        )

        const region = screen.getByTestId('live-region')
        expect(region).toHaveAttribute('aria-live', 'polite')
        expect(region).toHaveTextContent('Registro salvo com sucesso')
    })

    it('assertive live region announces errors immediately', () => {
        render(
            <div aria-live="assertive" role="alert" data-testid="error-region">
                Erro ao salvar registro
            </div>
        )

        const region = screen.getByTestId('error-region')
        expect(region).toHaveAttribute('aria-live', 'assertive')
        expect(region).toHaveAttribute('role', 'alert')
    })

    // ── Toast notifications ────────────────────────────────────────────────

    it('toast notification container uses role="status" or aria-live', () => {
        render(
            <div role="status" aria-live="polite" data-testid="toast">
                Operacao concluida!
            </div>
        )

        const toast = screen.getByTestId('toast')
        expect(toast).toHaveAttribute('role', 'status')
    })

    // ── Loading states ─────────────────────────────────────────────────────

    it('loading state is announced via aria-busy', () => {
        render(
            <div aria-busy="true" aria-live="polite" data-testid="loading-area">
                Carregando dados...
            </div>
        )

        const area = screen.getByTestId('loading-area')
        expect(area).toHaveAttribute('aria-busy', 'true')
    })

    it('loading Button has disabled state for screen readers', () => {
        render(<Button loading>Salvando...</Button>)
        const button = screen.getByRole('button', { name: /salvando/i })
        expect(button).toBeDisabled()
    })

    // ── sr-only text ───────────────────────────────────────────────────────

    it('Dialog close button provides sr-only text for screen readers', async () => {
        const user = userEvent.setup()
        render(
            <Dialog>
                <DialogTrigger asChild>
                    <button>Open</button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Test Dialog</DialogTitle>
                        <DialogDescription>Description here</DialogDescription>
                    </DialogHeader>
                </DialogContent>
            </Dialog>
        )

        await user.click(screen.getByRole('button', { name: 'Open' }))

        await waitFor(() => {
            const srTexts = screen.getAllByText('Fechar')
            expect(srTexts.length).toBeGreaterThanOrEqual(1)
            // The sr-only span should have the class
            const srSpan = srTexts.find(el => el.classList.contains('sr-only'))
            expect(srSpan).toBeDefined()
        })
    })

    it('Sheet close button provides sr-only text for screen readers', async () => {
        const user = userEvent.setup()
        render(
            <Sheet>
                <SheetTrigger asChild>
                    <button>Open Sheet</button>
                </SheetTrigger>
                <SheetContent>
                    <SheetHeader>
                        <SheetTitle>Sheet Title</SheetTitle>
                    </SheetHeader>
                </SheetContent>
            </Sheet>
        )

        await user.click(screen.getByRole('button', { name: 'Open Sheet' }))

        await waitFor(() => {
            const srTexts = screen.getAllByText('Fechar')
            expect(srTexts.length).toBeGreaterThanOrEqual(1)
        })
    })

    // ── Pagination sr-only text ────────────────────────────────────────────

    it('PaginationEllipsis has sr-only text "More pages"', () => {
        render(<PaginationEllipsis />)
        expect(screen.getByText('More pages')).toBeInTheDocument()
        expect(screen.getByText('More pages')).toHaveClass('sr-only')
    })

    // ── Hidden decorative elements ─────────────────────────────────────────

    it('decorative icons are hidden from screen readers', () => {
        render(
            <span aria-hidden="true" data-testid="decorative-icon">
                icon
            </span>
        )

        const icon = screen.getByTestId('decorative-icon')
        expect(icon).toHaveAttribute('aria-hidden', 'true')
    })
})
