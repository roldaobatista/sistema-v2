import { describe, it, expect } from 'vitest'
import { render, screen } from '../test-utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

/**
 * Color contrast tests verify that critical UI elements use appropriate
 * color classes that meet WCAG contrast requirements.
 * We validate the CSS classes applied rather than computing actual ratios,
 * as actual color values depend on the theme CSS variables at runtime.
 */
describe('Color Contrast', () => {
    // ── Status badges ──────────────────────────────────────────────────────

    it('success Badge uses contrasting text and background colors', () => {
        render(<Badge variant="success">Ativo</Badge>)
        const badge = screen.getByText('Ativo')
        expect(badge).toHaveClass('text-emerald-700')
        expect(badge).toHaveClass('bg-emerald-50')
    })

    it('danger Badge uses contrasting text and background colors', () => {
        render(<Badge variant="danger">Atrasado</Badge>)
        const badge = screen.getByText('Atrasado')
        expect(badge).toHaveClass('text-red-700')
        expect(badge).toHaveClass('bg-red-50')
    })

    it('warning Badge uses contrasting text and background colors', () => {
        render(<Badge variant="warning">Pendente</Badge>)
        const badge = screen.getByText('Pendente')
        expect(badge).toHaveClass('text-amber-700')
        expect(badge).toHaveClass('bg-amber-50')
    })

    it('info Badge uses contrasting text and background colors', () => {
        render(<Badge variant="info">Em analise</Badge>)
        const badge = screen.getByText('Em analise')
        expect(badge).toHaveClass('text-sky-700')
        expect(badge).toHaveClass('bg-sky-50')
    })

    // ── Error messages ─────────────────────────────────────────────────────

    it('Input error message uses red text for contrast on light backgrounds', () => {
        render(<Input label="Email" error="Campo obrigatorio" />)
        const errorMsg = screen.getByText('Campo obrigatorio')
        expect(errorMsg).toHaveClass('text-red-600')
    })

    // ── Button variants ────────────────────────────────────────────────────

    it('danger Button uses white text on colored background', () => {
        const { container } = render(<Button variant="danger">Excluir</Button>)
        const button = screen.getByRole('button', { name: 'Excluir' })
        expect(button.className).toContain('text-white')
    })

    it('outline Button has visible border for contrast', () => {
        render(<Button variant="outline">Cancelar</Button>)
        const button = screen.getByRole('button', { name: 'Cancelar' })
        expect(button.className).toContain('border')
    })

    // ── Badge dot indicator ────────────────────────────────────────────────

    it('Badge with dot renders colored indicator dot', () => {
        const { container } = render(<Badge variant="success" dot>Online</Badge>)
        const dot = container.querySelector('.rounded-full.bg-current')
        expect(dot).toBeInTheDocument()
    })
})
