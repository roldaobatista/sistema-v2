import { describe, it, expect } from 'vitest'
import { render, screen } from '../test-utils'
import userEvent from '@testing-library/user-event'
import { Button } from '@/components/ui/button'
import { IconButton } from '@/components/ui/iconbutton'

describe('Buttons Accessibility', () => {
    // ── Icon-only buttons ──────────────────────────────────────────────────

    it('IconButton has aria-label from label prop', () => {
        render(
            <IconButton
                label="Editar"
                icon={<span data-testid="icon">E</span>}
            />
        )

        const button = screen.getByRole('button', { name: 'Editar' })
        expect(button).toBeInTheDocument()
        expect(button).toHaveAttribute('aria-label', 'Editar')
    })

    it('IconButton falls back to tooltip prop for aria-label', () => {
        render(
            <IconButton
                tooltip="Excluir item"
                icon={<span>X</span>}
            />
        )

        const button = screen.getByRole('button', { name: 'Excluir item' })
        expect(button).toHaveAttribute('aria-label', 'Excluir item')
    })

    it('IconButton without label/tooltip uses default aria-label', () => {
        render(
            <IconButton icon={<span>?</span>} />
        )

        const button = screen.getByRole('button', { name: 'Ação' })
        expect(button).toHaveAttribute('aria-label', 'Ação')
    })

    // ── Loading buttons ────────────────────────────────────────────────────

    it('loading Button is disabled and shows spinner', () => {
        render(<Button loading>Salvando...</Button>)

        const button = screen.getByRole('button', { name: /salvando/i })
        expect(button).toBeDisabled()
    })

    it('loading Button renders loader icon', () => {
        const { container } = render(<Button loading>Processando</Button>)

        // Loader2 component adds animate-spin class
        const spinner = container.querySelector('.animate-spin')
        expect(spinner).toBeInTheDocument()
    })

    // ── Disabled buttons ───────────────────────────────────────────────────

    it('disabled Button has disabled attribute', () => {
        render(<Button disabled>Indisponivel</Button>)

        const button = screen.getByRole('button', { name: 'Indisponivel' })
        expect(button).toBeDisabled()
    })

    it('disabled Button cannot be focused via tab', async () => {
        const user = userEvent.setup()
        render(
            <div>
                <Button>Primeiro</Button>
                <Button disabled>Desabilitado</Button>
                <Button>Terceiro</Button>
            </div>
        )

        await user.tab()
        expect(screen.getByRole('button', { name: 'Primeiro' })).toHaveFocus()

        await user.tab()
        // Should skip disabled and go to Terceiro
        expect(screen.getByRole('button', { name: 'Terceiro' })).toHaveFocus()
    })

    // ── Button variants ────────────────────────────────────────────────────

    it('Button with icon renders icon alongside text', () => {
        render(
            <Button icon={<span data-testid="btn-icon">+</span>}>
                Novo
            </Button>
        )

        expect(screen.getByTestId('btn-icon')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /novo/i })).toBeInTheDocument()
    })

    it('Button renders as button element with correct type', () => {
        render(<Button type="submit">Enviar</Button>)
        const button = screen.getByRole('button', { name: 'Enviar' })
        expect(button).toHaveAttribute('type', 'submit')
        expect(button.tagName).toBe('BUTTON')
    })

    // ── asChild pattern ────────────────────────────────────────────────────

    it('Button with asChild and disabled sets aria-disabled on child', () => {
        render(
            <Button asChild disabled>
                <a href="/test">Link Button</a>
            </Button>
        )

        const link = screen.getByText('Link Button')
        expect(link).toHaveAttribute('aria-disabled', 'true')
    })
})
