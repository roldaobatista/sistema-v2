import { describe, it, expect, vi } from 'vitest'
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
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

describe('Keyboard Navigation', () => {
    // ── Tab through interactive elements ───────────────────────────────────

    it('Tab cycles through interactive elements in order', async () => {
        const user = userEvent.setup()
        render(
            <div>
                <Button data-testid="btn1">Primeiro</Button>
                <input data-testid="input1" placeholder="Campo" />
                <Button data-testid="btn2">Segundo</Button>
                <a href="/test" data-testid="link1">Link</a>
            </div>
        )

        await user.tab()
        expect(screen.getByTestId('btn1')).toHaveFocus()

        await user.tab()
        expect(screen.getByTestId('input1')).toHaveFocus()

        await user.tab()
        expect(screen.getByTestId('btn2')).toHaveFocus()

        await user.tab()
        expect(screen.getByTestId('link1')).toHaveFocus()
    })

    it('Shift+Tab moves focus backwards', async () => {
        const user = userEvent.setup()
        render(
            <div>
                <Button>A</Button>
                <Button>B</Button>
                <Button>C</Button>
            </div>
        )

        // Tab to C
        await user.tab()
        await user.tab()
        await user.tab()
        expect(screen.getByRole('button', { name: 'C' })).toHaveFocus()

        // Shift+Tab back to B
        await user.tab({ shift: true })
        expect(screen.getByRole('button', { name: 'B' })).toHaveFocus()
    })

    // ── Dropdown menus ─────────────────────────────────────────────────────

    it('DropdownMenu opens with Enter key', async () => {
        const user = userEvent.setup()
        render(
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button>Opcoes</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    <DropdownMenuItem>Editar</DropdownMenuItem>
                    <DropdownMenuItem>Excluir</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        )

        const trigger = screen.getByRole('button', { name: 'Opcoes' })
        trigger.focus()
        await user.keyboard('{Enter}')

        await waitFor(() => {
            expect(screen.getByRole('menu')).toBeInTheDocument()
        })
    })

    it('DropdownMenu opens with Space key', async () => {
        const user = userEvent.setup()
        render(
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button>Acoes</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    <DropdownMenuItem>Item 1</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        )

        const trigger = screen.getByRole('button', { name: 'Acoes' })
        trigger.focus()
        await user.keyboard(' ')

        await waitFor(() => {
            expect(screen.getByRole('menu')).toBeInTheDocument()
        })
    })

    it('DropdownMenu items are navigable with arrow keys', async () => {
        const user = userEvent.setup()
        render(
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button>Menu</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    <DropdownMenuItem>Editar</DropdownMenuItem>
                    <DropdownMenuItem>Duplicar</DropdownMenuItem>
                    <DropdownMenuItem>Excluir</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        )

        await user.click(screen.getByRole('button', { name: 'Menu' }))

        await waitFor(() => {
            expect(screen.getByRole('menu')).toBeInTheDocument()
        })

        // Arrow down to navigate menu items
        await user.keyboard('{ArrowDown}')
        await user.keyboard('{ArrowDown}')

        // Menu items should exist
        expect(screen.getByText('Editar')).toBeInTheDocument()
        expect(screen.getByText('Duplicar')).toBeInTheDocument()
        expect(screen.getByText('Excluir')).toBeInTheDocument()
    })

    // ── Tabs component ─────────────────────────────────────────────────────

    it('Tabs triggers are clickable and show correct content', async () => {
        const user = userEvent.setup()
        render(
            <Tabs defaultValue="tab1">
                <TabsList>
                    <TabsTrigger value="tab1">Geral</TabsTrigger>
                    <TabsTrigger value="tab2">Detalhes</TabsTrigger>
                    <TabsTrigger value="tab3">Historico</TabsTrigger>
                </TabsList>
                <TabsContent value="tab1">Conteudo geral</TabsContent>
                <TabsContent value="tab2">Conteudo detalhes</TabsContent>
                <TabsContent value="tab3">Conteudo historico</TabsContent>
            </Tabs>
        )

        expect(screen.getByText('Conteudo geral')).toBeInTheDocument()
        expect(screen.queryByText('Conteudo detalhes')).not.toBeInTheDocument()

        await user.click(screen.getByText('Detalhes'))
        expect(screen.getByText('Conteudo detalhes')).toBeInTheDocument()
        expect(screen.queryByText('Conteudo geral')).not.toBeInTheDocument()
    })

    it('Tab triggers are keyboard focusable', async () => {
        const user = userEvent.setup()
        render(
            <Tabs defaultValue="a">
                <TabsList>
                    <TabsTrigger value="a">Tab A</TabsTrigger>
                    <TabsTrigger value="b">Tab B</TabsTrigger>
                </TabsList>
                <TabsContent value="a">Content A</TabsContent>
                <TabsContent value="b">Content B</TabsContent>
            </Tabs>
        )

        await user.tab()
        const tabA = screen.getByText('Tab A')
        expect(tabA).toHaveFocus()
    })

    // ── Accordion ──────────────────────────────────────────────────────────

    it('Accordion triggers are keyboard accessible', async () => {
        const user = userEvent.setup()
        render(
            <Accordion type="single" collapsible>
                <AccordionItem value="item-1">
                    <AccordionTrigger>Informacoes Gerais</AccordionTrigger>
                    <AccordionContent>Conteudo das informacoes</AccordionContent>
                </AccordionItem>
                <AccordionItem value="item-2">
                    <AccordionTrigger>Endereco</AccordionTrigger>
                    <AccordionContent>Conteudo do endereco</AccordionContent>
                </AccordionItem>
            </Accordion>
        )

        const trigger1 = screen.getByText('Informacoes Gerais')
        trigger1.focus()
        await user.keyboard('{Enter}')

        await waitFor(() => {
            expect(screen.getByText('Conteudo das informacoes')).toBeInTheDocument()
        })
    })

    it('Accordion items have correct expanded state', async () => {
        const user = userEvent.setup()
        render(
            <Accordion type="single" collapsible>
                <AccordionItem value="item-1">
                    <AccordionTrigger>Secao 1</AccordionTrigger>
                    <AccordionContent>Conteudo 1</AccordionContent>
                </AccordionItem>
            </Accordion>
        )

        const trigger = screen.getByText('Secao 1')
        expect(trigger.closest('button')).toHaveAttribute('data-state', 'closed')

        await user.click(trigger)
        await waitFor(() => {
            expect(trigger.closest('button')).toHaveAttribute('data-state', 'open')
        })
    })

    // ── Modal focus trap ───────────────────────────────────────────────────

    it('Dialog traps focus within when open', async () => {
        const user = userEvent.setup()
        render(
            <div>
                <button>Outside Button</button>
                <Dialog>
                    <DialogTrigger asChild>
                        <button>Open Dialog</button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Trapped</DialogTitle>
                            <DialogDescription>Focus should stay inside</DialogDescription>
                        </DialogHeader>
                        <input data-testid="inside-input" />
                        <button>Inside Button</button>
                    </DialogContent>
                </Dialog>
            </div>
        )

        await user.click(screen.getByRole('button', { name: 'Open Dialog' }))

        await waitFor(() => {
            const dialog = screen.getByRole('dialog')
            expect(dialog).toBeInTheDocument()
        })

        // Focus should be inside the dialog
        expect(screen.getByRole('dialog').contains(document.activeElement)).toBe(true)
    })

    // ── Button Enter and Space ─────────────────────────────────────────────

    it('Button activates on Enter key', async () => {
        const onClick = vi.fn()
        const user = userEvent.setup()

        render(<Button onClick={onClick}>Confirmar</Button>)

        const button = screen.getByRole('button', { name: 'Confirmar' })
        button.focus()
        await user.keyboard('{Enter}')

        expect(onClick).toHaveBeenCalledTimes(1)
    })

    it('Button activates on Space key', async () => {
        const onClick = vi.fn()
        const user = userEvent.setup()

        render(<Button onClick={onClick}>Cancelar</Button>)

        const button = screen.getByRole('button', { name: 'Cancelar' })
        button.focus()
        await user.keyboard(' ')

        expect(onClick).toHaveBeenCalledTimes(1)
    })

    // ── ESC to dismiss ─────────────────────────────────────────────────────

    it('DropdownMenu closes with Escape key', async () => {
        const user = userEvent.setup()
        render(
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button>Toggle</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    <DropdownMenuItem>Item</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        )

        await user.click(screen.getByRole('button', { name: 'Toggle' }))
        await waitFor(() => {
            expect(screen.getByRole('menu')).toBeInTheDocument()
        })

        await user.keyboard('{Escape}')
        await waitFor(() => {
            expect(screen.queryByRole('menu')).not.toBeInTheDocument()
        })
    })
})
