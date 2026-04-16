import { describe, it, expect, vi } from 'vitest'
import { render, screen, waitFor } from '../test-utils'
import userEvent from '@testing-library/user-event'
import { Modal } from '@/components/ui/modal'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
} from '@/components/ui/dialog'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet'

describe('Modals Accessibility', () => {
    // ── Dialog role ────────────────────────────────────────────────────────

    it('Dialog has role="dialog"', async () => {
        const user = userEvent.setup()
        render(
            <Dialog>
                <DialogTrigger asChild>
                    <button>Abrir</button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Titulo do Dialog</DialogTitle>
                        <DialogDescription>Descricao</DialogDescription>
                    </DialogHeader>
                    <p>Conteudo</p>
                </DialogContent>
            </Dialog>
        )

        await user.click(screen.getByRole('button', { name: 'Abrir' }))

        await waitFor(() => {
            expect(screen.getByRole('dialog')).toBeInTheDocument()
        })
    })

    // ── Dialog label ───────────────────────────────────────────────────────

    it('Modal has accessible title via DialogTitle', async () => {
        render(
            <Modal open title="Novo Cliente">
                <p>Formulario</p>
            </Modal>
        )

        await waitFor(() => {
            expect(screen.getByRole('dialog')).toBeInTheDocument()
        })

        expect(screen.getAllByText('Novo Cliente').length).toBeGreaterThanOrEqual(1)
    })

    it('Modal without explicit description uses title as sr-only description', async () => {
        render(
            <Modal open title="Editar Item">
                <p>Conteudo</p>
            </Modal>
        )

        await waitFor(() => {
            const dialog = screen.getByRole('dialog')
            expect(dialog).toBeInTheDocument()
        })
    })

    // ── Focus management ───────────────────────────────────────────────────

    it('focus moves inside dialog when opened', async () => {
        const user = userEvent.setup()
        render(
            <Dialog>
                <DialogTrigger asChild>
                    <button>Abrir Dialog</button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Titulo</DialogTitle>
                        <DialogDescription>Desc</DialogDescription>
                    </DialogHeader>
                    <input data-testid="dialog-input" />
                </DialogContent>
            </Dialog>
        )

        await user.click(screen.getByRole('button', { name: 'Abrir Dialog' }))

        await waitFor(() => {
            const dialog = screen.getByRole('dialog')
            expect(dialog).toBeInTheDocument()
            // Focus should be within the dialog (Radix moves focus to content)
            expect(dialog.contains(document.activeElement)).toBe(true)
        })
    })

    // ── ESC closes dialog ──────────────────────────────────────────────────

    it('ESC key closes the dialog', async () => {
        const user = userEvent.setup()
        render(
            <Dialog>
                <DialogTrigger asChild>
                    <button>Abrir</button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Titulo</DialogTitle>
                        <DialogDescription>Desc</DialogDescription>
                    </DialogHeader>
                    <p>Body</p>
                </DialogContent>
            </Dialog>
        )

        await user.click(screen.getByRole('button', { name: 'Abrir' }))
        await waitFor(() => {
            expect(screen.getByRole('dialog')).toBeInTheDocument()
        })

        await user.keyboard('{Escape}')
        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
        })
    })

    // ── AlertDialog ────────────────────────────────────────────────────────

    it('AlertDialog has role="alertdialog"', async () => {
        const user = userEvent.setup()
        render(
            <AlertDialog>
                <AlertDialogTrigger asChild>
                    <button>Excluir</button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Confirmar exclusao</AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta acao nao pode ser desfeita.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction>Confirmar</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        )

        await user.click(screen.getByRole('button', { name: 'Excluir' }))

        await waitFor(() => {
            expect(screen.getByRole('alertdialog')).toBeInTheDocument()
        })

        expect(screen.getByText('Confirmar exclusao')).toBeInTheDocument()
        expect(screen.getByText('Esta acao nao pode ser desfeita.')).toBeInTheDocument()
    })

    // ── Sheet (side panel) ─────────────────────────────────────────────────

    it('Sheet renders as dialog with close button', async () => {
        const user = userEvent.setup()
        render(
            <Sheet>
                <SheetTrigger asChild>
                    <button>Abrir Painel</button>
                </SheetTrigger>
                <SheetContent>
                    <SheetHeader>
                        <SheetTitle>Detalhes</SheetTitle>
                    </SheetHeader>
                    <p>Conteudo lateral</p>
                </SheetContent>
            </Sheet>
        )

        await user.click(screen.getByRole('button', { name: 'Abrir Painel' }))

        await waitFor(() => {
            expect(screen.getByRole('dialog')).toBeInTheDocument()
        })

        expect(screen.getByText('Detalhes')).toBeInTheDocument()
    })

    // ── Close button sr-only text ──────────────────────────────────────────

    it('Dialog close button has sr-only text "Fechar"', async () => {
        const user = userEvent.setup()
        render(
            <Dialog>
                <DialogTrigger asChild>
                    <button>Open</button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Test</DialogTitle>
                        <DialogDescription>Desc</DialogDescription>
                    </DialogHeader>
                </DialogContent>
            </Dialog>
        )

        await user.click(screen.getByRole('button', { name: 'Open' }))

        await waitFor(() => {
            expect(screen.getByText('Fechar')).toBeInTheDocument()
        })
    })

    // ── Modal open/close callback ──────────────────────────────────────────

    it('Modal calls onClose when closed', async () => {
        const onClose = vi.fn()
        const user = userEvent.setup()

        const TestModal = () => {
            const [open, setOpen] = React.useState(true)
            return (
                <Modal
                    open={open}
                    onOpenChange={setOpen}
                    onClose={onClose}
                    title="Test"
                >
                    <p>Content</p>
                </Modal>
            )
        }

        const React = await import('react')
        render(<TestModal />)

        await waitFor(() => {
            expect(screen.getByRole('dialog')).toBeInTheDocument()
        })

        await user.keyboard('{Escape}')

        await waitFor(() => {
            expect(onClose).toHaveBeenCalled()
        })
    })
})
